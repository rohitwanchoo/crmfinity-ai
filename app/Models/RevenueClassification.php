<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'description_pattern',
        'classification',
        'adjustment_reason',
        'is_mca_funding',
        'mca_lender_id',
        'mca_lender_name',
        'usage_count',
        'user_id',
    ];

    protected $casts = [
        'is_mca_funding' => 'boolean',
    ];

    /**
     * Normalize a description into a pattern for matching.
     */
    public static function normalizePattern(string $description): string
    {
        // Remove dates
        $normalized = preg_replace('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', '', $description);
        // Remove specific numbers but keep general structure
        $normalized = preg_replace('/\d{6,}/', '#ID#', $normalized); // Account/reference numbers
        $normalized = preg_replace('/\$[\d,]+\.?\d*/', '', $normalized); // Dollar amounts
        // Clean up whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    /**
     * Record a revenue classification correction.
     */
    public static function recordClassification(
        string $description,
        string $classification,
        ?string $adjustmentReason = null,
        ?int $userId = null,
        bool $isMcaFunding = false,
        ?string $mcaLenderId = null,
        ?string $mcaLenderName = null
    ): self {
        $pattern = self::normalizePattern($description);

        $existing = self::where('description_pattern', $pattern)->first();

        if ($existing) {
            $existing->update([
                'classification' => $classification,
                'adjustment_reason' => $adjustmentReason,
                'is_mca_funding' => $isMcaFunding,
                'mca_lender_id' => $mcaLenderId,
                'mca_lender_name' => $mcaLenderName,
                'usage_count' => $existing->usage_count + 1,
            ]);
            return $existing;
        }

        return self::create([
            'description_pattern' => $pattern,
            'classification' => $classification,
            'adjustment_reason' => $adjustmentReason,
            'is_mca_funding' => $isMcaFunding,
            'mca_lender_id' => $mcaLenderId,
            'mca_lender_name' => $mcaLenderName,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get learned patterns for AI prompting.
     */
    public static function getLearnedPatterns(): array
    {
        return self::orderBy('usage_count', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($item) {
                return [
                    'pattern' => $item->description_pattern,
                    'classification' => $item->classification,
                    'reason' => $item->adjustment_reason,
                ];
            })
            ->toArray();
    }

    /**
     * Check if a description matches a learned adjustment pattern.
     */
    public static function isAdjustment(string $description): ?array
    {
        $pattern = self::normalizePattern($description);
        $patternLower = strtolower($pattern);

        $match = self::whereRaw('LOWER(description_pattern) LIKE ?', ['%' . strtolower($patternLower) . '%'])
            ->orWhereRaw('? LIKE CONCAT("%", LOWER(description_pattern), "%")', [$patternLower])
            ->orderBy('usage_count', 'desc')
            ->first();

        if ($match) {
            return [
                'classification' => $match->classification,
                'reason' => $match->adjustment_reason,
            ];
        }

        return null;
    }

    /**
     * Get classification for a description using learned patterns.
     * Returns 'true_revenue', 'adjustment', or null if no match.
     */
    public static function getClassification(string $description): ?string
    {
        $result = self::getFullClassification($description);
        return $result ? $result['classification'] : null;
    }

    /**
     * Get full classification info for a description using learned patterns.
     * Returns array with classification, MCA funding info, etc. or null if no match.
     */
    public static function getFullClassification(string $description): ?array
    {
        $pattern = self::normalizePattern($description);
        $patternLower = strtolower($pattern);

        // Get all learned patterns ordered by usage count
        $learnedPatterns = self::orderBy('usage_count', 'desc')->get();

        foreach ($learnedPatterns as $learned) {
            $learnedPatternLower = strtolower($learned->description_pattern);
            $matched = false;

            // Check for various matching strategies
            // 1. Exact normalized pattern match
            if ($patternLower === $learnedPatternLower) {
                $matched = true;
            }

            // 2. Learned pattern is contained in description
            if (!$matched && !empty($learnedPatternLower) && stripos($patternLower, $learnedPatternLower) !== false) {
                $matched = true;
            }

            // 3. Description is contained in learned pattern
            if (!$matched && !empty($patternLower) && stripos($learnedPatternLower, $patternLower) !== false) {
                $matched = true;
            }

            // 4. Check key words match (for partial matches)
            if (!$matched) {
                $learnedWords = array_filter(explode(' ', $learnedPatternLower), fn($w) => strlen($w) > 3);

                if (count($learnedWords) >= 2) {
                    $matchCount = 0;
                    foreach ($learnedWords as $word) {
                        if (stripos($patternLower, $word) !== false) {
                            $matchCount++;
                        }
                    }
                    // If more than 60% of significant words match
                    if ($matchCount >= ceil(count($learnedWords) * 0.6)) {
                        $matched = true;
                    }
                }
            }

            if ($matched) {
                return [
                    'classification' => $learned->classification,
                    'adjustment_reason' => $learned->adjustment_reason,
                    'is_mca_funding' => (bool) $learned->is_mca_funding,
                    'mca_lender_id' => $learned->mca_lender_id,
                    'mca_lender_name' => $learned->mca_lender_name,
                ];
            }
        }

        return null;
    }
}
