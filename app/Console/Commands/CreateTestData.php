<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use Illuminate\Support\Str;

class CreateTestData extends Command
{
    protected $signature = 'bankstatement:create-test-data';

    protected $description = 'Create test data with balance information to demonstrate negative days calculation';

    public function handle()
    {
        $this->info('🔧 Creating Test Data for Negative Days Calculation');
        $this->info('===================================================');
        $this->newLine();

        // Create a test session
        $sessionId = Str::uuid()->toString();

        $session = AnalysisSession::create([
            'session_id' => $sessionId,
            'user_id' => 1,
            'filename' => 'TEST_NEGATIVE_DAYS_DEMO.pdf',
            'bank_name' => 'Test Bank',
            'pages' => 1,
            'total_transactions' => 0,
            'total_credits' => 0,
            'total_debits' => 0,
            'total_returned' => 0,
            'returned_count' => 0,
            'net_flow' => 0,
            'high_confidence_count' => 0,
            'medium_confidence_count' => 0,
            'low_confidence_count' => 0,
            'analysis_type' => 'openai',
            'model_used' => 'gpt-4o',
            'api_cost' => 0,
        ]);

        $this->info("Created session: {$sessionId}");
        $this->newLine();

        // Create test transactions with realistic scenario:
        // Day 1: Start with $1000, multiple transactions go negative, then recover
        // Day 2: Multiple transactions, stay negative all day
        // Day 3: Multiple transactions, stay positive
        // Day 4: Start positive, end negative

        $testTransactions = [
            // January 15 - Start positive, go negative during day, end positive (should NOT count)
            ['date' => '2026-01-15', 'desc' => 'Opening Balance', 'amount' => 0, 'type' => 'credit', 'balance' => 1000.00],
            ['date' => '2026-01-15', 'desc' => 'Large Payment', 'amount' => 800, 'type' => 'debit', 'balance' => 200.00],
            ['date' => '2026-01-15', 'desc' => 'Bill Payment 1', 'amount' => 150, 'type' => 'debit', 'balance' => 50.00],
            ['date' => '2026-01-15', 'desc' => 'Bill Payment 2', 'amount' => 100, 'type' => 'debit', 'balance' => -50.00],
            ['date' => '2026-01-15', 'desc' => 'Bill Payment 3', 'amount' => 75, 'type' => 'debit', 'balance' => -125.00],
            ['date' => '2026-01-15', 'desc' => 'Deposit Received', 'amount' => 500, 'type' => 'credit', 'balance' => 375.00],

            // January 16 - Multiple negative transactions, day ends negative (SHOULD count as 1 day)
            ['date' => '2026-01-16', 'desc' => 'Payment 1', 'amount' => 200, 'type' => 'debit', 'balance' => 175.00],
            ['date' => '2026-01-16', 'desc' => 'Payment 2', 'amount' => 300, 'type' => 'debit', 'balance' => -125.00],
            ['date' => '2026-01-16', 'desc' => 'Payment 3', 'amount' => 150, 'type' => 'debit', 'balance' => -275.00],
            ['date' => '2026-01-16', 'desc' => 'Payment 4', 'amount' => 100, 'type' => 'debit', 'balance' => -375.00],

            // January 17 - Stays positive all day (should NOT count)
            ['date' => '2026-01-17', 'desc' => 'Large Deposit', 'amount' => 2000, 'type' => 'credit', 'balance' => 1625.00],
            ['date' => '2026-01-17', 'desc' => 'Small Payment', 'amount' => 50, 'type' => 'debit', 'balance' => 1575.00],

            // January 18 - Starts positive, ends negative (SHOULD count as 1 day)
            ['date' => '2026-01-18', 'desc' => 'Payment 1', 'amount' => 500, 'type' => 'debit', 'balance' => 1075.00],
            ['date' => '2026-01-18', 'desc' => 'Payment 2', 'amount' => 600, 'type' => 'debit', 'balance' => 475.00],
            ['date' => '2026-01-18', 'desc' => 'Large Bill', 'amount' => 800, 'type' => 'debit', 'balance' => -325.00],

            // January 19 - Recover to positive (should NOT count)
            ['date' => '2026-01-19', 'desc' => 'Recovery Deposit', 'amount' => 1000, 'type' => 'credit', 'balance' => 675.00],

            // January 20 - Three transactions, all negative balances, day ends negative (SHOULD count as 1 day)
            ['date' => '2026-01-20', 'desc' => 'Payment A', 'amount' => 700, 'type' => 'debit', 'balance' => -25.00],
            ['date' => '2026-01-20', 'desc' => 'Payment B', 'amount' => 100, 'type' => 'debit', 'balance' => -125.00],
            ['date' => '2026-01-20', 'desc' => 'Payment C', 'amount' => 50, 'type' => 'debit', 'balance' => -175.00],

            // January 21 - Final recovery
            ['date' => '2026-01-21', 'desc' => 'Final Deposit', 'amount' => 500, 'type' => 'credit', 'balance' => 325.00],
        ];

        $totalCredits = 0;
        $totalDebits = 0;

        foreach ($testTransactions as $txn) {
            $endingBalance = $txn['balance'];
            $amount = $txn['amount'];

            // Calculate beginning balance
            if ($txn['type'] === 'credit') {
                $beginningBalance = $endingBalance - $amount;
                $totalCredits += $amount;
            } else {
                $beginningBalance = $endingBalance + $amount;
                $totalDebits += $amount;
            }

            AnalyzedTransaction::create([
                'analysis_session_id' => $session->id,
                'transaction_date' => $txn['date'],
                'description' => $txn['desc'],
                'amount' => $amount,
                'type' => $txn['type'],
                'original_type' => $txn['type'],
                'confidence' => 1.0,
                'confidence_label' => 'high',
                'ending_balance' => $endingBalance,
                'beginning_balance' => $beginningBalance,
            ]);
        }

        // Update session totals
        $session->update([
            'total_transactions' => count($testTransactions),
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'net_flow' => $totalCredits - $totalDebits,
        ]);

        $this->info("Created {$session->total_transactions} test transactions");
        $this->newLine();

        $this->info('📊 Test Scenario Summary:');
        $this->line('   Jan 15: 6 txns (3 negative balances) → Ends POSITIVE at $375.00 → NOT counted');
        $this->line('   Jan 16: 4 txns (3 negative balances) → Ends NEGATIVE at -$375.00 → COUNTED ✓');
        $this->line('   Jan 17: 2 txns (0 negative balances) → Ends POSITIVE at $1,575.00 → NOT counted');
        $this->line('   Jan 18: 3 txns (1 negative balance) → Ends NEGATIVE at -$325.00 → COUNTED ✓');
        $this->line('   Jan 19: 1 txn (0 negative balances) → Ends POSITIVE at $675.00 → NOT counted');
        $this->line('   Jan 20: 3 txns (3 negative balances) → Ends NEGATIVE at -$175.00 → COUNTED ✓');
        $this->line('   Jan 21: 1 txn (0 negative balances) → Ends POSITIVE at $325.00 → NOT counted');
        $this->newLine();

        $this->info('🎯 Expected Result:');
        $this->line('   Total unique dates: 7');
        $this->line('   Transactions with negative balance: 10');
        $this->line('   Negative DAYS (day-wise): 3 (Jan 16, Jan 18, Jan 20)');
        $this->newLine();

        $this->info("✅ Test session created: {$sessionId}");
        $this->info("Run: php artisan bankstatement:test-negative-days --session={$sessionId}");

        return 0;
    }
}
