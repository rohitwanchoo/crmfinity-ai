<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyzedTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_session_id',
        'transaction_date',
        'description',
        'description_normalized',
        'amount',
        'beginning_balance',
        'ending_balance',
        'type',
        'original_type',
        'was_corrected',
        'exclude_from_revenue',
        'exclusion_reason',
        'confidence',
        'confidence_label',
        'category',
        'merchant_name',
        'is_mca_payment',
        'mca_lender_id',
        'mca_lender_name',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'beginning_balance' => 'decimal:2',
        'ending_balance' => 'decimal:2',
        'confidence' => 'decimal:4',
        'was_corrected' => 'boolean',
        'exclude_from_revenue' => 'boolean',
        'is_mca_payment' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(AnalysisSession::class, 'analysis_session_id');
    }

    /**
     * Normalize description for pattern matching
     */
    public static function normalizeDescription(string $description): string
    {
        // Remove dates
        $normalized = preg_replace('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', '', $description);
        // Replace numbers with placeholder
        $normalized = preg_replace('/\d+/', '#', $normalized);
        // Clean up whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Extract merchant name from description
     */
    public static function extractMerchant(string $description): ?string
    {
        // Common patterns to extract merchant names
        $patterns = [
            '/^([A-Z][A-Z0-9\s&\'\-\.]+?)(?:\s+#|\s+\d|$)/i',
            '/(?:POS|PURCHASE|DEBIT)\s+([A-Z][A-Z0-9\s&\'\-\.]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                return trim($matches[1]);
            }
        }

        // Fallback: first few words
        $words = explode(' ', $description);

        return implode(' ', array_slice($words, 0, 3));
    }

    /**
     * Create transactions from analysis results
     * Returns array of created transaction IDs mapped to original index
     */
    public static function createFromAnalysis(int $sessionId, array $transactions): array
    {
        $createdIds = [];

        foreach ($transactions as $index => $txn) {
            $created = self::create([
                'analysis_session_id' => $sessionId,
                'transaction_date' => $txn['date'],
                'description' => $txn['description'],
                'description_normalized' => self::normalizeDescription($txn['description']),
                'amount' => $txn['amount'],
                'type' => $txn['type'],
                'original_type' => $txn['type'],
                'was_corrected' => $txn['corrected'] ?? false,
                'confidence' => $txn['confidence'] ?? 0.8,
                'confidence_label' => $txn['confidence_label'] ?? 'medium',
                'merchant_name' => self::extractMerchant($txn['description']),
            ]);

            $createdIds[$index] = $created->id;
        }

        return $createdIds;
    }

    /**
     * Get common patterns for learning
     */
    public static function getLearnedPatterns(): array
    {
        // Get patterns from corrected transactions
        return self::where('was_corrected', true)
            ->select('description_normalized', 'type as correct_type')
            ->distinct()
            ->limit(100)
            ->get()
            ->toArray();
    }

    /**
     * Get transaction statistics by type
     */
    public static function getTypeStatistics(): array
    {
        return [
            'total' => self::count(),
            'credits' => self::where('type', 'credit')->count(),
            'debits' => self::where('type', 'debit')->count(),
            'corrected' => self::where('was_corrected', true)->count(),
        ];
    }
}
