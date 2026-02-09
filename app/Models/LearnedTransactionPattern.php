<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LearnedTransactionPattern extends Model
{
    protected $fillable = [
        'description_hash',
        'normalized_description',
        'original_description',
        'transaction_type',
        'amount',
        'source',
        'is_manual_override',
        'occurrence_count',
        'confidence_score',
        'user_id',
    ];

    protected $casts = [
        'is_manual_override' => 'boolean',
        'amount' => 'decimal:2',
    ];

    /**
     * Normalize a transaction description for pattern matching.
     * Removes dates, normalizes numbers, and cleans whitespace.
     */
    public static function normalizeDescription(string $description): string
    {
        // Convert to lowercase
        $normalized = strtolower($description);

        // Remove dates in various formats
        $normalized = preg_replace('/\d{1,2}[\/\-]\d{1,2}([\/\-]\d{2,4})?/', '', $normalized);

        // Remove reference numbers and IDs (sequences of digits)
        $normalized = preg_replace('/\b\d{4,}\b/', '#REF#', $normalized);

        // Normalize check numbers
        $normalized = preg_replace('/check\s*#?\s*\d+/i', 'CHECK #REF#', $normalized);

        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Trim
        $normalized = trim($normalized);

        return $normalized;
    }

    /**
     * Generate hash for a normalized description.
     */
    public static function generateHash(string $normalizedDescription): string
    {
        return md5($normalizedDescription);
    }

    /**
     * Learn from a transaction (store or update pattern).
     */
    public static function learnFromTransaction(
        string $description,
        string $type,
        float $amount = null,
        string $source = 'openai',
        bool $isManualOverride = false,
        int $userId = null
    ): self {
        $normalized = self::normalizeDescription($description);
        $hash = self::generateHash($normalized);

        // Check if pattern exists
        $existing = self::where('description_hash', $hash)->first();

        if ($existing) {
            // If manual override, always update
            if ($isManualOverride) {
                $existing->update([
                    'transaction_type' => $type,
                    'source' => 'manual',
                    'is_manual_override' => true,
                    'occurrence_count' => $existing->occurrence_count + 1,
                    'confidence_score' => 100,
                    'user_id' => $userId,
                ]);
            } else {
                // Only update if not manually overridden and same type
                if (!$existing->is_manual_override) {
                    $existing->increment('occurrence_count');
                    // Increase confidence with more occurrences (max 100)
                    $newConfidence = min(100, 50 + ($existing->occurrence_count * 5));
                    $existing->update(['confidence_score' => $newConfidence]);
                }
            }
            return $existing->fresh();
        }

        // Create new pattern
        return self::create([
            'description_hash' => $hash,
            'normalized_description' => $normalized,
            'original_description' => substr($description, 0, 500),
            'transaction_type' => $type,
            'amount' => $amount,
            'source' => $source,
            'is_manual_override' => $isManualOverride,
            'occurrence_count' => 1,
            'confidence_score' => $isManualOverride ? 100 : 50,
            'user_id' => $userId,
        ]);
    }

    /**
     * Learn from multiple transactions at once.
     */
    public static function learnFromTransactions(array $transactions, int $userId = null): int
    {
        $learned = 0;

        foreach ($transactions as $txn) {
            $description = $txn['description'] ?? '';
            $type = $txn['type'] ?? '';
            $amount = $txn['amount'] ?? null;

            if ($description && $type) {
                self::learnFromTransaction($description, $type, $amount, 'openai', false, $userId);
                $learned++;
            }
        }

        return $learned;
    }

    /**
     * Get learned classification for a description.
     * Returns null if no pattern found, or the learned type if found.
     */
    public static function getLearnedClassification(string $description): ?array
    {
        $normalized = self::normalizeDescription($description);
        $hash = self::generateHash($normalized);

        $pattern = self::where('description_hash', $hash)
            ->where('confidence_score', '>=', 60) // Only use patterns with decent confidence
            ->first();

        if ($pattern) {
            return [
                'type' => $pattern->transaction_type,
                'confidence' => $pattern->confidence_score,
                'source' => $pattern->source,
                'is_manual' => $pattern->is_manual_override,
            ];
        }

        // Try fuzzy matching on normalized description
        $pattern = self::where('normalized_description', $normalized)
            ->where('confidence_score', '>=', 60)
            ->orderBy('confidence_score', 'desc')
            ->first();

        if ($pattern) {
            return [
                'type' => $pattern->transaction_type,
                'confidence' => $pattern->confidence_score,
                'source' => $pattern->source,
                'is_manual' => $pattern->is_manual_override,
            ];
        }

        return null;
    }

    /**
     * Get all learned patterns for use in AI prompt.
     * Returns patterns with highest confidence first.
     */
    public static function getPatternsForAI(int $limit = 100): array
    {
        return self::select('normalized_description', 'transaction_type', 'confidence_score', 'is_manual_override')
            ->where('confidence_score', '>=', 60)
            ->orderByDesc('is_manual_override') // Manual overrides first
            ->orderByDesc('confidence_score')
            ->orderByDesc('occurrence_count')
            ->limit($limit)
            ->get()
            ->map(function ($pattern) {
                return [
                    'pattern' => $pattern->normalized_description,
                    'type' => $pattern->transaction_type,
                    'confidence' => $pattern->confidence_score,
                    'is_manual' => $pattern->is_manual_override,
                ];
            })
            ->toArray();
    }

    /**
     * Apply learned patterns to transactions (post-processing).
     */
    public static function applyToTransactions(array &$transactions): int
    {
        $corrected = 0;

        foreach ($transactions as &$txn) {
            $description = $txn['description'] ?? '';
            if (!$description) continue;

            $learned = self::getLearnedClassification($description);

            if ($learned && $learned['type'] !== ($txn['type'] ?? '')) {
                $txn['original_type'] = $txn['type'];
                $txn['type'] = $learned['type'];
                $txn['auto_corrected'] = true;
                $txn['correction_source'] = $learned['source'];
                $txn['correction_confidence'] = $learned['confidence'];
                $corrected++;
            }
        }

        return $corrected;
    }

    /**
     * Reset a pattern (remove manual override, let OpenAI re-learn).
     */
    public static function resetPattern(string $description): bool
    {
        $normalized = self::normalizeDescription($description);
        $hash = self::generateHash($normalized);

        $pattern = self::where('description_hash', $hash)->first();

        if ($pattern) {
            $pattern->delete();
            return true;
        }

        return false;
    }

    /**
     * Get statistics about learned patterns.
     */
    public static function getStats(): array
    {
        return [
            'total_patterns' => self::count(),
            'manual_overrides' => self::where('is_manual_override', true)->count(),
            'openai_learned' => self::where('source', 'openai')->count(),
            'high_confidence' => self::where('confidence_score', '>=', 80)->count(),
            'credit_patterns' => self::where('transaction_type', 'credit')->count(),
            'debit_patterns' => self::where('transaction_type', 'debit')->count(),
        ];
    }
}
