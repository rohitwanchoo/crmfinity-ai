<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DemoNegativeDays extends Command
{
    protected $signature = 'bankstatement:demo-negative-days';

    protected $description = 'Demonstrate the negative days fix with sample data';

    public function handle()
    {
        $this->info('🧪 Negative Days Calculation - Before vs After Demo');
        $this->info('===================================================');
        $this->newLine();

        // Sample data: 3 transactions on the same day with different negative balances
        $sampleTransactions = [
            ['date' => '2025-01-15', 'description' => 'Payment 1', 'amount' => 500, 'type' => 'debit', 'ending_balance' => -100],
            ['date' => '2025-01-15', 'description' => 'Payment 2', 'amount' => 300, 'type' => 'debit', 'ending_balance' => -400],
            ['date' => '2025-01-15', 'description' => 'Deposit', 'amount' => 450, 'type' => 'credit', 'ending_balance' => 50],
            ['date' => '2025-01-16', 'description' => 'Payment 3', 'amount' => 200, 'type' => 'debit', 'ending_balance' => -150],
            ['date' => '2025-01-16', 'description' => 'Payment 4', 'amount' => 100, 'type' => 'debit', 'ending_balance' => -250],
            ['date' => '2025-01-17', 'description' => 'Deposit 2', 'amount' => 500, 'type' => 'credit', 'ending_balance' => 250],
        ];

        $this->info('📊 Sample Transactions:');
        $this->newLine();

        $tableData = [];
        foreach ($sampleTransactions as $txn) {
            $tableData[] = [
                $txn['date'],
                substr($txn['description'], 0, 20),
                ucfirst($txn['type']),
                '$' . number_format($txn['amount'], 2),
                '$' . number_format($txn['ending_balance'], 2),
                $txn['ending_balance'] < 0 ? '❌ Negative' : '✅ Positive',
            ];
        }

        $this->table(
            ['Date', 'Description', 'Type', 'Amount', 'Ending Balance', 'Status'],
            $tableData
        );

        $this->newLine();
        $this->info('🔍 Analysis by Date:');
        $this->newLine();

        // Group by date
        $byDate = [];
        foreach ($sampleTransactions as $txn) {
            $byDate[$txn['date']][] = $txn;
        }

        foreach ($byDate as $date => $txns) {
            $this->info("   Date: {$date}");
            $this->info("   Transactions: " . count($txns));

            foreach ($txns as $idx => $txn) {
                $status = $txn['ending_balance'] < 0 ? '❌' : '✅';
                $this->line("      Txn " . ($idx + 1) . ": {$txn['description']} - Balance: $" . number_format($txn['ending_balance'], 2) . " {$status}");
            }

            $lastTxn = end($txns);
            $lastBalance = $lastTxn['ending_balance'];
            $dayStatus = $lastBalance < 0 ? '❌ NEGATIVE DAY' : '✅ POSITIVE DAY';

            $this->warn("   → Final Day Balance: $" . number_format($lastBalance, 2) . " {$dayStatus}");
            $this->newLine();
        }

        // Calculate negative days using the NEW method
        $this->info('📈 Calculation Results:');
        $this->newLine();

        $negativeDaysNew = $this->calculateNegativeDaysNew($sampleTransactions);
        $uniqueDates = count($byDate);
        $totalTransactions = count($sampleTransactions);
        $transactionsWithNegativeBalance = count(array_filter($sampleTransactions, fn($t) => $t['ending_balance'] < 0));

        $this->line("   Total Unique Dates: {$uniqueDates}");
        $this->line("   Total Transactions: {$totalTransactions}");
        $this->line("   Transactions with Negative Balance: {$transactionsWithNegativeBalance}");
        $this->newLine();

        $this->info("   ✅ NEW METHOD (Day-wise, using last transaction per day):");
        $this->info("      Negative Days: {$negativeDaysNew}");
        $this->newLine();

        $this->info('💡 Explanation:');
        $this->line('   - 2025-01-15: 3 transactions, last balance = $50 (POSITIVE) → Not counted');
        $this->line('   - 2025-01-16: 2 transactions, last balance = -$250 (NEGATIVE) → Counted as 1 day');
        $this->line('   - 2025-01-17: 1 transaction, last balance = $250 (POSITIVE) → Not counted');
        $this->newLine();
        $this->info('   ✅ Result: 1 negative day (only 2025-01-16)');
        $this->newLine();

        $this->info('🎯 Key Point:');
        $this->line('   Even though there are 4 transactions with negative balances,');
        $this->line('   only 1 UNIQUE DAY ends with a negative balance.');
        $this->line('   The system now counts DAYS, not individual transactions!');

        return 0;
    }

    protected function calculateNegativeDaysNew(array $transactions): int
    {
        $negativeDays = 0;
        $dailyBalances = [];

        // Group transactions by date to get the last balance for each day
        $transactionsByDate = [];
        foreach ($transactions as $txn) {
            $date = $txn['date'];
            if (!isset($transactionsByDate[$date])) {
                $transactionsByDate[$date] = [];
            }
            $transactionsByDate[$date][] = $txn;
        }

        // For each day, get the last transaction's ending balance
        foreach ($transactionsByDate as $date => $dayTransactions) {
            $lastTransaction = end($dayTransactions);
            $endingBalance = $lastTransaction['ending_balance'] ?? null;

            if ($endingBalance !== null) {
                $dailyBalances[$date] = (float) $endingBalance;
            }
        }

        // Count unique days where balance < 0
        foreach ($dailyBalances as $date => $balance) {
            if ($balance < 0) {
                $negativeDays++;
            }
        }

        return $negativeDays;
    }
}
