<?php

namespace App\Services;

use App\Models\BankLayoutPattern;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BankPatternService
{
    /**
     * Cache duration in seconds (1 hour)
     */
    private const CACHE_DURATION = 3600;

    /**
     * Get all US bank patterns from database
     */
    public function getAllPatterns(): array
    {
        return Cache::remember('us_bank_patterns', self::CACHE_DURATION, function () {
            return BankLayoutPattern::all()->keyBy('bank_name')->toArray();
        });
    }

    /**
     * Detect which bank a statement belongs to based on text content
     */
    public function detectBank(string $text): ?BankLayoutPattern
    {
        $patterns = BankLayoutPattern::all();
        $bestMatch = null;
        $highestScore = 0;

        foreach ($patterns as $pattern) {
            $score = $this->calculateMatchScore($text, $pattern);

            // Increased minimum score from 2 to 4 to reduce false positives
            // A score of 4 typically means at least 1 header match (3 pts) + 1 marker
            if ($score > $highestScore && $score >= 4) {
                $highestScore = $score;
                $bestMatch = $pattern;
            }
        }

        if ($bestMatch) {
            Log::info("BankPatternService: Detected bank '{$bestMatch->bank_name}' with score {$highestScore}");
            $bestMatch->incrementStatementCount();
        }

        return $bestMatch;
    }

    /**
     * Calculate match score for a bank pattern against text
     *
     * Scoring weights (prioritizing header detection):
     * - Header patterns: 3 points each (most reliable - bank name/URL in header)
     * - Footer patterns: 2 points each (moderately reliable)
     * - Transaction markers: 1 point each (least reliable - can appear in any statement)
     */
    private function calculateMatchScore(string $text, BankLayoutPattern $pattern): int
    {
        $score = 0;
        $textLower = strtolower($text);

        // Check header patterns (highest weight - bank name/URL are most reliable)
        $headerPatterns = $pattern->header_patterns ?? [];
        foreach ($headerPatterns as $headerPattern) {
            if (stripos($textLower, strtolower($headerPattern)) !== false) {
                $score += 3;
            }
        }

        // Check footer patterns (medium weight)
        $footerPatterns = $pattern->footer_patterns ?? [];
        foreach ($footerPatterns as $footerPattern) {
            if (stripos($textLower, strtolower($footerPattern)) !== false) {
                $score += 2;
            }
        }

        // Check transaction markers (lowest weight - these can appear in any statement)
        $transactionMarkers = $pattern->transaction_markers ?? [];
        foreach ($transactionMarkers as $marker) {
            if (stripos($textLower, strtolower($marker)) !== false) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * Get bank-specific AI prompt rules
     */
    public function getBankSpecificPrompt(?BankLayoutPattern $pattern): string
    {
        if (! $pattern) {
            return '';
        }

        return $pattern->getAIPromptRules();
    }

    /**
     * Get extraction rules for a specific bank
     */
    public function getExtractionRules(?BankLayoutPattern $pattern): array
    {
        if (! $pattern) {
            return $this->getDefaultExtractionRules();
        }

        return array_merge(
            $this->getDefaultExtractionRules(),
            $pattern->extraction_rules ?? []
        );
    }

    /**
     * Get default extraction rules when bank is not detected
     */
    private function getDefaultExtractionRules(): array
    {
        return [
            'debit_indicators' => [
                'PURCHASE', 'DEBIT', 'WITHDRAWAL', 'ATM', 'CHECK', 'PAYMENT',
                'TRANSFER TO', 'FEE', 'CHARGE', 'POS', 'WIRE OUT', 'SENT',
                'authorized on', 'AUTOPAY', 'BILL PAY',
            ],
            'credit_indicators' => [
                'DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'REFUND',
                'DIRECT DEP', 'PAYROLL', 'RECEIVED', 'CASHBACK', 'DIVIDEND',
                'MOBILE DEPOSIT', 'WIRE IN',
            ],
            'check_format' => 'CHECK # followed by number',
            'date_format' => 'MM/DD/YYYY',
        ];
    }

    /**
     * Build complete bank-specific context for AI parsing
     */
    public function buildAIContext(string $text): array
    {
        $detectedBank = $this->detectBank($text);
        $extractionRules = $this->getExtractionRules($detectedBank);
        $bankPrompt = $this->getBankSpecificPrompt($detectedBank);

        return [
            'detected_bank' => $detectedBank?->bank_name ?? 'Unknown',
            'bank_id' => $detectedBank?->id,
            'date_format' => $detectedBank?->date_format ?? 'MM/DD/YYYY',
            'has_separate_columns' => $detectedBank?->column_structure['separate_debit_credit'] ?? false,
            'extraction_rules' => $extractionRules,
            'bank_prompt' => $bankPrompt,
            'column_structure' => $detectedBank?->column_structure ?? [],
        ];
    }

    /**
     * Get all bank names for UI dropdown
     */
    public function getBankNames(): array
    {
        return BankLayoutPattern::orderBy('bank_name')
            ->pluck('bank_name')
            ->toArray();
    }

    /**
     * Record feedback for a bank pattern (correct/incorrect detection)
     */
    public function recordFeedback(int $patternId, bool $wasCorrect): void
    {
        $pattern = BankLayoutPattern::find($patternId);

        if ($pattern) {
            $pattern->updateAccuracy($wasCorrect);
        }
    }

    /**
     * Clear cached patterns (call after seeding or updates)
     */
    public function clearCache(): void
    {
        Cache::forget('us_bank_patterns');
    }

    /**
     * Get pattern by bank name
     */
    public function getPatternByName(string $bankName): ?BankLayoutPattern
    {
        return BankLayoutPattern::where('bank_name', $bankName)->first();
    }

    /**
     * Get all patterns with statistics
     */
    public function getPatternsWithStats(): array
    {
        return BankLayoutPattern::orderBy('total_statements_seen', 'desc')
            ->get()
            ->map(function ($pattern) {
                return [
                    'id' => $pattern->id,
                    'bank_name' => $pattern->bank_name,
                    'total_statements' => $pattern->total_statements_seen,
                    'accuracy_rate' => $pattern->accuracy_rate ? round($pattern->accuracy_rate * 100, 1).'%' : 'N/A',
                    'date_format' => $pattern->date_format,
                    'has_separate_columns' => $pattern->column_structure['separate_debit_credit'] ?? false,
                ];
            })
            ->toArray();
    }

    /**
     * Get debit indicators for a bank (for Python script)
     */
    public function getDebitIndicators(?BankLayoutPattern $pattern): array
    {
        $rules = $this->getExtractionRules($pattern);

        return $rules['debit_indicators'] ?? [];
    }

    /**
     * Get credit indicators for a bank (for Python script)
     */
    public function getCreditIndicators(?BankLayoutPattern $pattern): array
    {
        $rules = $this->getExtractionRules($pattern);

        return $rules['credit_indicators'] ?? [];
    }
}
