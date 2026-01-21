<?php

namespace App\Services;

use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Models\ApplicationDocument;
use App\Models\MCAApplication;
use App\Models\TransactionCorrection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BankAnalysisService
{
    protected BankPatternService $bankPatternService;
    protected UnderwritingScoreService $underwritingScoreService;

    // Patterns to exclude from true revenue calculation
    protected const EXCLUDE_PATTERNS = [
        'transfer from', 'xfer from', 'online transfer', 'wire transfer', 'ach transfer',
        'zelle from', 'venmo from', 'paypal transfer', 'internal transfer', 'move money',
        'loan', 'advance', 'mca', 'merchant cash', 'funding', 'capital', 'lending',
        'refund', 'reversal', 'return', 'rebate', 'chargeback', 'credit adjustment',
        'interest', 'dividend', 'bonus', 'reward', 'cashback',
        'nsf fee reversal', 'overdraft reversal', 'fee waiver',
    ];

    public function __construct(
        BankPatternService $bankPatternService,
        UnderwritingScoreService $underwritingScoreService
    ) {
        $this->bankPatternService = $bankPatternService;
        $this->underwritingScoreService = $underwritingScoreService;
    }

    /**
     * Analyze all bank statements for an application
     */
    public function analyzeApplication(MCAApplication $application): array
    {
        $bankStatements = $application->documents()
            ->where('document_type', 'bank_statement')
            ->where('mime_type', 'application/pdf')
            ->get();

        if ($bankStatements->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No bank statement PDFs found for analysis.',
            ];
        }

        $results = [];
        $allSessionIds = [];
        $totals = [
            'true_revenue' => 0,
            'credits' => 0,
            'debits' => 0,
            'transactions' => 0,
        ];

        foreach ($bankStatements as $document) {
            $result = $this->analyzeDocument($document, $application->id);
            $results[] = $result;

            if ($result['success']) {
                $allSessionIds[] = $result['session_id'];
                $totals['true_revenue'] += $result['true_revenue'];
                $totals['credits'] += $result['total_credits'];
                $totals['debits'] += $result['total_debits'];
                $totals['transactions'] += $result['transactions'];
            }
        }

        $successCount = count(array_filter($results, fn ($r) => $r['success']));

        // Add analysis note
        $this->addNote(
            $application,
            'analysis',
            "Bank statement analysis completed: {$successCount} of " . count($bankStatements) .
            ' statements analyzed. True Revenue: $' . number_format($totals['true_revenue'], 2)
        );

        // Calculate underwriting score
        $underwritingResult = null;
        if ($successCount > 0) {
            $underwritingResult = $this->calculateUnderwriting($application);
        }

        // Generate FCS report
        $fcsData = [];
        if (!empty($allSessionIds)) {
            $application->refresh();
            $fcsData = $this->generateFCSReport($application, $results, $totals);
        }

        return [
            'success' => true,
            'message' => "Analyzed {$successCount} bank statements successfully.",
            'results' => $results,
            'summary' => [
                'statements_analyzed' => $successCount,
                'total_statements' => count($bankStatements),
                'total_transactions' => $totals['transactions'],
                'total_credits' => round($totals['credits'], 2),
                'total_debits' => round($totals['debits'], 2),
                'total_true_revenue' => round($totals['true_revenue'], 2),
            ],
            'fcs_url' => $fcsData['url'] ?? null,
            'fcs_filename' => $fcsData['filename'] ?? null,
            'underwriting' => $underwritingResult,
        ];
    }

    /**
     * Analyze a single document
     */
    public function analyzeDocument(ApplicationDocument $document, int $applicationId): array
    {
        try {
            $filePath = Storage::disk('local')->path($document->storage_path);

            if (!file_exists($filePath)) {
                return [
                    'document_id' => $document->id,
                    'filename' => $document->original_filename,
                    'success' => false,
                    'error' => 'File not found on server',
                ];
            }

            // Extract text from PDF
            $extractResult = $this->extractPDFText($filePath);
            if (!$extractResult['success']) {
                return [
                    'document_id' => $document->id,
                    'filename' => $document->original_filename,
                    'success' => false,
                    'error' => $extractResult['error'] ?? 'Failed to extract PDF text',
                ];
            }

            $text = $extractResult['text'];
            $pages = $extractResult['pages'] ?? 0;

            Log::info("Bank Analysis: Processing '{$document->original_filename}' - {$pages} pages");

            // Parse transactions
            $transactions = $pages > 1 && strlen($text) > 50000
                ? $this->parseTransactionsInChunks($text, $pages)
                : $this->parseTransactionsWithAI($text);

            // Apply learned corrections
            $transactions = $this->applyLearnedCorrections($transactions);

            // Calculate summary
            $summary = $this->calculateTransactionSummary($transactions);

            // Save analysis session
            $session = null;
            if (!empty($transactions)) {
                $session = $this->saveAnalysisSession([
                    'file' => $document->original_filename,
                    'pages' => $pages,
                    'transactions' => $transactions,
                    'summary' => $summary,
                ], $transactions, $applicationId);
            }

            // Calculate true revenue
            $trueRevenueData = $this->calculateTrueRevenue($transactions);

            // Update document
            $document->update([
                'is_processed' => true,
                'analysis_session_id' => $session?->id,
                'true_revenue' => $trueRevenueData['true_revenue'],
                'total_credits' => $summary['total_credits'],
                'total_debits' => $summary['total_debits'],
                'transaction_count' => count($transactions),
                'analyzed_at' => now(),
                'extracted_data' => [
                    'summary' => $summary,
                    'true_revenue_data' => $trueRevenueData,
                    'session_id' => $session?->session_id,
                ],
            ]);

            Log::info("Bank Analysis: Completed '{$document->original_filename}' - " .
                count($transactions) . ' transactions, True Revenue: $' .
                number_format($trueRevenueData['true_revenue'], 2));

            return [
                'document_id' => $document->id,
                'filename' => $document->original_filename,
                'statement_period' => $document->statement_period,
                'success' => true,
                'transactions' => count($transactions),
                'total_credits' => $summary['total_credits'],
                'total_debits' => $summary['total_debits'],
                'true_revenue' => $trueRevenueData['true_revenue'],
                'session_id' => $session?->session_id,
            ];

        } catch (\Exception $e) {
            Log::error("Bank Analysis Error for {$document->original_filename}: " . $e->getMessage());

            return [
                'document_id' => $document->id,
                'filename' => $document->original_filename,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract text from PDF using Python script
     */
    protected function extractPDFText(string $filePath): array
    {
        $scriptPath = storage_path('app/scripts/extract_pdf_text.py');
        $command = 'python3 ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($filePath) . ' false 2>&1';
        $output = shell_exec($command);

        return json_decode($output, true) ?? ['success' => false, 'error' => 'Failed to parse PDF output'];
    }

    /**
     * Parse transactions in chunks for long statements
     */
    protected function parseTransactionsInChunks(string $text, int $pages): array
    {
        $pageTexts = preg_split('/\n*=== PAGE BREAK ===\n*/i', $text);
        $allTransactions = [];
        $seenTransactions = [];

        foreach ($pageTexts as $index => $pageText) {
            $pageText = trim($pageText);
            if (empty($pageText) || strlen($pageText) < 50) {
                continue;
            }

            $contextText = 'PAGE ' . ($index + 1) . " OF {$pages}\n\n" . $pageText;
            $transactions = $this->parseTransactionsWithAI($contextText);

            foreach ($transactions as $txn) {
                $key = $txn['date'] . '|' . strtolower(substr($txn['description'], 0, 30)) . '|' . number_format($txn['amount'], 2);
                if (!isset($seenTransactions[$key])) {
                    $seenTransactions[$key] = true;
                    $allTransactions[] = $txn;
                }
            }
        }

        usort($allTransactions, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $allTransactions;
    }

    /**
     * Parse transactions using AI
     */
    protected function parseTransactionsWithAI(string $text): array
    {
        $apiKey = env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            Log::error('Bank Analysis: Anthropic API key not configured');
            return [];
        }

        $bankContext = $this->bankPatternService->buildAIContext($text);

        $tempFile = storage_path('app/uploads/temp_' . Str::random(10) . '.txt');
        file_put_contents($tempFile, $text);

        $learnedPatterns = $this->getLearnedPatternsForAI();
        $combinedPatterns = [
            'learned_patterns' => $learnedPatterns,
            'bank_context' => $bankContext,
        ];
        $patternsFile = storage_path('app/uploads/patterns_' . Str::random(10) . '.json');
        file_put_contents($patternsFile, json_encode($combinedPatterns));

        try {
            $scriptPath = storage_path('app/scripts/parse_transactions_ai.py');
            $outputFile = storage_path('app/uploads/output_' . Str::random(10) . '.json');

            $command = 'timeout 320 python3 ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tempFile) . ' ' . escapeshellarg($apiKey);
            $command .= ' ' . escapeshellarg($patternsFile);
            $command .= ' ' . escapeshellarg($outputFile);
            $command .= ' 2>&1';

            shell_exec($command);

            @unlink($tempFile);
            @unlink($patternsFile);

            $result = null;
            if (file_exists($outputFile)) {
                $result = json_decode(file_get_contents($outputFile), true);
                @unlink($outputFile);
            }

            if ($result && $result['success'] && isset($result['transactions'])) {
                $detectedBank = $bankContext['detected_bank'] ?? 'Unknown';
                foreach ($result['transactions'] as &$txn) {
                    $txn['detected_bank'] = $detectedBank;
                }
                return $result['transactions'];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Bank Analysis Exception: ' . $e->getMessage());
            @unlink($tempFile);
            @unlink($patternsFile);
            return [];
        }
    }

    /**
     * Get learned patterns for AI context
     */
    protected function getLearnedPatternsForAI(): array
    {
        $corrections = TransactionCorrection::getLearnedPatterns();
        $historical = AnalyzedTransaction::getLearnedPatterns();

        $allPatterns = array_merge($corrections, $historical);
        $unique = [];
        $seen = [];

        foreach ($allPatterns as $pattern) {
            $key = strtolower($pattern['description_pattern'] ?? $pattern['description_normalized'] ?? '');
            if (!empty($key) && !isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = [
                    'description_pattern' => $key,
                    'correct_type' => $pattern['correct_type'],
                ];
            }
        }

        return array_slice($unique, 0, 100);
    }

    /**
     * Apply learned corrections to transactions
     */
    protected function applyLearnedCorrections(array $transactions): array
    {
        $corrections = TransactionCorrection::all()->keyBy(fn ($item) => strtolower($item->description_pattern));
        $historicalCorrections = AnalyzedTransaction::where('was_corrected', true)
            ->get()
            ->keyBy(fn ($item) => strtolower($item->description_normalized));

        if ($corrections->isEmpty() && $historicalCorrections->isEmpty()) {
            return $transactions;
        }

        foreach ($transactions as &$txn) {
            $normalizedDesc = TransactionCorrection::normalizePattern($txn['description']);
            $normalizedDescLower = strtolower($normalizedDesc);

            foreach ($corrections as $pattern => $correction) {
                if (stripos($normalizedDescLower, $pattern) !== false) {
                    if ($txn['type'] !== $correction->correct_type) {
                        $txn['type'] = $correction->correct_type;
                        $txn['corrected'] = true;
                        $txn['confidence'] = 0.99;
                    }
                    break;
                }
            }
        }

        return $transactions;
    }

    /**
     * Calculate transaction summary
     */
    protected function calculateTransactionSummary(array $transactions): array
    {
        $totalCredits = 0;
        $totalDebits = 0;
        $creditCount = 0;
        $debitCount = 0;

        foreach ($transactions as $txn) {
            if ($txn['type'] === 'credit') {
                $totalCredits += $txn['amount'];
                $creditCount++;
            } else {
                $totalDebits += $txn['amount'];
                $debitCount++;
            }
        }

        return [
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'credit_count' => $creditCount,
            'debit_count' => $debitCount,
            'net_flow' => $totalCredits - $totalDebits,
            'transaction_count' => count($transactions),
        ];
    }

    /**
     * Save analysis session
     */
    protected function saveAnalysisSession(array $data, array &$transactions, int $applicationId): AnalysisSession
    {
        $session = AnalysisSession::createFromAnalysis($data, Auth::id());
        $session->update(['application_id' => $applicationId]);
        $transactionIds = AnalyzedTransaction::createFromAnalysis($session->id, $transactions);

        foreach ($transactionIds as $index => $id) {
            if (isset($transactions[$index])) {
                $transactions[$index]['id'] = $id;
            }
        }

        return $session;
    }

    /**
     * Calculate true revenue (excluding transfers, loans, etc.)
     */
    public function calculateTrueRevenue(array $transactions): array
    {
        $totalCredits = 0;
        $trueRevenue = 0;
        $excludedAmount = 0;
        $excludedItems = [];

        foreach ($transactions as $txn) {
            if ($txn['type'] !== 'credit') {
                continue;
            }

            $totalCredits += $txn['amount'];
            $description = strtolower($txn['description']);
            $isExcluded = false;

            foreach (self::EXCLUDE_PATTERNS as $pattern) {
                if (stripos($description, $pattern) !== false) {
                    $isExcluded = true;
                    break;
                }
            }

            if ($isExcluded) {
                $excludedAmount += $txn['amount'];
                $excludedItems[] = [
                    'description' => $txn['description'],
                    'amount' => $txn['amount'],
                ];
            } else {
                $trueRevenue += $txn['amount'];
            }
        }

        return [
            'true_revenue' => round($trueRevenue, 2),
            'total_credits' => round($totalCredits, 2),
            'excluded_amount' => round($excludedAmount, 2),
            'excluded_count' => count($excludedItems),
            'revenue_ratio' => $totalCredits > 0 ? round(($trueRevenue / $totalCredits) * 100, 1) : 0,
        ];
    }

    /**
     * Calculate underwriting score after analysis
     */
    protected function calculateUnderwriting(MCAApplication $application): ?array
    {
        try {
            $result = $this->underwritingScoreService->calculateAndSave($application);

            if ($result['success']) {
                $this->addNote(
                    $application,
                    'underwriting',
                    "Underwriting score calculated: {$result['score']}/100 - {$result['decision']}"
                );

                return [
                    'score' => $result['score'],
                    'decision' => $result['decision'],
                    'flags_count' => count($result['flags'] ?? []),
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Underwriting score calculation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate FCS (Financial Capability Statement) report
     */
    public function generateFCSReport(MCAApplication $application, array $results, array $totals): array
    {
        $reportId = sprintf('APP%d_%05d_%s', $application->id, rand(10000, 99999), time());
        $pdfFilename = "fcs_app_{$application->id}_{$reportId}.pdf";

        $statementsData = [];
        foreach ($results as $result) {
            if ($result['success']) {
                $statementsData[] = [
                    'filename' => $result['filename'],
                    'statement_period' => $result['statement_period'] ?? 'N/A',
                    'credits' => $result['total_credits'],
                    'debits' => $result['total_debits'],
                    'true_revenue' => $result['true_revenue'],
                    'transactions' => $result['transactions'],
                ];
            }
        }

        $pdfData = [
            'report_id' => $reportId,
            'application_id' => $application->id,
            'business_name' => $application->business_name,
            'generated_at' => now()->format('F j, Y g:i A'),
            'analyst_name' => Auth::user()->name ?? 'System User',
            'statement_count' => count($statementsData),
            'total_credits' => round($totals['credits'], 2),
            'total_debits' => round($totals['debits'], 2),
            'true_revenue' => round($totals['true_revenue'], 2),
            'revenue_ratio' => $totals['credits'] > 0 ? round(($totals['true_revenue'] / $totals['credits']) * 100, 1) : 0,
            'statements' => $statementsData,
            'underwriting_score' => $application->underwriting_score,
            'underwriting_decision' => $application->underwriting_decision,
            'underwriting_details' => $application->underwriting_details,
        ];

        try {
            $pdf = Pdf::loadView('pdf.application-fcs', $pdfData);
            $pdf->setPaper('letter', 'portrait');

            $pdfPath = 'fcs_reports/' . $pdfFilename;
            Storage::put($pdfPath, $pdf->output());

            // Update documents with FCS path
            foreach ($results as $result) {
                if ($result['success']) {
                    ApplicationDocument::where('id', $result['document_id'])
                        ->update(['fcs_report_path' => $pdfPath]);
                }
            }

            Log::info("Bank Analysis: Generated FCS PDF: {$pdfFilename}");

            return [
                'url' => route('applications.download-fcs', [$application, $pdfFilename]),
                'filename' => $pdfFilename,
                'path' => $pdfPath,
            ];
        } catch (\Exception $e) {
            Log::error('Bank Analysis: Failed to generate FCS PDF: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Add note to application
     */
    protected function addNote(MCAApplication $application, string $type, string $content): void
    {
        $application->notes()->create([
            'user_id' => Auth::id(),
            'type' => $type,
            'content' => $content,
        ]);
    }
}
