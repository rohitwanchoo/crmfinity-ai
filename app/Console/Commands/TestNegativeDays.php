<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AnalysisSession;
use App\Models\TransactionCategory;
use App\Models\RevenueClassification;
use App\Models\McaPattern;

class TestNegativeDays extends Command
{
    protected $signature = 'bankstatement:test-negative-days
                            {--session= : Specific session ID to test}
                            {--recent=5 : Number of recent sessions to test}';

    protected $description = 'Test negative days calculation on historical data';

    public function handle()
    {
        $this->info('🧪 Testing Negative Days Calculation');
        $this->info('====================================');
        $this->newLine();

        // Get sessions to test
        $sessions = $this->getSessions();

        if ($sessions->isEmpty()) {
            $this->warn('No sessions found to test.');
            return 0;
        }

        $this->info("Testing {$sessions->count()} session(s)...");
        $this->newLine();

        foreach ($sessions as $session) {
            $this->testSession($session);
            $this->newLine();
        }

        return 0;
    }

    protected function getSessions()
    {
        if ($sessionId = $this->option('session')) {
            return AnalysisSession::where('session_id', $sessionId)
                ->where('analysis_type', 'openai')
                ->get();
        }

        $recent = (int) $this->option('recent');
        return AnalysisSession::where('analysis_type', 'openai')
            ->orderBy('created_at', 'desc')
            ->limit($recent)
            ->get();
    }

    protected function testSession($session)
    {
        $this->info("📊 Session: {$session->filename}");
        $this->info("   ID: {$session->session_id}");
        $this->info("   Date: {$session->created_at->format('Y-m-d H:i')}");

        // Get transactions
        $transactions = $session->transactions()
            ->orderBy('transaction_date')
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'date' => $txn->transaction_date,
                    'description' => $txn->description,
                    'amount' => (float) $txn->amount,
                    'type' => $txn->type,
                    'ending_balance' => $txn->ending_balance,
                    'beginning_balance' => $txn->beginning_balance,
                ];
            })
            ->toArray();

        if (empty($transactions)) {
            $this->warn('   No transactions found');
            return;
        }

        // Calculate monthly data with negative days
        $monthlyData = $this->groupTransactionsByMonth($transactions);

        $this->info("   Total Transactions: " . count($transactions));

        // Check if we have balance data
        $hasBalances = collect($transactions)->contains(fn($t) => isset($t['ending_balance']) && $t['ending_balance'] !== null);
        $this->info("   Has Balance Data: " . ($hasBalances ? 'Yes' : 'No'));

        // Show monthly breakdown
        $this->newLine();
        $this->info("   📅 Monthly Breakdown:");

        $tableData = [];
        foreach ($monthlyData['months'] as $month) {
            // Count transactions per day
            $transactionsByDay = [];
            foreach ($month['transactions'] as $txn) {
                $date = $txn['date'] ?? null;
                if (!$date) continue;
                $dateStr = is_string($date) ? $date : (string) $date;
                if (!isset($transactionsByDay[$dateStr])) {
                    $transactionsByDay[$dateStr] = [];
                }
                $transactionsByDay[$dateStr][] = $txn;
            }

            // Find days with multiple transactions
            $multipleTxnDays = array_filter($transactionsByDay, fn($txns) => count($txns) > 1);
            $multipleTxnDaysCount = count($multipleTxnDays);

            $tableData[] = [
                $month['month_name'],
                $month['days_in_month'],
                count($month['transactions']),
                $multipleTxnDaysCount,
                $month['negative_days'],
            ];
        }

        $this->table(
            ['Month', 'Days in Month', 'Transactions', 'Days with 2+ Txns', 'Negative Days'],
            $tableData
        );

        // Show days with multiple transactions and their balances
        if ($hasBalances) {
            $this->newLine();
            $this->info("   🔍 Days with Multiple Transactions:");

            foreach ($monthlyData['months'] as $month) {
                $transactionsByDay = [];
                foreach ($month['transactions'] as $txn) {
                    $date = $txn['date'] ?? null;
                    if (!$date) continue;
                    $dateStr = is_string($date) ? $date : (string) $date;
                    if (!isset($transactionsByDay[$dateStr])) {
                        $transactionsByDay[$dateStr] = [];
                    }
                    $transactionsByDay[$dateStr][] = $txn;
                }

                foreach ($transactionsByDay as $dateStr => $dayTxns) {
                    if (count($dayTxns) > 1) {
                        $this->info("      Date: {$dateStr} ({$month['month_name']})");
                        foreach ($dayTxns as $idx => $txn) {
                            $balance = isset($txn['ending_balance']) ? '$' . number_format($txn['ending_balance'], 2) : 'N/A';
                            $this->line("         Txn " . ($idx + 1) . ": " . substr($txn['description'], 0, 40) . " - Balance: {$balance}");
                        }
                        $lastBalance = end($dayTxns)['ending_balance'] ?? null;
                        if ($lastBalance !== null) {
                            $status = $lastBalance < 0 ? '❌ NEGATIVE' : '✅ POSITIVE';
                            $this->info("         Final Day Balance: $" . number_format($lastBalance, 2) . " {$status}");
                        }
                        $this->newLine();
                    }
                }
            }
        }
    }

    /**
     * Simplified version of groupTransactionsByMonth for testing
     */
    protected function groupTransactionsByMonth(array $transactions): array
    {
        $monthlyGroups = [];

        foreach ($transactions as $txn) {
            $date = $txn['date'] ?? null;
            if (!$date) continue;

            $timestamp = strtotime($date);
            if (!$timestamp) continue;

            $monthKey = date('Y-m', $timestamp);
            $monthName = date('F Y', $timestamp);

            if (!isset($monthlyGroups[$monthKey])) {
                $monthlyGroups[$monthKey] = [
                    'month_key' => $monthKey,
                    'month_name' => $monthName,
                    'transactions' => [],
                    'days_in_month' => date('t', $timestamp),
                    'negative_days' => 0,
                ];
            }

            $monthlyGroups[$monthKey]['transactions'][] = $txn;
        }

        // Calculate negative days for each month
        foreach ($monthlyGroups as &$month) {
            $negativeDays = 0;
            $dailyBalances = [];
            $hasActualBalances = false;

            // Check if we have actual balance data
            foreach ($month['transactions'] as $txn) {
                if (isset($txn['ending_balance']) && $txn['ending_balance'] !== null) {
                    $hasActualBalances = true;
                    break;
                }
            }

            if ($hasActualBalances) {
                // Use actual ending balances from bank statement
                // Group transactions by date to get the last balance for each day
                $transactionsByDate = [];
                foreach ($month['transactions'] as $txn) {
                    $date = $txn['date'] ?? null;
                    if (!$date) continue;

                    $dateStr = is_string($date) ? $date : (string) $date;

                    if (!isset($transactionsByDate[$dateStr])) {
                        $transactionsByDate[$dateStr] = [];
                    }

                    $transactionsByDate[$dateStr][] = $txn;
                }

                // For each day, get the last transaction's ending balance
                foreach ($transactionsByDate as $dateStr => $dayTransactions) {
                    // Get the last transaction of the day (assuming transactions are in chronological order)
                    $lastTransaction = end($dayTransactions);
                    $endingBalance = $lastTransaction['ending_balance'] ?? null;

                    if ($endingBalance !== null) {
                        $dailyBalances[$dateStr] = (float) $endingBalance;
                    }
                }

                // Count unique days where balance < 0
                foreach ($dailyBalances as $dateStr => $balance) {
                    if ($balance < 0) {
                        $negativeDays++;
                    }
                }
            }

            $month['negative_days'] = $negativeDays;
        }
        unset($month);

        ksort($monthlyGroups);

        return [
            'months' => array_values($monthlyGroups),
        ];
    }
}
