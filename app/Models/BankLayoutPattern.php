<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankLayoutPattern extends Model
{
    protected $fillable = [
        'bank_name',
        'layout_version',
        'date_format',
        'transaction_markers',
        'header_patterns',
        'footer_patterns',
        'column_structure',
        'extraction_rules',
        'total_statements_seen',
        'accuracy_rate',
    ];

    protected $casts = [
        'transaction_markers' => 'array',
        'header_patterns' => 'array',
        'footer_patterns' => 'array',
        'column_structure' => 'array',
        'extraction_rules' => 'array',
        'accuracy_rate' => 'decimal:4',
    ];

    /**
     * Get all US bank patterns
     */
    public static function getUSBankPatterns(): array
    {
        return self::where('layout_version', 'default')
            ->orderBy('bank_name')
            ->get()
            ->keyBy('bank_name')
            ->toArray();
    }

    /**
     * Find matching bank pattern from statement text
     */
    public static function detectBank(string $text): ?self
    {
        $patterns = self::all();

        foreach ($patterns as $pattern) {
            $headerPatterns = $pattern->header_patterns ?? [];
            $matchScore = 0;

            foreach ($headerPatterns as $headerPattern) {
                if (stripos($text, $headerPattern) !== false) {
                    $matchScore++;
                }
            }

            // Require at least 2 header pattern matches for confidence
            if ($matchScore >= 2) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Get bank-specific extraction rules for AI prompt
     */
    public function getAIPromptRules(): string
    {
        $rules = [];

        $rules[] = "=== BANK-SPECIFIC RULES FOR {$this->bank_name} ===";

        if ($this->date_format) {
            $rules[] = "Date format: {$this->date_format}";
        }

        if (! empty($this->column_structure)) {
            $rules[] = 'Column structure: '.json_encode($this->column_structure);
        }

        if (! empty($this->extraction_rules)) {
            foreach ($this->extraction_rules as $key => $rule) {
                if (is_array($rule)) {
                    $rules[] = "{$key}: ".implode(', ', $rule);
                } else {
                    $rules[] = "{$key}: {$rule}";
                }
            }
        }

        if (! empty($this->transaction_markers)) {
            $rules[] = 'Transaction markers: '.implode(', ', $this->transaction_markers);
        }

        return implode("\n", $rules);
    }

    /**
     * Increment statement count for learning
     */
    public function incrementStatementCount(): void
    {
        $this->increment('total_statements_seen');
    }

    /**
     * Update accuracy rate based on correction feedback
     */
    public function updateAccuracy(bool $wasCorrect): void
    {
        $currentRate = $this->accuracy_rate ?? 0.8;
        $count = $this->total_statements_seen;

        // Weighted moving average
        $newRate = (($currentRate * ($count - 1)) + ($wasCorrect ? 1 : 0)) / $count;

        $this->update(['accuracy_rate' => $newRate]);
    }
}
