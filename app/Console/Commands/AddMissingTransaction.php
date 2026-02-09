<?php

namespace App\Console\Commands;

use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Services\AnalysisSummaryService;
use Illuminate\Console\Command;

class AddMissingTransaction extends Command
{
    protected $signature = 'transaction:add
                            {--session= : Session ID or filename to search for}
                            {--date= : Transaction date (YYYY-MM-DD)}
                            {--description= : Transaction description}
                            {--amount= : Transaction amount}
                            {--type=credit : Transaction type (credit or debit)}';

    protected $description = 'Add a missing transaction to an analysis session';

    public function handle(AnalysisSummaryService $summaryService)
    {
        $sessionSearch = $this->option('session');
        $date = $this->option('date');
        $description = $this->option('description');
        $amount = $this->option('amount');
        $type = $this->option('type');

        // Find the session
        if (!$sessionSearch) {
            $sessionSearch = $this->ask('Enter session ID or filename to search for');
        }

        $session = AnalysisSession::where('session_id', $sessionSearch)
            ->orWhere('filename', 'LIKE', "%{$sessionSearch}%")
            ->first();

        if (!$session) {
            $this->error("No session found matching: {$sessionSearch}");

            // Show available sessions
            $this->info("\nAvailable sessions:");
            AnalysisSession::orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->each(function ($s) {
                    $this->line("  ID: {$s->id} | Session: {$s->session_id} | File: {$s->filename}");
                });

            return 1;
        }

        $this->info("Found session: {$session->session_id}");
        $this->info("Filename: {$session->filename}");
        $this->info("Current totals - Credits: \${$session->total_credits} | Debits: \${$session->total_debits}");

        // Get transaction details
        if (!$description) {
            $description = $this->ask('Enter transaction description');
        }

        if (!$amount) {
            $amount = $this->ask('Enter transaction amount');
        }
        $amount = (float) str_replace(['$', ','], '', $amount);

        if (!$date) {
            $date = $this->ask('Enter transaction date (YYYY-MM-DD)');
        }

        if (!in_array($type, ['credit', 'debit'])) {
            $type = $this->choice('Select transaction type', ['credit', 'debit'], 0);
        }

        // Confirm
        $this->info("\nAdding transaction:");
        $this->line("  Date: {$date}");
        $this->line("  Description: {$description}");
        $this->line("  Amount: \$" . number_format($amount, 2));
        $this->line("  Type: {$type}");

        if (!$this->confirm('Proceed with adding this transaction?')) {
            $this->info('Cancelled.');
            return 0;
        }

        // Create the transaction
        $transaction = AnalyzedTransaction::create([
            'analysis_session_id' => $session->id,
            'transaction_date' => $date,
            'description' => $description,
            'description_normalized' => AnalyzedTransaction::normalizeDescription($description),
            'amount' => $amount,
            'type' => $type,
            'original_type' => $type,
            'was_corrected' => false,
            'confidence' => 1.0,
            'confidence_label' => 'high',
            'merchant_name' => AnalyzedTransaction::extractMerchant($description),
        ]);

        $this->info("\nTransaction added! (ID: {$transaction->id})");

        // Recalculate all totals using the summary service
        $this->info('Recalculating session totals and combined analysis summary...');
        $result = $summaryService->recalculateSession($session->id);

        $this->info("\nUpdated Session Totals:");
        $this->line("  Total Credits: \$" . number_format($result['total_credits'], 2));
        $this->line("  Total Debits: \$" . number_format($result['total_debits'], 2));
        $this->line("  Net Flow: \$" . number_format($result['net_flow'], 2));
        $this->line("  True Revenue: \$" . number_format($result['true_revenue'], 2));
        $this->line("  Total Transactions: {$result['total_transactions']}");

        $this->info("\nRevenue Breakdown:");
        $this->line("  Revenue Items: {$result['revenue_breakdown']['revenue_items']}");
        $this->line("  Excluded Items: {$result['revenue_breakdown']['excluded_items']}");
        $this->line("  Excluded Amount: \$" . number_format($result['revenue_breakdown']['excluded_amount'], 2));

        if ($result['document_updated']) {
            $this->info("\nLinked document updated.");
        }

        if ($result['application_updated']) {
            $this->info("Combined analysis summary updated for application.");
        }

        return 0;
    }
}
