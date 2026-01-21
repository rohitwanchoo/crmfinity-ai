<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Confidence Scoring Service for transaction extraction
 * Provides detailed confidence scores based on multiple factors
 * Part of PR3: Advanced Features - Confidence Scoring System
 */
class ConfidenceScoringService
{
    // Weight factors for confidence calculation
    private const WEIGHT_KEYWORD_MATCH = 0.25;
    private const WEIGHT_COLUMN_POSITION = 0.25;
    private const WEIGHT_AMOUNT_FORMAT = 0.15;
    private const WEIGHT_DATE_FORMAT = 0.10;
    private const WEIGHT_DESCRIPTION_QUALITY = 0.10;
    private const WEIGHT_BALANCE_VERIFICATION = 0.15;

    // Confidence thresholds
    private const THRESHOLD_HIGH = 0.85;
    private const THRESHOLD_MEDIUM = 0.60;

    /**
     * Calculate confidence score for a transaction
     *
     * @param array $transaction The transaction to score
     * @param array $context Extraction context (section, column, balance changes)
     * @return array Transaction with detailed confidence scoring
     */
    public function calculateConfidence(array $transaction, array $context = []): array
    {
        $scores = [
            'keyword_score' => $this->scoreKeywordMatch($transaction),
            'column_score' => $this->scoreColumnPosition($transaction, $context),
            'amount_score' => $this->scoreAmountFormat($transaction),
            'date_score' => $this->scoreDateFormat($transaction),
            'description_score' => $this->scoreDescriptionQuality($transaction),
            'balance_score' => $this->scoreBalanceVerification($transaction, $context),
        ];

        // Calculate weighted total
        $totalScore =
            ($scores['keyword_score'] * self::WEIGHT_KEYWORD_MATCH) +
            ($scores['column_score'] * self::WEIGHT_COLUMN_POSITION) +
            ($scores['amount_score'] * self::WEIGHT_AMOUNT_FORMAT) +
            ($scores['date_score'] * self::WEIGHT_DATE_FORMAT) +
            ($scores['description_score'] * self::WEIGHT_DESCRIPTION_QUALITY) +
            ($scores['balance_score'] * self::WEIGHT_BALANCE_VERIFICATION);

        // Determine confidence level
        if ($totalScore >= self::THRESHOLD_HIGH) {
            $confidenceLevel = 'high';
        } elseif ($totalScore >= self::THRESHOLD_MEDIUM) {
            $confidenceLevel = 'medium';
        } else {
            $confidenceLevel = 'low';
        }

        return array_merge($transaction, [
            'confidence' => $confidenceLevel,
            'confidence_score' => round($totalScore, 3),
            'confidence_breakdown' => $scores,
        ]);
    }

    /**
     * Batch calculate confidence for multiple transactions
     */
    public function calculateBatchConfidence(array $transactions, array $context = []): array
    {
        $result = [];
        $prevBalance = $context['starting_balance'] ?? null;

        foreach ($transactions as $idx => $txn) {
            $txnContext = $context;

            // Add balance tracking if available
            if ($prevBalance !== null && isset($txn['amount'])) {
                $txnContext['previous_balance'] = $prevBalance;
                $expectedNewBalance = $txn['type'] === 'credit'
                    ? $prevBalance + $txn['amount']
                    : $prevBalance - $txn['amount'];
                $txnContext['expected_balance'] = $expectedNewBalance;
                $prevBalance = $expectedNewBalance;
            }

            $result[] = $this->calculateConfidence($txn, $txnContext);
        }

        return $result;
    }

    /**
     * Score based on keyword matching for credit/debit classification
     */
    private function scoreKeywordMatch(array $transaction): float
    {
        $description = strtoupper($transaction['description'] ?? '');
        $type = $transaction['type'] ?? 'debit';

        // Strong credit indicators
        $creditKeywords = [
            'DEPOSIT' => 1.0,
            'DIRECT DEP' => 1.0,
            'PAYROLL' => 1.0,
            'CREDIT' => 0.9,
            'REFUND' => 0.95,
            'RETURN' => 0.85,
            'REVERSAL' => 0.9,
            'INTEREST' => 0.95,
            'DIVIDEND' => 0.95,
            'FROM ' => 0.8,
            ' FROM' => 0.8,
            'RECEIVED' => 0.85,
            'INCOMING' => 0.9,
            'TRANSFER IN' => 0.9,
            'WIRE IN' => 0.9,
            'ACH CREDIT' => 0.95,
            'MOBILE DEPOSIT' => 0.95,
            'CHECK DEPOSIT' => 0.95,
            'ZELLE FROM' => 0.95,
            'VENMO FROM' => 0.95,
        ];

        // Strong debit indicators
        $debitKeywords = [
            'PURCHASE' => 1.0,
            'WITHDRAWAL' => 1.0,
            'DEBIT' => 0.9,
            'POS ' => 0.95,
            'ATM ' => 0.95,
            'CHECK #' => 0.95,
            'CHK ' => 0.95,
            'FEE' => 0.95,
            'CHARGE' => 0.9,
            'PAYMENT' => 0.8,
            'TO ' => 0.75,
            ' TO' => 0.75,
            'SENT' => 0.85,
            'TRANSFER OUT' => 0.9,
            'WIRE OUT' => 0.9,
            'ACH DEBIT' => 0.95,
            'AUTOPAY' => 0.9,
            'BILL PAY' => 0.9,
            'LOAN' => 0.85,
            'ZELLE TO' => 0.95,
            'VENMO TO' => 0.95,
            'AMAZON' => 0.8,
            'WALMART' => 0.8,
            'TARGET' => 0.8,
        ];

        $matchScore = 0.5; // Default neutral score
        $matchedKeyword = null;

        // Check for keyword matches
        foreach ($creditKeywords as $keyword => $weight) {
            if (strpos($description, $keyword) !== false) {
                if ($type === 'credit') {
                    $matchScore = max($matchScore, $weight);
                    $matchedKeyword = $keyword;
                } else {
                    // Type mismatch - lower confidence
                    $matchScore = min($matchScore, 1 - $weight);
                }
            }
        }

        foreach ($debitKeywords as $keyword => $weight) {
            if (strpos($description, $keyword) !== false) {
                if ($type === 'debit') {
                    $matchScore = max($matchScore, $weight);
                    $matchedKeyword = $keyword;
                } else {
                    // Type mismatch - lower confidence
                    $matchScore = min($matchScore, 1 - $weight);
                }
            }
        }

        return $matchScore;
    }

    /**
     * Score based on column/section position
     */
    private function scoreColumnPosition(array $transaction, array $context): float
    {
        $section = strtoupper($context['section'] ?? '');
        $column = strtoupper($context['column'] ?? '');
        $type = $transaction['type'] ?? 'debit';

        // Check section header
        $creditSections = ['DEPOSIT', 'CREDIT', 'ADDITION', 'INCOMING'];
        $debitSections = ['WITHDRAWAL', 'DEBIT', 'CHECK', 'PAYMENT', 'SUBTRACTION'];

        foreach ($creditSections as $keyword) {
            if (strpos($section, $keyword) !== false || strpos($column, $keyword) !== false) {
                return $type === 'credit' ? 1.0 : 0.2;
            }
        }

        foreach ($debitSections as $keyword) {
            if (strpos($section, $keyword) !== false || strpos($column, $keyword) !== false) {
                return $type === 'debit' ? 1.0 : 0.2;
            }
        }

        // No clear section/column context
        return 0.5;
    }

    /**
     * Score based on amount format quality
     */
    private function scoreAmountFormat(array $transaction): float
    {
        $amount = $transaction['amount'] ?? null;

        if ($amount === null) {
            return 0.0;
        }

        if (!is_numeric($amount)) {
            return 0.2;
        }

        $amount = (float) $amount;

        // Check for valid amount
        if ($amount <= 0) {
            return 0.3;
        }

        // Check for reasonable precision (2 decimal places)
        $decimals = strlen(substr(strrchr((string) $amount, "."), 1));
        if ($decimals > 2) {
            return 0.7;
        }

        // Check for suspiciously round numbers (might be placeholders)
        if ($amount == round($amount, -2) && $amount >= 1000) {
            return 0.8; // Slightly lower confidence for very round numbers
        }

        return 1.0;
    }

    /**
     * Score based on date format quality
     */
    private function scoreDateFormat(array $transaction): float
    {
        $date = $transaction['date'] ?? null;

        if (!$date) {
            return 0.0;
        }

        // Check for YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            // Validate it's a real date
            $parts = explode('-', $date);
            if (checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
                // Check if date is reasonable (within last 2 years)
                $txnDate = strtotime($date);
                $twoYearsAgo = strtotime('-2 years');
                $tomorrow = strtotime('+1 day');

                if ($txnDate >= $twoYearsAgo && $txnDate <= $tomorrow) {
                    return 1.0;
                }
                return 0.7; // Valid format but unusual date
            }
        }

        // Partial match - some date info
        if (preg_match('/\d{2}[-\/]\d{2}/', $date)) {
            return 0.6;
        }

        return 0.3;
    }

    /**
     * Score based on description quality
     */
    private function scoreDescriptionQuality(array $transaction): float
    {
        $description = $transaction['description'] ?? '';

        if (empty($description)) {
            return 0.0;
        }

        $length = strlen($description);

        // Too short - likely incomplete
        if ($length < 5) {
            return 0.3;
        }

        // Reasonable length
        if ($length >= 10 && $length <= 200) {
            $score = 0.8;

            // Bonus for containing recognizable patterns
            if (preg_match('/[A-Z]{2,}/', $description)) {
                $score += 0.1; // Contains uppercase words
            }

            if (preg_match('/\d+/', $description)) {
                $score += 0.1; // Contains numbers (like check numbers, IDs)
            }

            return min($score, 1.0);
        }

        // Very long - might include garbage
        if ($length > 200) {
            return 0.6;
        }

        return 0.7;
    }

    /**
     * Score based on balance verification
     */
    private function scoreBalanceVerification(array $transaction, array $context): float
    {
        $actualBalance = $context['actual_balance'] ?? null;
        $expectedBalance = $context['expected_balance'] ?? null;

        if ($actualBalance === null || $expectedBalance === null) {
            return 0.5; // No balance info to verify
        }

        $diff = abs($actualBalance - $expectedBalance);

        // Perfect match
        if ($diff < 0.01) {
            return 1.0;
        }

        // Small rounding error
        if ($diff < 1.00) {
            return 0.9;
        }

        // Moderate mismatch - might be wrong type
        if ($diff < 100) {
            return 0.5;
        }

        // Large mismatch - likely wrong type
        return 0.2;
    }

    /**
     * Get confidence statistics for a batch of transactions
     */
    public function getConfidenceStats(array $transactions): array
    {
        $high = 0;
        $medium = 0;
        $low = 0;
        $totalScore = 0;

        foreach ($transactions as $txn) {
            $confidence = $txn['confidence'] ?? 'medium';
            $score = $txn['confidence_score'] ?? 0.5;

            switch ($confidence) {
                case 'high':
                    $high++;
                    break;
                case 'medium':
                    $medium++;
                    break;
                case 'low':
                    $low++;
                    break;
            }

            $totalScore += $score;
        }

        $total = count($transactions);

        return [
            'total' => $total,
            'high_confidence' => $high,
            'medium_confidence' => $medium,
            'low_confidence' => $low,
            'high_confidence_percent' => $total > 0 ? round(($high / $total) * 100, 1) : 0,
            'average_score' => $total > 0 ? round($totalScore / $total, 3) : 0,
            'needs_review' => $low,
        ];
    }
}
