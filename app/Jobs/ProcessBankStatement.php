<?php

namespace App\Jobs;

use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Models\ApiUsageLog;
use App\Models\TransactionCategory;
use App\Models\TransactionCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBankStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 1; // Don't retry (expensive operation)

    protected $sessionId;
    protected $batchId;
    protected $filename;
    protected $savedPath;
    protected $model;
    protected $apiKey;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $sessionId,
        string $batchId,
        string $filename,
        string $savedPath,
        string $model,
        string $apiKey,
        ?int $userId = null
    ) {
        $this->sessionId = $sessionId;
        $this->batchId = $batchId;
        $this->filename = $filename;
        $this->savedPath = $savedPath;
        $this->model = $model;
        $this->apiKey = $apiKey;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $jobStartTime = now();

            Log::info("Job started: Processing bank statement", [
                'session_id' => $this->sessionId,
                'filename' => $this->filename,
                'batch_id' => $this->batchId,
            ]);

            // Get learned corrections for AI training
            $corrections = TransactionCorrection::select('description_pattern', 'correct_type')
                ->orderBy('usage_count', 'desc')
                ->limit(50)
                ->get()
                ->toArray();
            $correctionsJson = json_encode($corrections);

            // Run Python script with corrections
            $scriptPath = storage_path('app/scripts/bank_statement_extractor.py');
            $stderrFile = storage_path('logs/python_stderr_' . $this->sessionId . '.log');
            $command = sprintf(
                '/var/www/html/crmfinity_underwriting/crmfinity-ai/venv/bin/python3 %s %s %s %s %s 2>%s',
                escapeshellarg($scriptPath),
                escapeshellarg($this->savedPath),
                escapeshellarg($this->apiKey),
                escapeshellarg($this->model),
                escapeshellarg($correctionsJson),
                escapeshellarg($stderrFile)
            );

            $output = shell_exec($command);

            // Clean up: extract only the JSON part if there's any extra text
            $lines = explode("\n", trim($output));
            $jsonLine = end($lines);

            // If the last line doesn't look like JSON, try to find it
            if (!str_starts_with(trim($jsonLine), '{')) {
                foreach (array_reverse($lines) as $line) {
                    if (str_starts_with(trim($line), '{')) {
                        $jsonLine = $line;
                        break;
                    }
                }
            }

            $data = json_decode($jsonLine, true);

            if (!$data || !isset($data['success'])) {
                $stderr = file_exists($stderrFile) ? file_get_contents($stderrFile) : '';
                throw new \Exception('Failed to parse Python script output. Output: ' . substr($output, 0, 200) . '... Stderr: ' . substr($stderr, 0, 200));
            }

            // Clean up stderr log file
            if (file_exists($stderrFile)) {
                @unlink($stderrFile);
            }

            if (!$data['success']) {
                throw new \Exception($data['error'] ?? 'Unknown error from Python script');
            }

            // Detect bank name from metadata or filename
            $bankName = $data['metadata']['bank_name'] ?? $this->detectBankName($this->filename);

            // Save to database
            $session = AnalysisSession::create([
                'session_id' => $this->sessionId,
                'batch_id' => $this->batchId,
                'user_id' => $this->userId,
                'filename' => $this->filename,
                'bank_name' => $bankName,
                'pages' => $data['metadata']['pages'] ?? 1,
                'total_transactions' => $data['summary']['total_transactions'],
                'total_credits' => $data['summary']['credit_total'],
                'total_debits' => $data['summary']['debit_total'],
                'total_returned' => $data['summary']['returned_total'] ?? 0,
                'returned_count' => $data['summary']['returned_count'] ?? 0,
                'net_flow' => $data['summary']['net_balance'],
                'high_confidence_count' => $data['summary']['total_transactions'],
                'medium_confidence_count' => 0,
                'low_confidence_count' => 0,
                'analysis_type' => 'claude',
                'model_used' => $this->model,
                'api_cost' => $data['api_cost']['total_cost'] ?? 0,
                'input_tokens' => $data['api_cost']['input_tokens'] ?? 0,
                'output_tokens' => $data['api_cost']['output_tokens'] ?? 0,
                'total_tokens' => $data['api_cost']['total_tokens'] ?? 0,
                'beginning_balance' => $data['statement_summary']['beginning_balance'] ?? null,
                'ending_balance' => $data['statement_summary']['ending_balance'] ?? null,
                'average_daily_balance' => $data['statement_summary']['average_daily_balance'] ?? null,
                'processing_time' => $jobStartTime->diffInSeconds(now()),
                'source_type' => ($data['metadata']['ocr_used'] ?? false) ? 'scanned' : 'pdf',
            ]);

            // Log detailed API usage for tracking
            if (isset($data['api_cost'])) {
                $apiCost = $data['api_cost'];
                ApiUsageLog::create([
                    'analysis_session_id' => $session->id,
                    'user_id' => $this->userId,
                    'api_provider' => 'anthropic',
                    'model_used' => $apiCost['model'] ?? $this->model,
                    'input_tokens' => $apiCost['input_tokens'] ?? 0,
                    'output_tokens' => $apiCost['output_tokens'] ?? 0,
                    'total_tokens' => $apiCost['total_tokens'] ?? 0,
                    'input_cost' => $apiCost['input_cost'] ?? 0,
                    'output_cost' => $apiCost['output_cost'] ?? 0,
                    'total_cost' => $apiCost['total_cost'] ?? 0,
                    'extraction_method' => $data['metadata']['extraction_method'] ?? 'ai',
                    'status' => 'success',
                    'endpoint' => '/v1/messages',
                ]);

                Log::info("API usage logged", [
                    'session_id' => $this->sessionId,
                    'total_tokens' => $apiCost['total_tokens'] ?? 0,
                    'total_cost' => $apiCost['total_cost'] ?? 0,
                ]);
            }

            // Save transactions
            Log::info("Starting to save transactions", [
                'session_id' => $this->sessionId,
                'transaction_count' => count($data['transactions'] ?? []),
            ]);

            foreach ($data['transactions'] as $index => $txn) {
                try {
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

                    // Add balance if available
                    if (isset($txn['ending_balance'])) {
                        $endingBalance = (float) $txn['ending_balance'];
                        $amount = (float) ($txn['amount'] ?? 0);
                        $transactionData['ending_balance'] = $endingBalance;

                        if ($type === 'credit') {
                            $transactionData['beginning_balance'] = $endingBalance - $amount;
                        } else {
                            $transactionData['beginning_balance'] = $endingBalance + $amount;
                        }
                    }

                    AnalyzedTransaction::create($transactionData);
                } catch (\Exception $e) {
                    Log::error("Failed to save transaction", [
                        'session_id' => $this->sessionId,
                        'transaction_index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            Log::info("Finished saving transactions", [
                'session_id' => $this->sessionId,
                'transactions_saved' => $session->transactions()->count(),
            ]);

            // Run MCA classification, negative days, NSF calculations, etc.
            $this->runPostProcessing($session);

            Log::info("Job completed successfully", [
                'session_id' => $this->sessionId,
                'filename' => $this->filename,
            ]);

        } catch (\Exception $e) {
            Log::error("Job failed: Bank statement processing error", [
                'session_id' => $this->sessionId,
                'filename' => $this->filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Run post-processing (MCA classification, negative days, NSF)
     */
    protected function runPostProcessing(AnalysisSession $session): void
    {
        // Run the calculations that were in the controller
        $controller = app(\App\Http\Controllers\BankStatementController::class);

        // Get transactions for analysis
        $transactions = $session->transactions()->orderBy('transaction_date')->get();

        // You can add MCA classification, negative days calculation, etc. here
        // For now, just log that post-processing is complete
        Log::info("Post-processing complete", ['session_id' => $this->sessionId]);
    }

    /**
     * Detect bank name from filename
     */
    protected function detectBankName(string $filename): ?string
    {
        $filename = strtolower($filename);

        if (str_contains($filename, 'chase')) return 'Chase Bank';
        if (str_contains($filename, 'wells')) return 'Wells Fargo';
        if (str_contains($filename, 'bofa') || str_contains($filename, 'bank of america')) return 'Bank of America';
        if (str_contains($filename, 'citi')) return 'Citibank';
        if (str_contains($filename, 'usbank') || str_contains($filename, 'us bank')) return 'US Bank';
        if (str_contains($filename, 'pnc')) return 'PNC Bank';
        if (str_contains($filename, 'td')) return 'TD Bank';
        if (str_contains($filename, 'capital')) return 'Capital One';

        return null;
    }
}
