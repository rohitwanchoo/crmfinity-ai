<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Models\TransactionCategory;
use Illuminate\Support\Str;

class ReprocessBankStatements extends Command
{
    protected $signature = 'bankstatement:reprocess
                            {--session= : Specific session ID to reprocess}
                            {--all : Reprocess all sessions}
                            {--recent=5 : Number of recent sessions to reprocess}
                            {--dry-run : Show what would be processed without actually processing}';

    protected $description = 'Reprocess historical bank statements to extract balance data';

    public function handle()
    {
        $this->info('🔄 Bank Statement Reprocessing Tool');
        $this->info('=====================================');
        $this->newLine();

        $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        if (!$apiKey) {
            $this->error('❌ OpenAI API key not configured!');
            return 1;
        }

        // Determine which sessions to process
        $sessions = $this->getSessions();

        if ($sessions->isEmpty()) {
            $this->warn('No sessions found to process.');
            return 0;
        }

        $this->info("Found {$sessions->count()} session(s) to process:");
        $this->newLine();

        // Show sessions
        $this->table(
            ['ID', 'Session ID', 'Filename', 'Transactions', 'Created'],
            $sessions->map(fn($s) => [
                $s->id,
                substr($s->session_id, 0, 8) . '...',
                Str::limit($s->filename, 40),
                $s->total_transactions,
                $s->created_at->format('Y-m-d H:i')
            ])
        );

        if ($this->option('dry-run')) {
            $this->info('🔍 Dry run - no actual processing performed');
            return 0;
        }

        if (!$this->confirm('Do you want to proceed with reprocessing?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        $this->newLine();
        $bar = $this->output->createProgressBar($sessions->count());
        $bar->start();

        $processed = 0;
        $failed = 0;

        foreach ($sessions as $session) {
            try {
                $this->reprocessSession($session, $apiKey);
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed to process {$session->filename}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Processing complete!");
        $this->info("Processed: {$processed}");
        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
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

        if ($this->option('all')) {
            return AnalysisSession::where('analysis_type', 'openai')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $recent = (int) $this->option('recent');
        return AnalysisSession::where('analysis_type', 'openai')
            ->orderBy('created_at', 'desc')
            ->limit($recent)
            ->get();
    }

    protected function reprocessSession($session, $apiKey)
    {
        $uploadPath = storage_path('app/uploads');
        $pdfPath = $uploadPath . '/' . $session->session_id . '_' . $session->filename;

        if (!file_exists($pdfPath)) {
            throw new \Exception("PDF file not found: {$pdfPath}");
        }

        // Get learned corrections for AI training
        $corrections = \App\Models\TransactionCorrection::select('description_pattern', 'correct_type')
            ->orderBy('usage_count', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
        $correctionsJson = json_encode($corrections);

        // Run Python script with corrections
        $scriptPath = storage_path('app/scripts/bank_statement_extractor.py');
        $model = $session->model_used ?? 'gpt-4o';

        $command = sprintf(
            '/var/www/html/crmfinity_underwriting/crmfinity-ai/venv/bin/python3 %s %s %s %s %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($pdfPath),
            escapeshellarg($apiKey),
            escapeshellarg($model),
            escapeshellarg($correctionsJson)
        );

        $output = shell_exec($command);
        $data = json_decode($output, true);

        if (!$data || !isset($data['success'])) {
            throw new \Exception('Failed to parse Python script output');
        }

        if (!$data['success']) {
            throw new \Exception($data['error'] ?? 'Unknown error from Python script');
        }

        // Delete old transactions
        $session->transactions()->delete();

        // Save new transactions with balance data
        foreach ($data['transactions'] as $txn) {
            $type = $txn['type'] ?? 'debit';
            $description = $txn['description'] ?? 'Unknown';

            // Auto-assign category
            $category = null;
            $categoryData = TransactionCategory::getCategoryForDescription($description, $type);
            if ($categoryData) {
                $category = $categoryData['category'];
            }

            $transactionData = [
                'analysis_session_id' => $session->id,
                'transaction_date' => $txn['date'] ?? date('Y-m-d'),
                'description' => $description,
                'amount' => $txn['amount'] ?? 0,
                'type' => $type,
                'original_type' => $type,
                'confidence' => 1.0,
                'confidence_label' => 'high',
                'category' => $category,
            ];

            // Add balance if available and calculate beginning balance
            if (isset($txn['ending_balance'])) {
                $endingBalance = (float) $txn['ending_balance'];
                $amount = (float) ($txn['amount'] ?? 0);

                $transactionData['ending_balance'] = $endingBalance;

                // Calculate beginning balance
                if ($type === 'credit') {
                    $transactionData['beginning_balance'] = $endingBalance - $amount;
                } else {
                    $transactionData['beginning_balance'] = $endingBalance + $amount;
                }
            }

            AnalyzedTransaction::create($transactionData);
        }

        // Update session with new totals and balance data
        $session->update([
            'total_transactions' => $data['summary']['total_transactions'],
            'total_credits' => $data['summary']['credit_total'],
            'total_debits' => $data['summary']['debit_total'],
            'total_returned' => $data['summary']['returned_total'] ?? 0,
            'returned_count' => $data['summary']['returned_count'] ?? 0,
            'net_flow' => $data['summary']['net_balance'],
            'beginning_balance' => $data['metadata']['beginning_balance'] ?? null,
            'ending_balance' => $data['metadata']['ending_balance'] ?? null,
        ]);
    }
}
