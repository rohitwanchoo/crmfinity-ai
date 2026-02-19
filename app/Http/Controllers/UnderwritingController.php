<?php

namespace App\Http\Controllers;

use App\Services\BankStatementAnalyzerService;
use App\Services\DecisionAutomationService;
use App\Services\FraudDetectionService;
use App\Services\RiskScoringService;
use App\Services\TransactionParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UnderwritingController extends Controller
{
    protected TransactionParserService $transactionParser;

    protected BankStatementAnalyzerService $bankAnalyzer;

    protected FraudDetectionService $fraudDetector;

    protected RiskScoringService $riskScoring;

    public function __construct(
        TransactionParserService $transactionParser,
        BankStatementAnalyzerService $bankAnalyzer,
        FraudDetectionService $fraudDetector,
        RiskScoringService $riskScoring
    ) {
        $this->transactionParser = $transactionParser;
        $this->bankAnalyzer = $bankAnalyzer;
        $this->fraudDetector = $fraudDetector;
        $this->riskScoring = $riskScoring;
    }

    public function index()
    {
        return view('underwriting.index');
    }

    public function analyze(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string|max:255',
            'statement_pdfs.*' => 'required|file|mimes:pdf|max:10240',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'requested_amount' => 'nullable|numeric|min:0',
        ]);

        $allTransactions = [];
        $fileResults = [];
        $combinedText = '';
        $uploadPath = storage_path('app/uploads');

        // Ensure upload directory exists
        if (! file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Process each uploaded PDF
        if ($request->hasFile('statement_pdfs')) {
            foreach ($request->file('statement_pdfs') as $file) {
                $filename = 'stmt_'.Str::random(10).'.pdf';
                $filePath = $uploadPath.'/'.$filename;
                $file->move($uploadPath, $filename);

                try {
                    // Extract text from PDF using Python script
                    $scriptPath = storage_path('app/scripts/extract_pdf_text.py');
                    $command = '/var/www/html/crmfinity-ai/venv/bin/python3 '.escapeshellarg($scriptPath).' '.escapeshellarg($filePath).' true 2>&1';
                    $output = shell_exec($command);
                    $result = json_decode($output, true);

                    if ($result && $result['success']) {
                        $text = $result['text'];
                        $combinedText .= $text."\n\n";

                        // Parse transactions from the extracted text
                        $parsed = $this->transactionParser->parseTransactions($text, $request->bank_name);

                        $fileResults[] = [
                            'file' => $file->getClientOriginalName(),
                            'text_length' => strlen($text),
                            'pages' => $result['pages'] ?? 0,
                            'bank_detected' => $parsed['bank'],
                            'transactions_found' => count($parsed['transactions']),
                            'metadata' => $parsed['metadata'],
                            'summary' => $parsed['summary'],
                        ];

                        // Merge transactions
                        $allTransactions = array_merge($allTransactions, $parsed['transactions']);
                    } else {
                        $fileResults[] = [
                            'file' => $file->getClientOriginalName(),
                            'error' => 'Failed to extract text from PDF',
                            'details' => $result['error'] ?? 'Unknown error',
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('PDF processing error', [
                        'file' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ]);
                    $fileResults[] = [
                        'file' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ];
                }

                // Clean up temp file
                @unlink($filePath);
            }
        }

        // Perform comprehensive analysis if we have transactions
        $analysis = null;
        $fraudAnalysis = null;
        $riskAssessment = null;

        if (! empty($allTransactions)) {
            // Get monthly breakdown
            $monthlyBreakdown = $this->transactionParser->getMonthlyBreakdown($allTransactions);

            // Perform bank statement analysis
            $analysis = $this->bankAnalyzer->analyze($allTransactions, [
                'bank' => $request->bank_name,
            ]);

            // Run fraud detection
            $applicationData = [
                'monthly_revenue' => $request->monthly_revenue,
                'business_name' => $request->business_name ?? null,
            ];
            $fraudAnalysis = $this->fraudDetector->analyze($allTransactions, $applicationData);

            // If requested amount provided, perform full risk assessment
            if ($request->requested_amount) {
                $riskData = [
                    'bank_analysis' => $analysis,
                    'fraud_analysis' => $fraudAnalysis,
                    'monthly_revenue' => $request->monthly_revenue ?? $analysis['revenue_analysis']['average_monthly'] ?? 0,
                    'requested_amount' => $request->requested_amount,
                    'parsed_transactions' => $allTransactions,
                ];

                $riskAssessment = $this->riskScoring->performComprehensiveAssessment($riskData);
            }
        }

        // Calculate summary statistics
        $totalTransactions = count($allTransactions);
        $totalCredits = array_sum(array_map(
            fn ($t) => $t['type'] === 'credit' ? $t['amount'] : 0,
            $allTransactions
        ));
        $totalDebits = array_sum(array_map(
            fn ($t) => $t['type'] === 'debit' ? $t['amount'] : 0,
            $allTransactions
        ));

        return view('underwriting.results', [
            'fileResults' => $fileResults,
            'transactions' => $allTransactions,
            'totalTransactions' => $totalTransactions,
            'totalCredits' => $totalCredits,
            'totalDebits' => $totalDebits,
            'monthlyBreakdown' => $monthlyBreakdown ?? [],
            'analysis' => $analysis,
            'fraudAnalysis' => $fraudAnalysis,
            'riskAssessment' => $riskAssessment,
            'bankName' => $request->bank_name,
        ]);
    }

    /**
     * Quick risk check endpoint
     */
    public function quickCheck(Request $request)
    {
        $request->validate([
            'credit_score' => 'nullable|integer|min:300|max:850',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'active_mcas' => 'nullable|integer|min:0',
            'time_in_business_months' => 'nullable|integer|min:0',
        ]);

        $result = $this->riskScoring->quickRiskCheck($request->all());

        return response()->json($result);
    }

    /**
     * Analyze bank statement text (API endpoint)
     */
    public function analyzeText(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
            'bank_name' => 'nullable|string',
            'monthly_revenue' => 'nullable|numeric',
            'requested_amount' => 'nullable|numeric',
        ]);

        try {
            // Parse transactions
            $parsed = $this->transactionParser->parseTransactions(
                $request->text,
                $request->bank_name
            );

            if (empty($parsed['transactions'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No transactions could be parsed from the provided text',
                    'parsed' => $parsed,
                ], 422);
            }

            // Analyze transactions
            $analysis = $this->bankAnalyzer->analyze($parsed['transactions'], $parsed['metadata']);

            // Fraud detection
            $fraudAnalysis = $this->fraudDetector->analyze($parsed['transactions'], [
                'monthly_revenue' => $request->monthly_revenue,
            ]);

            // Build response
            $response = [
                'success' => true,
                'bank' => $parsed['bank'],
                'transaction_count' => count($parsed['transactions']),
                'summary' => $parsed['summary'],
                'analysis' => $analysis,
                'fraud_analysis' => $fraudAnalysis,
            ];

            // Full risk assessment if requested
            if ($request->requested_amount) {
                $riskData = [
                    'bank_analysis' => $analysis,
                    'fraud_analysis' => $fraudAnalysis,
                    'monthly_revenue' => $request->monthly_revenue ?? $analysis['revenue_analysis']['average_monthly'],
                    'requested_amount' => $request->requested_amount,
                ];
                $response['risk_assessment'] = $this->riskScoring->performComprehensiveAssessment($riskData);
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Bank statement analysis error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction categories
     */
    public function getCategories()
    {
        return response()->json([
            'categories' => [
                'card_processing' => 'Credit Card Processing',
                'deposit' => 'Customer Deposits',
                'transfer' => 'Transfers',
                'mca' => 'MCA Payments',
                'loan' => 'Loan Payments',
                'payroll' => 'Payroll',
                'nsf' => 'NSF/Overdraft',
                'fee' => 'Bank Fees',
                'other' => 'Other',
            ],
        ]);
    }

    /**
     * Process application through decision automation
     */
    public function autoDecision(Request $request, DecisionAutomationService $automation)
    {
        $request->validate([
            'application_id' => 'required|integer|exists:mca_applications,id',
        ]);

        $result = $automation->processApplication($request->application_id);

        return response()->json($result);
    }

    /**
     * Batch process applications
     */
    public function batchProcess(Request $request, DecisionAutomationService $automation)
    {
        $request->validate([
            'application_ids' => 'required|array',
            'application_ids.*' => 'integer|exists:mca_applications,id',
        ]);

        $result = $automation->processBatch($request->application_ids);

        return response()->json($result);
    }

    /**
     * Get automation statistics
     */
    public function automationStats(Request $request, DecisionAutomationService $automation)
    {
        $period = $request->get('period', '30d');
        $stats = $automation->getStatistics($period);

        return response()->json($stats);
    }
}
