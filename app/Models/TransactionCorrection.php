<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description_pattern',
        'original_type',
        'correct_type',
        'amount',
        'usage_count',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'usage_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get learned patterns for AI training
     */
    public static function getLearnedPatterns(): array
    {
        return self::select('description_pattern', 'correct_type')
            ->orderBy('usage_count', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    /**
     * Record a correction or update existing one
     */
    public static function recordCorrection(string $description, string $originalType, string $correctType, ?float $amount = null, ?int $userId = null): self
    {
        // Normalize description for pattern matching
        $pattern = self::normalizePattern($description);

        $correction = self::where('description_pattern', $pattern)
            ->where('correct_type', $correctType)
            ->first();

        if ($correction) {
            $correction->increment('usage_count');

            return $correction;
        }

        return self::create([
            'user_id' => $userId,
            'description_pattern' => $pattern,
            'original_type' => $originalType,
            'correct_type' => $correctType,
            'amount' => $amount,
            'usage_count' => 1,
        ]);
    }

    /**
     * Normalize description to create a reusable pattern
     */
    public static function normalizePattern(string $description): string
    {
        // Remove dates and specific numbers but keep key identifiers
        $pattern = preg_replace('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', '', $description);
        $pattern = preg_replace('/\d+/', '#', $pattern);
        $pattern = preg_replace('/\s+/', ' ', $pattern);

        return trim($pattern);
    }
}
