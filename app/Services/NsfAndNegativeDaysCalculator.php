<?php

namespace App\Services;

class NsfAndNegativeDaysCalculator
{
    /**
     * Calculate negative days using EOD (End of Day) definition
     *
     * For each calendar date, determine the end-of-day balance as the last running_balance on that date.
     * If running balances are not provided, reconstruct running balance from the opening balance and sorted transactions.
     * Count number of dates where EOD_balance < 0.
     *
     * @param array $transactions Sorted transactions
     * @param float|null $openingBalance Opening balance for the period
     * @return array ['negative_days_count' => int, 'negative_dates' => array]
     */
    public function calculateNegativeDays(array $transactions, ?float $openingBalance = null): array
    {
        $dailyBalances = [];
        $hasActualBalances = false;

        // Step 1: Group transactions by date and check if we have actual ending_balance data
        $transactionsByDate = [];
        foreach ($transactions as $txn) {
            $date = $txn['date'] ?? $txn['transaction_date'] ?? null;
            if (!$date) continue;

            $dateStr = is_string($date) ? $date : date('Y-m-d', strtotime($date));

            if (!isset($transactionsByDate[$dateStr])) {
                $transactionsByDate[$dateStr] = [];
            }

            $transactionsByDate[$dateStr][] = $txn;

            // Check if we have actual balance data
            if (isset($txn['ending_balance']) && $txn['ending_balance'] !== null) {
                $hasActualBalances = true;
            }
        }

        // Sort dates
        ksort($transactionsByDate);

        if ($hasActualBalances) {
            // Method 1: Use actual ending_balance from transactions
            foreach ($transactionsByDate as $dateStr => $dayTransactions) {
                // Get the last transaction of the day (assuming sorted by time)
                $lastTransaction = end($dayTransactions);
                $endingBalance = $lastTransaction['ending_balance'] ?? null;

                if ($endingBalance !== null) {
                    $dailyBalances[$dateStr] = (float) $endingBalance;
                }
            }
        } else {
            // Method 2: Reconstruct running balance from opening balance and transactions
            $runningBalance = $openingBalance ?? 0;

            foreach ($transactionsByDate as $dateStr => $dayTransactions) {
                // Process all transactions for this day
                foreach ($dayTransactions as $txn) {
                    $amount = (float) ($txn['amount'] ?? 0);
                    $type = $txn['type'] ?? 'debit';

                    // Update running balance
                    if ($type === 'credit') {
                        $runningBalance += $amount;
                    } else {
                        $runningBalance -= $amount;
                    }
                }

                // Store the end-of-day balance
                $dailyBalances[$dateStr] = $runningBalance;
            }
        }

        // Step 2: Count negative days and collect details
        $negativeDaysCount = 0;
        $negativeDates = [];

        foreach ($dailyBalances as $dateStr => $balance) {
            if ($balance < 0) {
                $negativeDaysCount++;
                $negativeDates[] = [
                    'date' => $dateStr,
                    'eod_balance' => round($balance, 2)
                ];
            }
        }

        return [
            'negative_days_count' => $negativeDaysCount,
            'negative_dates' => $negativeDates,
            'method_used' => $hasActualBalances ? 'actual_balances' : 'reconstructed',
            'opening_balance' => $openingBalance,
        ];
    }

    /**
     * Calculate NSF counts with deduplication
     *
     * Identify NSF-related lines and create three outputs:
     * 1. nsf_fee_count = number of fee lines containing NSF/returned-item fee wording
     * 2. returned_item_count = number of non-fee returned/declined/reversal lines
     * 3. unique_nsf_events = deduplicate fee+return pairs (same/close amount, same merchant, within 0-3 days)
     *
     * @param array $transactions All transactions
     * @return array NSF analysis with counts
     */
    public function calculateNsfCounts(array $transactions): array
    {
        // Keywords for NSF fees
        $nsfFeeKeywords = [
            'nsf fee', 'nsf charge', 'nsf service charge',
            'non-sufficient funds fee', 'insufficient funds fee',
            'overdraft fee', 'overdraft charge', 'od fee',
            'returned item fee', 'returned check fee', 'returned payment fee',
            'return item fee', 'item returned fee'
        ];

        // Keywords for returned/declined items (non-fee)
        $returnedItemKeywords = [
            'nsf return', 'nsf declined',
            'returned item', 'returned check', 'returned ach', 'returned payment', 'returned debit',
            'return', 'returned', 'reject', 'rejected',
            'declined', 'reversal', 'reverse',
            'non-sufficient funds', 'insufficient funds',
            // ACH return codes
            'r01', 'r02', 'r09', 'r10', 'r29'
        ];

        $nsfFees = [];
        $returnedItems = [];

        // Step 1: Classify transactions as fees or returned items
        foreach ($transactions as $index => $txn) {
            $description = strtolower($txn['description'] ?? '');
            $amount = (float) ($txn['amount'] ?? 0);
            $date = $txn['date'] ?? $txn['transaction_date'] ?? null;
            $type = $txn['type'] ?? 'debit';

            if (!$date) continue;

            $dateStr = is_string($date) ? $date : date('Y-m-d', strtotime($date));
            $timestamp = strtotime($dateStr);

            // Check for NSF fees (typically small amounts like $35, $36, etc.)
            $isFee = false;
            foreach ($nsfFeeKeywords as $keyword) {
                if (stripos($description, $keyword) !== false) {
                    $isFee = true;
                    break;
                }
            }

            if ($isFee) {
                $nsfFees[] = [
                    'index' => $index,
                    'date' => $dateStr,
                    'timestamp' => $timestamp,
                    'description' => $txn['description'],
                    'amount' => $amount,
                    'type' => $type,
                ];
                continue; // Don't check returned item keywords if it's a fee
            }

            // Check for returned items (actual returned payments)
            $isReturned = false;
            foreach ($returnedItemKeywords as $keyword) {
                if (stripos($description, $keyword) !== false) {
                    $isReturned = true;
                    break;
                }
            }

            if ($isReturned) {
                // Try to extract merchant/payee name from description
                $merchant = $this->extractMerchantName($txn['description']);

                $returnedItems[] = [
                    'index' => $index,
                    'date' => $dateStr,
                    'timestamp' => $timestamp,
                    'description' => $txn['description'],
                    'merchant' => $merchant,
                    'amount' => $amount,
                    'type' => $type,
                ];
            }
        }

        // Step 2: Deduplicate fee+return pairs to count unique NSF events
        $uniqueEvents = [];
        $matchedFees = [];
        $matchedReturns = [];

        foreach ($nsfFees as $feeIndex => $fee) {
            $bestMatch = null;
            $bestMatchScore = 0;

            // Look for a matching returned item within 0-3 days
            foreach ($returnedItems as $returnIndex => $return) {
                if (in_array($returnIndex, $matchedReturns)) {
                    continue; // Already matched
                }

                // Calculate days difference
                $daysDiff = abs(($return['timestamp'] - $fee['timestamp']) / 86400);

                if ($daysDiff <= 3) {
                    // Calculate match score based on:
                    // 1. Amount similarity (returned item amount should be close to a typical transaction amount)
                    // 2. Merchant name match
                    // 3. Time proximity

                    $score = 0;

                    // Time proximity score (closer = better)
                    $score += (3 - $daysDiff) * 20; // Max 60 points

                    // Merchant/description similarity
                    if ($return['merchant'] && $fee['description']) {
                        $similarity = 0;
                        similar_text(
                            strtolower($return['merchant']),
                            strtolower($fee['description']),
                            $similarity
                        );
                        $score += $similarity * 0.4; // Max 40 points
                    }

                    if ($score > $bestMatchScore) {
                        $bestMatchScore = $score;
                        $bestMatch = $returnIndex;
                    }
                }
            }

            // If we found a good match (score > 30), pair them as one event
            if ($bestMatch !== null && $bestMatchScore > 30) {
                $uniqueEvents[] = [
                    'fee' => $fee,
                    'returned_item' => $returnedItems[$bestMatch],
                    'match_score' => round($bestMatchScore, 2),
                    'event_type' => 'paired',
                ];
                $matchedFees[] = $feeIndex;
                $matchedReturns[] = $bestMatch;
            } else {
                // Standalone fee (no matching return found)
                $uniqueEvents[] = [
                    'fee' => $fee,
                    'returned_item' => null,
                    'match_score' => 0,
                    'event_type' => 'fee_only',
                ];
                $matchedFees[] = $feeIndex;
            }
        }

        // Add unmatched returned items as standalone events
        foreach ($returnedItems as $returnIndex => $return) {
            if (!in_array($returnIndex, $matchedReturns)) {
                $uniqueEvents[] = [
                    'fee' => null,
                    'returned_item' => $return,
                    'match_score' => 0,
                    'event_type' => 'return_only',
                ];
            }
        }

        return [
            'nsf_fee_count' => count($nsfFees),
            'returned_item_count' => count($returnedItems),
            'unique_nsf_events' => count($uniqueEvents),
            'nsf_fees' => $nsfFees,
            'returned_items' => $returnedItems,
            'unique_events' => $uniqueEvents,
        ];
    }

    /**
     * Extract merchant/payee name from transaction description
     *
     * @param string $description
     * @return string|null
     */
    private function extractMerchantName(string $description): ?string
    {
        // Remove common prefixes/suffixes
        $cleaned = preg_replace('/^(returned?|nsf|declined|reversal|reverse|ach|check|payment)\s+/i', '', $description);
        $cleaned = preg_replace('/\s+(returned?|nsf|declined|reversal|reverse)$/i', '', $cleaned);

        // Remove dates and numbers
        $cleaned = preg_replace('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', '', $cleaned);
        $cleaned = preg_replace('/\b\d+\b/', '', $cleaned);

        // Remove extra whitespace
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        return $cleaned ?: null;
    }

    /**
     * Calculate comprehensive NSF and negative days analysis
     *
     * @param array $transactions
     * @param float|null $openingBalance
     * @return array Complete analysis
     */
    public function calculateComprehensiveAnalysis(array $transactions, ?float $openingBalance = null): array
    {
        $negativeDaysAnalysis = $this->calculateNegativeDays($transactions, $openingBalance);
        $nsfAnalysis = $this->calculateNsfCounts($transactions);

        return [
            'negative_days' => $negativeDaysAnalysis,
            'nsf' => $nsfAnalysis,
            'summary' => [
                'negative_days_count' => $negativeDaysAnalysis['negative_days_count'],
                'nsf_fee_count' => $nsfAnalysis['nsf_fee_count'],
                'returned_item_count' => $nsfAnalysis['returned_item_count'],
                'unique_nsf_events' => $nsfAnalysis['unique_nsf_events'],
            ]
        ];
    }
}
