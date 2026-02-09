<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Models\RevenueClassification;
use App\Models\McaPattern;
use App\Models\TransactionCategory;
use App\Services\NsfAndNegativeDaysCalculator;

class BankStatementController extends Controller
{
    /**
     * Display the bank statement analyzer index page.
     */
    public function index()
    {
        $stats = [
            'total_sessions' => AnalysisSession::whereIn('analysis_type', ['openai', 'claude'])->count(),
            'total_transactions' => AnalyzedTransaction::whereHas('session', function ($q) {
                $q->whereIn('analysis_type', ['openai', 'claude']);
            })->count(),
            'total_credits' => AnalysisSession::whereIn('analysis_type', ['openai', 'claude'])->sum('total_credits'),
            'total_debits' => AnalysisSession::whereIn('analysis_type', ['openai', 'claude'])->sum('total_debits'),
            'total_lenders' => McaPattern::distinct('lender_name')->count('lender_name'),
        ];

        // Get bank statistics
        $bankStats = AnalysisSession::whereIn('analysis_type', ['openai', 'claude'])
            ->whereNotNull('bank_name')
            ->where('bank_name', '!=', '')
            ->selectRaw('bank_name, COUNT(*) as count, SUM(total_transactions) as transactions')
            ->groupBy('bank_name')
            ->orderByDesc('count')
            ->get();

        $recentSessions = AnalysisSession::whereIn('analysis_type', ['openai', 'claude'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('bankstatement.index', compact('stats', 'recentSessions', 'bankStats'));
    }

    /**
     * Handle GET requests to /analyze - redirect to upload form.
     */
    public function analyzeGet()
    {
        return redirect()->route('bankstatement.index')
            ->with('info', 'Please upload a bank statement to analyze.');
    }

    /**
     * Analyze uploaded bank statement using OpenAI.
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'statements' => 'required|array|min:1',
            'statements.*' => 'required|file|mimes:pdf|max:20480',
            'model' => 'nullable|in:claude-opus-4-6,claude-sonnet-4-5,claude-haiku-4-5',
        ]);

        $results = [];
        $defaultModel = config('services.anthropic.default_model', 'claude-opus-4-6');
        $model = $request->input('model', $defaultModel);
        $apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');

        if (!$apiKey) {
            return back()->with('error', 'Anthropic API key not configured. Please add ANTHROPIC_API_KEY to your .env file.');
        }

        foreach ($request->file('statements') as $file) {
            $filename = $file->getClientOriginalName();
            $sessionId = Str::uuid()->toString();

            // Save the file
            $uploadPath = storage_path('app/uploads');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $savedPath = $uploadPath . '/' . $sessionId . '_' . $filename;
            $file->move($uploadPath, $sessionId . '_' . $filename);

            try {
                // Get learned corrections for AI training
                $corrections = \App\Models\TransactionCorrection::select('description_pattern', 'correct_type')
                    ->orderBy('usage_count', 'desc')
                    ->limit(50)
                    ->get()
                    ->toArray();
                $correctionsJson = json_encode($corrections);

                // Run Python script with corrections
                // Redirect stderr to a separate temp file so debug messages don't mix with JSON output
                $scriptPath = storage_path('app/scripts/bank_statement_extractor.py');
                $stderrFile = storage_path('logs/python_stderr_' . $sessionId . '.log');
                $command = sprintf(
                    'python3 %s %s %s %s %s 2>%s',
                    escapeshellarg($scriptPath),
                    escapeshellarg($savedPath),
                    escapeshellarg($apiKey),
                    escapeshellarg($model),
                    escapeshellarg($correctionsJson),
                    escapeshellarg($stderrFile)
                );

                $output = shell_exec($command);

                // Clean up: extract only the JSON part if there's any extra text
                // The JSON should be the last complete JSON object in the output
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
                    // Read stderr for debugging if JSON parsing failed
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
                $bankName = $data['metadata']['bank_name'] ?? $this->detectBankName($filename);

                // Save to database
                $session = AnalysisSession::create([
                    'session_id' => $sessionId,
                    'user_id' => auth()->id(),
                    'filename' => $filename,
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
                    'model_used' => $model,
                    'api_cost' => $data['api_cost']['total_cost'] ?? 0,
                    'beginning_balance' => isset($data['statement_summary']['beginning_balance']) ? $data['statement_summary']['beginning_balance'] : null,
                    'ending_balance' => isset($data['statement_summary']['ending_balance']) ? $data['statement_summary']['ending_balance'] : null,
                ]);

                // Save transactions
                \Log::info("Starting to save transactions", [
                    'session_id' => $sessionId,
                    'transaction_count' => count($data['transactions'] ?? []),
                ]);

                foreach ($data['transactions'] as $index => $txn) {
                    try {
                        $type = $txn['type'] ?? 'debit'; // Default to debit if not specified
                        $description = $txn['description'] ?? 'Unknown';

                        // Auto-assign category based on description
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

                            // Calculate beginning balance:
                            // For credits: beginning_balance = ending_balance - amount
                            // For debits: beginning_balance = ending_balance + amount
                            if ($type === 'credit') {
                                $transactionData['beginning_balance'] = $endingBalance - $amount;
                            } else {
                                $transactionData['beginning_balance'] = $endingBalance + $amount;
                            }
                        }

                        AnalyzedTransaction::create($transactionData);
                    } catch (\Exception $e) {
                        \Log::error("Failed to save transaction", [
                            'session_id' => $sessionId,
                            'transaction_index' => $index,
                            'transaction_data' => $txn,
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                        throw $e; // Re-throw to stop processing
                    }
                }

                \Log::info("Finished saving transactions", [
                    'session_id' => $sessionId,
                    'transactions_saved' => $session->transactions()->count(),
                ]);

                // Reload transactions from database to get IDs and any applied logic
                $transactionsWithIds = $session->transactions()
                    ->orderBy('transaction_date')
                    ->get()
                    ->map(function ($txn) {
                        // Category should already be set from creation, but ensure it's populated
                        $category = $txn->category;
                        if (!$category) {
                            $categoryData = TransactionCategory::getCategoryForDescription($txn->description, $txn->type);
                            if ($categoryData) {
                                $category = $categoryData['category'];
                                $txn->update(['category' => $category]);
                            }
                        }

                        return [
                            'id' => $txn->id,
                            'date' => $txn->transaction_date,
                            'description' => $txn->description,
                            'amount' => (float) $txn->amount,
                            'type' => $txn->type,
                            'original_type' => $txn->original_type,
                            'was_corrected' => $txn->was_corrected ?? false,
                            'is_mca_payment' => $txn->is_mca_payment ?? false,
                            'mca_lender' => $txn->mca_lender ?? null,
                            'category' => $category,
                            'ending_balance' => $txn->ending_balance !== null ? (float) $txn->ending_balance : null,
                            'beginning_balance' => $txn->beginning_balance !== null ? (float) $txn->beginning_balance : null,
                        ];
                    })
                    ->toArray();

                // Group transactions by month and calculate true revenue
                $monthlyData = $this->groupTransactionsByMonth($transactionsWithIds);

                // Recalculate summary from actual saved transactions (not Python output)
                $actualCredits = $session->transactions()->where('type', 'credit')->get();
                $actualDebits = $session->transactions()->where('type', 'debit')->get();

                $correctedSummary = [
                    'total_transactions' => $session->transactions()->count(),
                    'credit_count' => $actualCredits->count(),
                    'debit_count' => $actualDebits->count(),
                    'credit_total' => $actualCredits->sum('amount'),
                    'debit_total' => $actualDebits->sum('amount'),
                    'net_balance' => $actualCredits->sum('amount') - $actualDebits->sum('amount'),
                    'returned_count' => $data['summary']['returned_count'] ?? 0,
                    'returned_total' => $data['summary']['returned_total'] ?? 0,
                ];

                // Get MCA analysis from Python script output
                $mcaAnalysis = $data['mca_analysis'] ?? [
                    'total_mca_count' => 0,
                    'total_mca_payments' => 0,
                    'total_mca_amount' => 0,
                    'lenders' => []
                ];

                $results[] = [
                    'filename' => $filename,
                    'session_id' => $sessionId,
                    'success' => true,
                    'summary' => $correctedSummary,
                    'api_cost' => $data['api_cost'],
                    'transactions' => $transactionsWithIds,
                    'monthly_data' => $monthlyData,
                    'mca_analysis' => $mcaAnalysis,
                ];

            } catch (\Exception $e) {
                Log::error('Bank statement analysis failed', [
                    'file' => $filename,
                    'error' => $e->getMessage()
                ]);

                $results[] = [
                    'filename' => $filename,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Check if all failed
        $allFailed = collect($results)->every(fn($r) => !$r['success']);
        if ($allFailed) {
            return back()->with('error', 'All files failed to process. ' . ($results[0]['error'] ?? ''));
        }

        // Store results in session for viewing
        session()->put('analysis_results', $results);

        // Redirect to results page (Post-Redirect-Get pattern)
        return redirect()->route('bankstatement.view-results')->with('success', 'Analysis completed successfully!');
    }

    /**
     * View analysis results (GET route after POST redirect).
     */
    public function viewResults()
    {
        // Get results from session
        $results = session()->get('analysis_results');

        // If no results in session, redirect to index
        if (!$results) {
            return redirect()->route('bankstatement.index')
                ->with('error', 'No analysis results found. Please upload a new statement.');
        }

        // Clear the results from session after retrieving
        session()->forget('analysis_results');

        return view('bankstatement.results', compact('results'));
    }

    /**
     * View analysis history.
     */
    public function history()
    {
        $sessions = AnalysisSession::whereIn('analysis_type', ['openai', 'claude'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('bankstatement.history', compact('sessions'));
    }

    /**
     * View a specific session.
     */
    public function session(Request $request, $sessionId)
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->whereIn('analysis_type', ['openai', 'claude'])
            ->firstOrFail();

        $transactions = $session->transactions()->orderBy('transaction_date')->get();

        $credits = $transactions->where('type', 'credit');
        $debits = $transactions->where('type', 'debit');

        // Get related sessions for linking back to full analysis
        $relatedSessions = $request->input('related', $sessionId);

        return view('bankstatement.session', compact('session', 'transactions', 'credits', 'debits', 'relatedSessions'));
    }

    /**
     * View updated analysis results for one or more sessions.
     * This reloads the data from the database with any corrections applied.
     */
    public function viewAnalysis(Request $request)
    {
        $sessionIds = $request->input('sessions', []);

        if (empty($sessionIds)) {
            return redirect()->route('bankstatement.history')->with('error', 'No sessions specified.');
        }

        // Ensure it's an array
        if (!is_array($sessionIds)) {
            $sessionIds = [$sessionIds];
        }

        $results = [];

        foreach ($sessionIds as $sessionId) {
            $session = AnalysisSession::where('session_id', $sessionId)
                ->whereIn('analysis_type', ['openai', 'claude'])
                ->first();

            if (!$session) {
                continue;
            }

            // Get transactions from database (with any corrections applied)
            $transactions = $session->transactions()
                ->orderBy('transaction_date')
                ->get()
                ->map(function ($txn) {
                    // Auto-assign category if not already set
                    $category = $txn->category;
                    if (!$category) {
                        $categoryData = TransactionCategory::getCategoryForDescription($txn->description, $txn->type);
                        if ($categoryData) {
                            $category = $categoryData['category'];
                            // Update the database with the auto-assigned category
                            $txn->update(['category' => $category]);
                        }
                    }

                    return [
                        'id' => $txn->id,
                        'date' => $txn->transaction_date,
                        'description' => $txn->description,
                        'amount' => (float) $txn->amount,
                        'type' => $txn->type,
                        'original_type' => $txn->original_type,
                        'was_corrected' => $txn->was_corrected,
                        'is_mca_payment' => $txn->is_mca_payment ?? false,
                        'mca_lender' => $txn->mca_lender_name ?? null,
                        'mca_lender_id' => $txn->mca_lender_id ?? null,
                        'category' => $category,
                        'ending_balance' => $txn->ending_balance !== null ? (float) $txn->ending_balance : null,
                        'beginning_balance' => $txn->beginning_balance !== null ? (float) $txn->beginning_balance : null,
                    ];
                })
                ->toArray();

            // Recalculate monthly data with fresh transactions
            $monthlyData = $this->groupTransactionsByMonth($transactions, $session);

            // Collect all processed transactions from monthly data (these have MCA funding flags set)
            $processedTransactions = [];
            foreach ($monthlyData['months'] as $month) {
                foreach ($month['transactions'] as $txn) {
                    $processedTransactions[] = $txn;
                }
            }

            // Recalculate MCA analysis from transactions (debits - payments made)
            $mcaAnalysis = $this->detectMcaPayments($processedTransactions);

            // Detect MCA funding from credits (funding received) - use processed transactions
            $mcaFunding = $this->detectMcaFunding($processedTransactions);

            // Merge funding data into MCA analysis
            $mcaAnalysis = $this->mergeMcaFundingWithPayments($mcaAnalysis, $mcaFunding);

            // Recalculate summary from fresh data
            $credits = collect($transactions)->where('type', 'credit');
            $debits = collect($transactions)->where('type', 'debit');
            $returned = collect($transactions)->where('type', 'returned');

            $results[] = [
                'filename' => $session->filename,
                'session_id' => $session->session_id,
                'success' => true,
                'summary' => [
                    'total_transactions' => count($transactions),
                    'credit_count' => $credits->count(),
                    'debit_count' => $debits->count(),
                    'returned_count' => $returned->count(),
                    'credit_total' => $credits->sum('amount'),
                    'debit_total' => $debits->sum('amount'),
                    'returned_total' => $returned->sum('amount'),
                    'net_balance' => $credits->sum('amount') - $debits->sum('amount'),
                ],
                'api_cost' => [
                    'total_cost' => $session->api_cost ?? 0,
                    'total_tokens' => 0,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                ],
                'transactions' => $transactions,
                'monthly_data' => $monthlyData,
                'mca_analysis' => $mcaAnalysis,
            ];
        }

        if (empty($results)) {
            return redirect()->route('bankstatement.history')->with('error', 'No valid sessions found.');
        }

        // Get all known MCA lenders (static + dynamic from database)
        $mcaLenders = McaPattern::getKnownLenders();

        // Add custom lenders from database
        $customLenders = \App\Models\McaPattern::where('is_mca', true)
            ->select('lender_id', 'lender_name')
            ->distinct()
            ->get();

        foreach ($customLenders as $lender) {
            if (!isset($mcaLenders[$lender->lender_id])) {
                $mcaLenders[$lender->lender_id] = $lender->lender_name;
            }
        }

        return view('bankstatement.results', compact('results', 'mcaLenders'));
    }

    /**
     * Detect MCA payments from transactions.
     */
    private function detectMcaPayments(array $transactions): array
    {
        $mcaLenders = [
            'ondeck' => ['name' => 'OnDeck Capital', 'patterns' => ['ondeck', 'on deck']],
            'kabbage' => ['name' => 'Kabbage', 'patterns' => ['kabbage']],
            'fundbox' => ['name' => 'Fundbox', 'patterns' => ['fundbox']],
            'bluevine' => ['name' => 'BlueVine', 'patterns' => ['bluevine', 'blue vine']],
            'credibly' => ['name' => 'Credibly', 'patterns' => ['credibly']],
            'kapitus' => ['name' => 'Kapitus', 'patterns' => ['kapitus']],
            'rapid_finance' => ['name' => 'Rapid Finance', 'patterns' => ['rapid finance', 'rapidfinance']],
            'can_capital' => ['name' => 'CAN Capital', 'patterns' => ['can capital']],
            'square_capital' => ['name' => 'Square Capital', 'patterns' => ['square capital', 'sq capital']],
            'paypal_working' => ['name' => 'PayPal Working Capital', 'patterns' => ['paypal working', 'paypal wc']],
            'amazon_lending' => ['name' => 'Amazon Lending', 'patterns' => ['amazon lending']],
            'shopify_capital' => ['name' => 'Shopify Capital', 'patterns' => ['shopify capital']],
            'stripe_capital' => ['name' => 'Stripe Capital', 'patterns' => ['stripe capital']],
            'clearco' => ['name' => 'Clearco', 'patterns' => ['clearco', 'clearbanc']],
            'libertas' => ['name' => 'Libertas Funding', 'patterns' => ['libertas']],
            'forward_financing' => ['name' => 'Forward Financing', 'patterns' => ['forward financing']],
            'fora_financial' => ['name' => 'Fora Financial', 'patterns' => ['fora financial']],
            'national_funding' => ['name' => 'National Funding', 'patterns' => ['national funding']],
            'bizfi' => ['name' => 'BizFi', 'patterns' => ['bizfi', 'biz fi']],
            'merchant_market' => ['name' => 'Merchant Market', 'patterns' => ['merchant market']],
        ];

        $mcaPayments = [];

        foreach ($transactions as $txn) {
            if (($txn['type'] ?? '') !== 'debit') {
                continue;
            }

            $description = $txn['description'] ?? '';
            $descriptionLower = strtolower($description);
            $matchedLenderId = null;
            $matchedLenderName = null;

            // First, check learned patterns from McaPattern model
            if (! McaPattern::isExcluded($description)) {
                $learnedMatch = McaPattern::checkMcaPattern($description);
                if ($learnedMatch) {
                    $matchedLenderId = $learnedMatch['lender_id'];
                    $matchedLenderName = $learnedMatch['lender_name'];
                }
            }

            // If no learned match, check hardcoded patterns
            if (! $matchedLenderId) {
                foreach ($mcaLenders as $lenderId => $lenderInfo) {
                    foreach ($lenderInfo['patterns'] as $pattern) {
                        if (stripos($descriptionLower, $pattern) !== false) {
                            $matchedLenderId = $lenderId;
                            $matchedLenderName = $lenderInfo['name'];
                            break 2;
                        }
                    }
                }
            }

            // If we found a match, add to MCA payments
            if ($matchedLenderId) {
                if (!isset($mcaPayments[$matchedLenderId])) {
                    $mcaPayments[$matchedLenderId] = [
                        'lender_id' => $matchedLenderId,
                        'lender_name' => $matchedLenderName,
                        'payment_count' => 0,
                        'total_amount' => 0,
                        'average_payment' => 0,
                        'unique_amounts' => [],
                        'frequency' => 'unknown',
                        'frequency_label' => 'Unknown',
                        'first_payment' => null,
                        'last_payment' => null,
                        'payments' => [],
                    ];
                }

                $amount = (float) ($txn['amount'] ?? 0);
                $mcaPayments[$matchedLenderId]['payment_count']++;
                $mcaPayments[$matchedLenderId]['total_amount'] += $amount;
                $mcaPayments[$matchedLenderId]['payments'][] = [
                    'date' => $txn['date'] ?? '',
                    'amount' => $amount,
                    'description' => $txn['description'] ?? '',
                ];

                if (!in_array($amount, $mcaPayments[$matchedLenderId]['unique_amounts'])) {
                    $mcaPayments[$matchedLenderId]['unique_amounts'][] = $amount;
                }
            }
        }

        // Calculate averages and frequency for each lender
        $totalMcaCount = count($mcaPayments);
        $totalMcaPayments = 0;
        $totalMcaAmount = 0;

        foreach ($mcaPayments as &$lender) {
            $lender['average_payment'] = $lender['payment_count'] > 0
                ? $lender['total_amount'] / $lender['payment_count']
                : 0;

            if (!empty($lender['payments'])) {
                usort($lender['payments'], fn($a, $b) => strcmp($a['date'], $b['date']));
                $lender['first_payment'] = $lender['payments'][0];
                $lender['last_payment'] = end($lender['payments']);

                // Calculate frequency
                if ($lender['payment_count'] >= 2) {
                    $firstDate = strtotime($lender['first_payment']['date']);
                    $lastDate = strtotime($lender['last_payment']['date']);
                    $daysDiff = ($lastDate - $firstDate) / 86400;
                    $avgDaysBetween = $daysDiff / ($lender['payment_count'] - 1);

                    if ($avgDaysBetween <= 1.5) {
                        $lender['frequency'] = 'daily';
                        $lender['frequency_label'] = 'Daily';
                    } elseif ($avgDaysBetween <= 3) {
                        $lender['frequency'] = 'every_other_day';
                        $lender['frequency_label'] = 'Every Other Day';
                    } elseif ($avgDaysBetween <= 5) {
                        $lender['frequency'] = 'twice_weekly';
                        $lender['frequency_label'] = 'Twice Weekly';
                    } elseif ($avgDaysBetween <= 9) {
                        $lender['frequency'] = 'weekly';
                        $lender['frequency_label'] = 'Weekly';
                    } elseif ($avgDaysBetween <= 18) {
                        $lender['frequency'] = 'bi_weekly';
                        $lender['frequency_label'] = 'Bi-Weekly';
                    } elseif ($avgDaysBetween <= 35) {
                        $lender['frequency'] = 'monthly';
                        $lender['frequency_label'] = 'Monthly';
                    } else {
                        $lender['frequency'] = 'irregular';
                        $lender['frequency_label'] = 'Irregular';
                    }
                } else {
                    $lender['frequency'] = 'single_payment';
                    $lender['frequency_label'] = 'Single Payment';
                }
            }

            // Keep payments data for view toggle in UI
            // unset($lender['payments']);
            $totalMcaPayments += $lender['payment_count'];
            $totalMcaAmount += $lender['total_amount'];
        }

        return [
            'total_mca_count' => $totalMcaCount,
            'total_mca_payments' => $totalMcaPayments,
            'total_mca_amount' => $totalMcaAmount,
            'lenders' => array_values($mcaPayments),
        ];
    }

    /**
     * Detect MCA funding from credit transactions.
     * This identifies funding received from MCA lenders.
     */
    private function detectMcaFunding(array $transactions): array
    {
        $mcaFunding = [];

        foreach ($transactions as $txn) {
            if (($txn['type'] ?? '') !== 'credit') {
                continue;
            }

            // Check if this credit is marked as MCA funding
            $isMcaFunding = $txn['is_mca_funding'] ?? false;
            $mcaLenderId = $txn['mca_funding_lender_id'] ?? null;
            $mcaLenderName = $txn['mca_funding_lender_name'] ?? null;

            // If not explicitly marked, check learned patterns
            if (!$isMcaFunding) {
                $learnedClassification = RevenueClassification::getFullClassification($txn['description'] ?? '');
                if ($learnedClassification && $learnedClassification['is_mca_funding']) {
                    $isMcaFunding = true;
                    $mcaLenderId = $learnedClassification['mca_lender_id'];
                    $mcaLenderName = $learnedClassification['mca_lender_name'];
                }
            }

            if ($isMcaFunding && $mcaLenderId) {
                if (!isset($mcaFunding[$mcaLenderId])) {
                    $mcaFunding[$mcaLenderId] = [
                        'lender_id' => $mcaLenderId,
                        'lender_name' => $mcaLenderName ?? 'Unknown Lender',
                        'funding_count' => 0,
                        'total_funding' => 0,
                        'funding_transactions' => [],
                    ];
                }

                $amount = (float) ($txn['amount'] ?? 0);
                $mcaFunding[$mcaLenderId]['funding_count']++;
                $mcaFunding[$mcaLenderId]['total_funding'] += $amount;
                $mcaFunding[$mcaLenderId]['funding_transactions'][] = [
                    'date' => $txn['date'] ?? '',
                    'amount' => $amount,
                    'description' => $txn['description'] ?? '',
                ];
            }
        }

        return [
            'total_funding_count' => array_sum(array_column($mcaFunding, 'funding_count')),
            'total_funding_amount' => array_sum(array_column($mcaFunding, 'total_funding')),
            'lenders' => $mcaFunding,
        ];
    }

    /**
     * Merge MCA funding data with MCA payment data.
     * This creates a unified view of obligations per lender.
     */
    private function mergeMcaFundingWithPayments(array $mcaPayments, array $mcaFunding): array
    {
        $mergedLenders = [];

        // First, add all lenders from payments
        foreach ($mcaPayments['lenders'] as $lender) {
            $lid = $lender['lender_id'];
            $mergedLenders[$lid] = $lender;
            $mergedLenders[$lid]['funding_count'] = 0;
            $mergedLenders[$lid]['total_funding'] = 0;
            $mergedLenders[$lid]['has_funding'] = false;
        }

        // Then, merge in funding data
        foreach ($mcaFunding['lenders'] as $lid => $fundingLender) {
            if (isset($mergedLenders[$lid])) {
                // Lender exists from payments, add funding info
                $mergedLenders[$lid]['funding_count'] = $fundingLender['funding_count'];
                $mergedLenders[$lid]['total_funding'] = $fundingLender['total_funding'];
                $mergedLenders[$lid]['has_funding'] = true;
            } else {
                // New lender only from funding (no payments yet)
                $mergedLenders[$lid] = [
                    'lender_id' => $lid,
                    'lender_name' => $fundingLender['lender_name'],
                    'payment_count' => 0,
                    'total_amount' => 0,
                    'average_payment' => 0,
                    'unique_amounts' => [],
                    'frequency' => 'no_payments',
                    'frequency_label' => 'No Payments Yet',
                    'first_payment' => null,
                    'last_payment' => null,
                    'funding_count' => $fundingLender['funding_count'],
                    'total_funding' => $fundingLender['total_funding'],
                    'has_funding' => true,
                ];
            }
        }

        // Recalculate totals
        $totalMcaCount = count($mergedLenders);
        $totalMcaPayments = 0;
        $totalMcaAmount = 0;
        $totalFundingCount = 0;
        $totalFundingAmount = 0;

        foreach ($mergedLenders as $lender) {
            $totalMcaPayments += $lender['payment_count'];
            $totalMcaAmount += $lender['total_amount'];
            $totalFundingCount += $lender['funding_count'] ?? 0;
            $totalFundingAmount += $lender['total_funding'] ?? 0;
        }

        return [
            'total_mca_count' => $totalMcaCount,
            'total_mca_payments' => $totalMcaPayments,
            'total_mca_amount' => $totalMcaAmount,
            'total_funding_count' => $totalFundingCount,
            'total_funding_amount' => $totalFundingAmount,
            'lenders' => array_values($mergedLenders),
        ];
    }

    /**
     * Download transactions as CSV.
     */
    public function downloadCsv($sessionId)
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->whereIn('analysis_type', ['openai', 'claude'])
            ->firstOrFail();

        $transactions = $session->transactions()->orderBy('transaction_date')->get();

        $filename = 'transactions_' . $sessionId . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Description', 'Amount', 'Type']);

            foreach ($transactions as $txn) {
                fputcsv($file, [
                    $txn->transaction_date,
                    $txn->description,
                    $txn->amount,
                    $txn->type,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Toggle transaction type (debit <-> credit) and save correction for AI learning.
     */
    public function toggleType(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer',
        ]);

        $transaction = AnalyzedTransaction::findOrFail($request->transaction_id);

        $oldType = $transaction->type;
        $newType = $oldType === 'credit' ? 'debit' : 'credit';

        // Update the transaction
        $transaction->update([
            'type' => $newType,
            'was_corrected' => true,
            'confidence' => 1.0,
            'confidence_label' => 'high',
        ]);

        // Save correction for AI learning
        \App\Models\TransactionCorrection::recordCorrection(
            $transaction->description,
            $oldType,
            $newType,
            $transaction->amount,
            auth()->id()
        );

        // Update session totals
        $session = $transaction->session;
        if ($session) {
            $amount = (float) $transaction->amount;

            if ($oldType === 'credit' && $newType === 'debit') {
                $session->total_credits -= $amount;
                $session->total_debits += $amount;
            } else {
                $session->total_debits -= $amount;
                $session->total_credits += $amount;
            }
            $session->net_flow = $session->total_credits - $session->total_debits;
            $session->save();
        }

        Log::info("BankStatement Correction: '{$transaction->description}' changed from {$oldType} to {$newType}");

        return response()->json([
            'success' => true,
            'message' => 'Transaction type updated. AI will learn from this correction.',
            'new_type' => $newType,
            'session_totals' => [
                'total_credits' => $session ? number_format($session->total_credits, 2) : 0,
                'total_debits' => $session ? number_format($session->total_debits, 2) : 0,
                'net_flow' => $session ? number_format($session->net_flow, 2) : 0,
            ],
        ]);
    }

    /**
     * Toggle revenue classification (true_revenue <-> adjustment) and save for AI learning.
     */
    public function toggleRevenue(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer',
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'current_classification' => 'required|in:true_revenue,adjustment',
            'is_mca_funding' => 'nullable|boolean',
            'mca_lender_id' => 'nullable|string',
            'mca_lender_name' => 'nullable|string',
            'transaction_ids' => 'nullable|array',
            'transaction_ids.*' => 'integer',
        ]);

        $newClassification = $request->current_classification === 'true_revenue' ? 'adjustment' : 'true_revenue';
        $isMcaFunding = (bool) $request->input('is_mca_funding', false);
        $mcaLenderId = $request->input('mca_lender_id');
        $mcaLenderName = $request->input('mca_lender_name');

        // Determine the adjustment reason
        $adjustmentReason = null;
        if ($newClassification === 'adjustment') {
            $adjustmentReason = $isMcaFunding ? 'mca_funding' : 'user_marked';
        }

        // Save to revenue classifications table for AI learning
        RevenueClassification::recordClassification(
            $request->description,
            $newClassification,
            $adjustmentReason,
            auth()->id(),
            $isMcaFunding,
            $mcaLenderId,
            $mcaLenderName
        );

        // Update specified transactions or find matching ones
        $transactionIds = $request->input('transaction_ids', []);
        $updatedCount = 0;

        if (!empty($transactionIds)) {
            // Update only the specified transaction IDs
            $updatedCount = AnalyzedTransaction::whereIn('id', $transactionIds)
                ->where('type', 'credit')
                ->update([
                    'is_mca_payment' => $isMcaFunding,
                    'mca_lender_id' => $isMcaFunding ? $mcaLenderId : null,
                    'mca_lender_name' => $isMcaFunding ? $mcaLenderName : null,
                ]);
        } else {
            // Fallback: Update by description pattern (old behavior)
            $normalizedPattern = \App\Models\RevenueClassification::normalizePattern($request->description);
            $transactions = AnalyzedTransaction::where('type', 'credit')->get();
            $matchingIds = [];

            foreach ($transactions as $txn) {
                $txnNormalized = \App\Models\RevenueClassification::normalizePattern($txn->description);
                if ($txnNormalized === $normalizedPattern || $txn->description === $request->description) {
                    $matchingIds[] = $txn->id;
                }
            }

            if (!empty($matchingIds)) {
                $updatedCount = AnalyzedTransaction::whereIn('id', $matchingIds)
                    ->update([
                        'is_mca_payment' => $isMcaFunding,
                        'mca_lender_id' => $isMcaFunding ? $mcaLenderId : null,
                        'mca_lender_name' => $isMcaFunding ? $mcaLenderName : null,
                    ]);
            }
        }

        $message = "Marked {$updatedCount} transaction(s) as " . str_replace('_', ' ', $newClassification);
        if ($isMcaFunding && $mcaLenderName) {
            $message .= " (MCA Funding from {$mcaLenderName})";
        }
        $message .= ". AI will learn from this.";

        Log::info("BankStatement Revenue Classification: '{$request->description}' marked as {$newClassification}" . ($isMcaFunding ? " (MCA Funding from {$mcaLenderName})" : "") . " - Updated {$updatedCount} transactions");

        return response()->json([
            'success' => true,
            'message' => $message,
            'new_classification' => $newClassification,
            'amount' => (float) $request->amount,
            'is_mca_funding' => $isMcaFunding,
            'mca_lender_id' => $mcaLenderId,
            'mca_lender_name' => $mcaLenderName,
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Toggle MCA status for a debit transaction and save for AI learning.
     */
    public function toggleMca(Request $request)
    {
        $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'is_mca' => 'required|boolean',
            'lender_id' => 'required_if:is_mca,true|nullable|string',
            'lender_name' => 'required_if:is_mca,true|nullable|string',
            'transaction_ids' => 'nullable|array',
            'transaction_ids.*' => 'integer',
        ]);

        $isMca = (bool) $request->is_mca;
        $lenderId = $request->lender_id ?? 'unknown';
        $lenderName = $request->lender_name ?? 'Unknown Lender';
        $transactionIds = $request->input('transaction_ids', []);

        // Save to MCA patterns table for AI learning
        McaPattern::recordPattern(
            $request->description,
            $lenderId,
            $lenderName,
            $isMca,
            auth()->id()
        );

        // Update specified transactions or find matching ones
        $updatedCount = 0;
        if (!empty($transactionIds)) {
            // Update only the specified transaction IDs
            $updatedCount = AnalyzedTransaction::whereIn('id', $transactionIds)
                ->where('type', 'debit')
                ->update([
                    'is_mca_payment' => $isMca,
                    'mca_lender_id' => $isMca ? $lenderId : null,
                    'mca_lender_name' => $isMca ? $lenderName : null,
                ]);
        } else {
            // Fallback: Update by description pattern (old behavior)
            $normalizedPattern = McaPattern::normalizePattern($request->description);
            $transactions = AnalyzedTransaction::where('type', 'debit')->get();
            $matchingIds = [];

            foreach ($transactions as $txn) {
                $txnNormalized = McaPattern::normalizePattern($txn->description);
                if ($txnNormalized === $normalizedPattern || $txn->description === $request->description) {
                    $matchingIds[] = $txn->id;
                }
            }

            if (!empty($matchingIds)) {
                $updatedCount = AnalyzedTransaction::whereIn('id', $matchingIds)
                    ->update([
                        'is_mca_payment' => $isMca,
                        'mca_lender_id' => $isMca ? $lenderId : null,
                        'mca_lender_name' => $isMca ? $lenderName : null,
                    ]);
            }
        }

        $message = $isMca
            ? "Marked {$updatedCount} transaction(s) as MCA payment from {$lenderName}. AI will learn from this."
            : "Removed {$updatedCount} transaction(s) from MCA payments. AI will learn from this.";

        Log::info("BankStatement MCA Classification: '{$request->description}' marked as " . ($isMca ? "MCA ({$lenderName})" : "Not MCA") . " - Updated {$updatedCount} transactions");

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_mca' => $isMca,
            'lender_id' => $lenderId,
            'lender_name' => $lenderName,
            'amount' => (float) $request->amount,
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Find similar transactions based on normalized pattern matching
     */
    public function findSimilarTransactions(Request $request)
    {
        $request->validate([
            'description' => 'required|string',
            'type' => 'required|in:credit,debit',
            'session_ids' => 'nullable|array',
        ]);

        $normalizedPattern = McaPattern::normalizePattern($request->description);
        $sessionIds = $request->input('session_ids', []);

        // Build query for transactions of the specified type
        $query = AnalyzedTransaction::where('type', $request->type);

        // Filter by session IDs if provided
        if (!empty($sessionIds)) {
            $sessionDbIds = AnalysisSession::whereIn('session_id', $sessionIds)->pluck('id');
            $query->whereIn('analysis_session_id', $sessionDbIds);
        }

        $transactions = $query->get();
        $matchingTransactions = [];

        foreach ($transactions as $txn) {
            $txnNormalized = McaPattern::normalizePattern($txn->description);

            // Check if normalized patterns match
            if ($txnNormalized === $normalizedPattern) {
                $matchingTransactions[] = [
                    'id' => $txn->id,
                    'date' => $txn->transaction_date->format('Y-m-d'),
                    'description' => $txn->description,
                    'amount' => (float) $txn->amount,
                    'is_mca_payment' => $txn->is_mca_payment,
                    'mca_lender_name' => $txn->mca_lender_name,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'matching_transactions' => $matchingTransactions,
            'count' => count($matchingTransactions),
        ]);
    }

    /**
     * Toggle transaction category classification
     */
    public function toggleCategory(Request $request)
    {
        $request->validate([
            'transaction_id' => 'nullable|integer',
            'transaction_ids' => 'nullable|array',
            'transaction_ids.*' => 'integer',
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'type' => 'required|in:credit,debit,returned',
            'category' => 'nullable|string',
            'subcategory' => 'nullable|string',
            'update_single_only' => 'nullable|boolean',
        ]);

        $category = $request->category;
        $subcategory = $request->subcategory;
        $transactionType = $request->type;
        $updateSingleOnly = $request->update_single_only ?? false;
        $transactionIds = $request->input('transaction_ids', []);

        // If category is null, clear the category
        if ($category === null) {
            // Update the actual transaction record if transaction_id or transaction_ids are provided
            if (!empty($transactionIds)) {
                $updatedCount = AnalyzedTransaction::whereIn('id', $transactionIds)
                    ->update(['category' => null]);
                Log::info("Cleared category for {$updatedCount} transaction(s)");
            } elseif ($request->transaction_id) {
                $transaction = AnalyzedTransaction::find($request->transaction_id);
                if ($transaction) {
                    $transaction->update([
                        'category' => null,
                    ]);
                    Log::info("Transaction {$transaction->id} category cleared");
                } else {
                    Log::warning("Transaction ID {$request->transaction_id} not found");
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Category cleared successfully',
                'category' => null,
                'category_label' => null,
            ]);
        }

        // Validate category exists in standard categories
        $standardCategories = TransactionCategory::getStandardCategories();
        if (!isset($standardCategories[$category])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid category selected.',
            ], 400);
        }

        // Normalize the description pattern for matching similar transactions
        $normalizedPattern = TransactionCategory::normalizePattern($request->description);

        // Update transactions - priority: transaction_ids array > single transaction > all matching
        if (!empty($transactionIds)) {
            // Update only the specific transactions by IDs
            $updatedCount = AnalyzedTransaction::whereIn('id', $transactionIds)
                ->update(['category' => $category]);

            Log::info("Batch category update by IDs: {$updatedCount} transaction(s) updated to category: {$category}");
        } elseif ($updateSingleOnly && $request->transaction_id) {
            // Update only the specific transaction
            $transaction = AnalyzedTransaction::find($request->transaction_id);
            if ($transaction) {
                $transaction->update(['category' => $category]);
                $updatedCount = 1;
                Log::info("Single transaction update: Transaction {$transaction->id} updated to category: {$category}");
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found.',
                ], 404);
            }
        } else {
            // Find and update ALL transactions with the same description pattern
            $updatedCount = AnalyzedTransaction::whereRaw('LOWER(description) = ?', [strtolower($request->description)])
                ->update(['category' => $category]);

            Log::info("Batch category update: {$updatedCount} transaction(s) with description '{$request->description}' updated to category: {$category}");
        }

        // Save to transaction_categories table for AI learning
        TransactionCategory::recordCategory(
            $request->description,
            $category,
            $subcategory,
            $transactionType,
            auth()->id()
        );

        $categoryLabel = $standardCategories[$category]['label'];
        $message = $updatedCount > 1
            ? "Updated {$updatedCount} transactions with matching description to '{$categoryLabel}'. AI will learn from this."
            : "Transaction classified as '{$categoryLabel}'. AI will learn from this.";

        Log::info("BankStatement Category Classification: '{$request->description}' classified as {$category}");

        return response()->json([
            'success' => true,
            'message' => $message,
            'category' => $category,
            'category_label' => $categoryLabel,
            'subcategory' => $subcategory,
            'amount' => (float) $request->amount,
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Get available transaction categories
     */
    public function getCategories()
    {
        $categories = TransactionCategory::getStandardCategories();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Batch classify multiple transactions as MCA funding or payments.
     */
    public function batchClassify(Request $request)
    {
        $request->validate([
            'transactions' => 'required|array|min:1',
            'transactions.*.description' => 'required|string',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.type' => 'required|in:credit,debit,returned',
            'transactions.*.uniqueId' => 'required|string',
            'transactions.*.sessionId' => 'required|string',
            'transactions.*.monthKey' => 'required|string',
            'mca_lender_id' => 'required|string',
            'mca_lender_name' => 'required|string',
        ]);

        $transactions = $request->input('transactions');
        $lenderId = $request->input('mca_lender_id');
        $lenderName = $request->input('mca_lender_name');
        $results = [];
        $creditCount = 0;
        $debitCount = 0;

        foreach ($transactions as $txn) {
            $description = $txn['description'];
            $amount = (float) $txn['amount'];
            $type = $txn['type'];

            if ($type === 'credit') {
                // Mark as MCA Funding (adjustment)
                RevenueClassification::recordClassification(
                    $description,
                    'adjustment',
                    'mca_funding',
                    auth()->id(),
                    true,
                    $lenderId,
                    $lenderName
                );
                $creditCount++;
            } else {
                // Mark as MCA Payment
                McaPattern::recordPattern(
                    $description,
                    $lenderId,
                    $lenderName,
                    true,
                    auth()->id()
                );
                $debitCount++;
            }

            $results[] = [
                'uniqueId' => $txn['uniqueId'],
                'sessionId' => $txn['sessionId'],
                'monthKey' => $txn['monthKey'],
                'monthIndex' => $txn['monthIndex'] ?? 0,
                'txnIndex' => $txn['txnIndex'] ?? 0,
                'description' => $description,
                'amount' => $amount,
                'type' => $type,
                'mca_lender_id' => $lenderId,
                'mca_lender_name' => $lenderName,
                'success' => true,
            ];
        }

        Log::info("BankStatement Batch Classification: {$creditCount} credits and {$debitCount} debits marked as MCA for {$lenderName}");

        return response()->json([
            'success' => true,
            'message' => "Marked {$creditCount} funding(s) and {$debitCount} payment(s) for {$lenderName}",
            'processed_count' => count($results),
            'credit_count' => $creditCount,
            'debit_count' => $debitCount,
            'results' => $results,
        ]);
    }

    /**
     * Get list of known MCA lenders for dropdown.
     */
    public function getMcaLenders()
    {
        return response()->json([
            'lenders' => McaPattern::getKnownLenders(),
        ]);
    }

    /**
     * Display all MCA lenders in the system.
     */
    public function lenders()
    {
        // Get detected entries from database
        $detectedEntries = McaPattern::select('lender_id', 'lender_name')
            ->selectRaw('COUNT(*) as pattern_count')
            ->selectRaw('SUM(usage_count) as total_usage')
            ->selectRaw('MAX(updated_at) as last_used')
            ->where('is_mca', true)
            ->groupBy('lender_id', 'lender_name')
            ->get()
            ->keyBy('lender_id');

        // Get all known lenders and debt collectors
        $knownLenders = McaPattern::getKnownLenders();
        $knownDebtCollectors = McaPattern::getKnownDebtCollectors();

        // Build complete lenders list (all known + any detected that aren't in known list)
        $lenders = collect();
        foreach ($knownLenders as $id => $name) {
            if ($detectedEntries->has($id)) {
                $lenders->push($detectedEntries->get($id));
            } else {
                $lenders->push((object) [
                    'lender_id' => $id,
                    'lender_name' => $name,
                    'pattern_count' => 0,
                    'total_usage' => 0,
                    'last_used' => null,
                ]);
            }
        }
        // Add any detected lenders not in the known list
        foreach ($detectedEntries as $id => $entry) {
            if (!isset($knownLenders[$id]) && !isset($knownDebtCollectors[$id])) {
                $lenders->push($entry);
            }
        }
        $lenders = $lenders->sortBy('lender_name')->values();

        // Build complete debt collectors list
        $debtCollectors = collect();
        foreach ($knownDebtCollectors as $id => $name) {
            if ($detectedEntries->has($id)) {
                $debtCollectors->push($detectedEntries->get($id));
            } else {
                $debtCollectors->push((object) [
                    'lender_id' => $id,
                    'lender_name' => $name,
                    'pattern_count' => 0,
                    'total_usage' => 0,
                    'last_used' => null,
                ]);
            }
        }
        $debtCollectors = $debtCollectors->sortBy('lender_name')->values();

        $stats = [
            'total_lenders' => $lenders->count(),
            'total_debt_collectors' => $debtCollectors->count(),
            'total_patterns' => McaPattern::where('is_mca', true)->count(),
            'total_usage' => McaPattern::where('is_mca', true)->sum('usage_count'),
        ];

        return view('bankstatement.lenders', compact('lenders', 'debtCollectors', 'stats'));
    }

    /**
     * Display detail page for a specific lender.
     */
    public function lenderDetail($lenderId)
    {
        // Get all patterns for this lender
        $patterns = McaPattern::where('lender_id', $lenderId)
            ->where('is_mca', true)
            ->orderBy('usage_count', 'desc')
            ->get();

        if ($patterns->isEmpty()) {
            abort(404, 'Lender not found');
        }

        // Get lender info from first pattern
        $lender = [
            'id' => $lenderId,
            'name' => $patterns->first()->lender_name,
            'total_patterns' => $patterns->count(),
            'total_usage' => $patterns->sum('usage_count'),
            'first_seen' => $patterns->sortBy('created_at')->first()->created_at,
            'last_used' => $patterns->sortByDesc('updated_at')->first()->updated_at,
        ];

        return view('bankstatement.lender-detail', compact('lender', 'patterns'));
    }

    /**
     * Show form to create a new lender.
     */
    public function createLender()
    {
        $knownLenders = McaPattern::getKnownLenders();
        $knownDebtCollectors = McaPattern::getKnownDebtCollectors();
        return view('bankstatement.lenders-create', compact('knownLenders', 'knownDebtCollectors'));
    }

    /**
     * Store a new lender pattern.
     */
    public function storeLender(Request $request)
    {
        $request->validate([
            'lender_id' => 'required|string|max:100',
            'lender_name' => 'required|string|max:255',
            'description_pattern' => 'required|string|max:500',
        ]);

        $pattern = McaPattern::create([
            'lender_id' => $request->lender_id,
            'lender_name' => $request->lender_name,
            'description_pattern' => $request->description_pattern,
            'is_mca' => true,
            'usage_count' => 0,
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('bankstatement.lender-detail', $request->lender_id)
            ->with('success', 'Lender pattern created successfully!');
    }

    /**
     * Show form to create a new pattern for existing lender.
     */
    public function createPattern($lenderId)
    {
        $patterns = McaPattern::where('lender_id', $lenderId)
            ->where('is_mca', true)
            ->first();

        if (!$patterns) {
            abort(404, 'Lender not found');
        }

        $lender = [
            'id' => $lenderId,
            'name' => $patterns->lender_name,
        ];

        return view('bankstatement.pattern-create', compact('lender'));
    }

    /**
     * Store a new pattern for existing lender.
     */
    public function storePattern(Request $request, $lenderId)
    {
        $request->validate([
            'description_pattern' => 'required|string|max:500',
        ]);

        // Get lender name from existing pattern
        $existingPattern = McaPattern::where('lender_id', $lenderId)
            ->where('is_mca', true)
            ->first();

        if (!$existingPattern) {
            abort(404, 'Lender not found');
        }

        $pattern = McaPattern::create([
            'lender_id' => $lenderId,
            'lender_name' => $existingPattern->lender_name,
            'description_pattern' => $request->description_pattern,
            'is_mca' => true,
            'usage_count' => 0,
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('bankstatement.lender-detail', $lenderId)
            ->with('success', 'Pattern added successfully!');
    }

    /**
     * Show form to edit a pattern.
     */
    public function editPattern($lenderId, $patternId)
    {
        $pattern = McaPattern::where('id', $patternId)
            ->where('lender_id', $lenderId)
            ->where('is_mca', true)
            ->firstOrFail();

        $lender = [
            'id' => $lenderId,
            'name' => $pattern->lender_name,
        ];

        return view('bankstatement.pattern-edit', compact('lender', 'pattern'));
    }

    /**
     * Update a pattern.
     */
    public function updatePattern(Request $request, $lenderId, $patternId)
    {
        $request->validate([
            'description_pattern' => 'required|string|max:500',
        ]);

        $pattern = McaPattern::where('id', $patternId)
            ->where('lender_id', $lenderId)
            ->where('is_mca', true)
            ->firstOrFail();

        $pattern->update([
            'description_pattern' => $request->description_pattern,
        ]);

        return redirect()
            ->route('bankstatement.lender-detail', $lenderId)
            ->with('success', 'Pattern updated successfully!');
    }

    /**
     * Delete a pattern.
     */
    public function deletePattern($lenderId, $patternId)
    {
        $pattern = McaPattern::where('id', $patternId)
            ->where('lender_id', $lenderId)
            ->where('is_mca', true)
            ->firstOrFail();

        // Check if this is the last pattern for the lender
        $patternCount = McaPattern::where('lender_id', $lenderId)
            ->where('is_mca', true)
            ->count();

        if ($patternCount <= 1) {
            return redirect()
                ->route('bankstatement.lender-detail', $lenderId)
                ->with('error', 'Cannot delete the last pattern for a lender.');
        }

        $pattern->delete();

        return redirect()
            ->route('bankstatement.lender-detail', $lenderId)
            ->with('success', 'Pattern deleted successfully!');
    }

    /**
     * Detect bank name from filename.
     */
    private function detectBankName(string $filename): ?string
    {
        $filenameLower = strtolower($filename);

        $banks = [
            'wells fargo' => ['wells fargo', 'wellsfargo', 'wf_', 'wf-'],
            'Chase' => ['chase', 'jpmorgan'],
            'Bank of America' => ['bank of america', 'bankofamerica', 'boa_', 'boa-', 'bofa'],
            'Citibank' => ['citibank', 'citi_', 'citi-'],
            'US Bank' => ['us bank', 'usbank', 'u.s. bank'],
            'PNC Bank' => ['pnc bank', 'pncbank', 'pnc_', 'pnc-'],
            'Capital One' => ['capital one', 'capitalone'],
            'TD Bank' => ['td bank', 'tdbank', 'td_'],
            'Truist' => ['truist', 'suntrust', 'bb&t'],
            'Fifth Third' => ['fifth third', 'fifththird', '5th 3rd', '53_'],
            'Citizens Bank' => ['citizens bank', 'citizensbank'],
            'KeyBank' => ['keybank', 'key bank'],
            'Huntington' => ['huntington'],
            'Regions Bank' => ['regions bank', 'regionsbank', 'regions_'],
            'M&T Bank' => ['m&t bank', 'mtbank', 'm&t_'],
            'HSBC' => ['hsbc'],
            'BMO' => ['bmo bank', 'bmo_', 'harris bank'],
            'Ally Bank' => ['ally bank', 'allybank'],
            'Discover Bank' => ['discover bank', 'discoverbank'],
            'Navy Federal' => ['navy federal', 'navyfederal', 'nfcu'],
            'USAA' => ['usaa'],
            'Charles Schwab' => ['schwab'],
            'American Express' => ['american express', 'amex'],
            'Goldman Sachs' => ['goldman sachs', 'marcus'],
            'Santander' => ['santander'],
            'First Republic' => ['first republic'],
            'Silicon Valley Bank' => ['silicon valley', 'svb_'],
            'Comerica' => ['comerica'],
            'Zions Bank' => ['zions bank', 'zionsbank'],
            'First National Bank' => ['first national'],
            'Associated Bank' => ['associated bank'],
            'Webster Bank' => ['webster bank'],
            'Eastern Bank' => ['eastern bank'],
            'Frost Bank' => ['frost bank'],
            'Arvest Bank' => ['arvest'],
            'Atlantic Union Bank' => ['atlantic union'],
            'Banner Bank' => ['banner bank'],
            'First Horizon' => ['first horizon'],
            'Glacier Bank' => ['glacier bank'],
            'Independent Bank' => ['independent bank'],
            'Old National Bank' => ['old national'],
            'Pinnacle Bank' => ['pinnacle bank'],
            'Renasant Bank' => ['renasant'],
            'Simmons Bank' => ['simmons bank'],
            'South State Bank' => ['south state'],
            'Synovus' => ['synovus'],
            'UMB Bank' => ['umb bank', 'umb_'],
            'United Bank' => ['united bank'],
            'Valley National Bank' => ['valley national'],
            'Washington Federal' => ['washington federal'],
            'Western Alliance' => ['western alliance'],
            'Wintrust' => ['wintrust'],
        ];

        foreach ($banks as $bankName => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($filenameLower, $pattern) !== false) {
                    return $bankName;
                }
            }
        }

        return null;
    }

    /**
     * Group transactions by month and calculate true revenue for each month.
     * Matches FCS PDF format with adjustments.
     */
    private function groupTransactionsByMonth(array $transactions, $session = null): array
    {
        // Patterns to exclude from true revenue (non-revenue deposits/adjustments)
        $adjustmentPatterns = [
            // Transfers
            'transfer from', 'xfer from', 'online transfer', 'wire transfer', 'ach transfer',
            'zelle from', 'venmo from', 'paypal transfer', 'internal transfer', 'move money',
            'transfer credit', 'account transfer', 'funds transfer',
            // Loans and advances
            'loan', 'advance', 'mca', 'merchant cash', 'funding', 'capital', 'lending',
            'business loan', 'credit line', 'line of credit', 'financing',
            // Refunds and reversals
            'refund', 'reversal', 'return', 'rebate', 'chargeback', 'credit adjustment',
            'adjustment', 'correction', 'void', 'cancelled',
            // Interest and bank credits
            'interest', 'dividend', 'bonus', 'reward', 'cashback', 'cash back',
            // NSF reversals
            'nsf fee reversal', 'overdraft reversal', 'fee waiver',
        ];

        // Group transactions by month
        $monthlyGroups = [];

        foreach ($transactions as $txn) {
            $date = $txn['date'] ?? null;
            if (!$date) continue;

            // Parse the date and get month key (YYYY-MM)
            $timestamp = strtotime($date);
            if (!$timestamp) continue;

            $monthKey = date('Y-m', $timestamp);
            $monthName = date('F Y', $timestamp);

            if (!isset($monthlyGroups[$monthKey])) {
                $monthlyGroups[$monthKey] = [
                    'month_key' => $monthKey,
                    'month_name' => $monthName,
                    'month_short' => date('M', $timestamp),
                    'year' => date('Y', $timestamp),
                    'deposits' => 0,
                    'deposit_count' => 0,
                    'adjustments' => 0,
                    'adjustment_count' => 0,
                    'adjustment_items' => [],
                    'true_revenue' => 0,
                    'debits' => 0,
                    'debit_count' => 0,
                    'transactions' => [],
                    'days_in_month' => date('t', $timestamp),
                ];
            }

            $amount = (float) ($txn['amount'] ?? 0);
            $description = strtolower($txn['description'] ?? '');
            $type = $txn['type'] ?? 'debit';

            if ($type === 'credit') {
                $monthlyGroups[$monthKey]['deposits'] += $amount;
                $monthlyGroups[$monthKey]['deposit_count']++;

                $isAdjustment = false;
                $classificationSource = 'default';
                $isMcaFunding = false;
                $mcaFundingLenderId = null;
                $mcaFundingLenderName = null;

                // First check learned patterns (user corrections take priority)
                $learnedClassification = RevenueClassification::getFullClassification($txn['description'] ?? '');

                if ($learnedClassification !== null) {
                    $isAdjustment = ($learnedClassification['classification'] === 'adjustment');
                    $classificationSource = 'learned';

                    // Check if this is MCA funding
                    if ($isAdjustment && $learnedClassification['is_mca_funding']) {
                        $isMcaFunding = true;
                        $mcaFundingLenderId = $learnedClassification['mca_lender_id'];
                        $mcaFundingLenderName = $learnedClassification['mca_lender_name'];
                    }
                } else {
                    // If no learned pattern, check default adjustment patterns
                    foreach ($adjustmentPatterns as $pattern) {
                        if (stripos($description, $pattern) !== false) {
                            $isAdjustment = true;
                            break;
                        }
                    }
                }

                // Store classification info for UI toggle
                $txn['is_adjustment'] = $isAdjustment;
                $txn['classification_source'] = $classificationSource;
                $txn['is_mca_funding'] = $isMcaFunding;
                $txn['mca_funding_lender_id'] = $mcaFundingLenderId;
                $txn['mca_funding_lender_name'] = $mcaFundingLenderName;

                if ($isAdjustment) {
                    $monthlyGroups[$monthKey]['adjustments'] += $amount;
                    $monthlyGroups[$monthKey]['adjustment_count']++;
                    $monthlyGroups[$monthKey]['adjustment_items'][] = [
                        'description' => $txn['description'],
                        'amount' => $amount,
                        'transaction_index' => count($monthlyGroups[$monthKey]['transactions']),
                        'is_mca_funding' => $isMcaFunding,
                        'mca_funding_lender_name' => $mcaFundingLenderName,
                    ];
                }

                // Add transaction to the month with classification info
                $monthlyGroups[$monthKey]['transactions'][] = $txn;
            } else {
                $monthlyGroups[$monthKey]['debits'] += $amount;
                $monthlyGroups[$monthKey]['debit_count']++;

                // NSF counting will be done comprehensively later with the new calculator
                // Skip simple pattern matching here

                // Check for MCA payments (for UI toggle state)
                $txn['is_mca'] = false;
                $txn['mca_lender'] = null;
                $txn['mca_lender_id'] = null;

                // Check if explicitly excluded
                if (! McaPattern::isExcluded($txn['description'] ?? '')) {
                    // First check learned patterns
                    $learnedMca = McaPattern::checkMcaPattern($txn['description'] ?? '');
                    if ($learnedMca) {
                        $txn['is_mca'] = true;
                        $txn['mca_lender'] = $learnedMca['lender_name'];
                        $txn['mca_lender_id'] = $learnedMca['lender_id'];
                    } else {
                        // Check hardcoded patterns
                        $mcaLenders = [
                            'ondeck' => ['name' => 'OnDeck Capital', 'patterns' => ['ondeck', 'on deck']],
                            'kabbage' => ['name' => 'Kabbage', 'patterns' => ['kabbage']],
                            'fundbox' => ['name' => 'Fundbox', 'patterns' => ['fundbox']],
                            'bluevine' => ['name' => 'BlueVine', 'patterns' => ['bluevine', 'blue vine']],
                            'credibly' => ['name' => 'Credibly', 'patterns' => ['credibly']],
                            'kapitus' => ['name' => 'Kapitus', 'patterns' => ['kapitus']],
                            'rapid_finance' => ['name' => 'Rapid Finance', 'patterns' => ['rapid finance', 'rapidfinance']],
                            'can_capital' => ['name' => 'CAN Capital', 'patterns' => ['can capital']],
                            'square_capital' => ['name' => 'Square Capital', 'patterns' => ['square capital', 'sq capital']],
                            'paypal_working' => ['name' => 'PayPal Working Capital', 'patterns' => ['paypal working', 'paypal wc']],
                            'amazon_lending' => ['name' => 'Amazon Lending', 'patterns' => ['amazon lending']],
                            'shopify_capital' => ['name' => 'Shopify Capital', 'patterns' => ['shopify capital']],
                            'stripe_capital' => ['name' => 'Stripe Capital', 'patterns' => ['stripe capital']],
                            'clearco' => ['name' => 'Clearco', 'patterns' => ['clearco', 'clearbanc']],
                            'libertas' => ['name' => 'Libertas Funding', 'patterns' => ['libertas']],
                        ];

                        foreach ($mcaLenders as $lenderId => $lenderInfo) {
                            foreach ($lenderInfo['patterns'] as $pattern) {
                                if (stripos($description, $pattern) !== false) {
                                    $txn['is_mca'] = true;
                                    $txn['mca_lender'] = $lenderInfo['name'];
                                    $txn['mca_lender_id'] = $lenderId;
                                    break 2;
                                }
                            }
                        }
                    }
                }

                // Add debit transaction to the month
                $monthlyGroups[$monthKey]['transactions'][] = $txn;
            }
        }

        // Calculate true revenue, averages, and negative days for each month
        $isFirstMonth = true;
        foreach ($monthlyGroups as &$month) {
            $month['true_revenue'] = $month['deposits'] - $month['adjustments'];
            $month['average_daily'] = $month['days_in_month'] > 0
                ? $month['true_revenue'] / $month['days_in_month']
                : 0;

            // Calculate negative days and NSF using the comprehensive calculator
            $calculator = new NsfAndNegativeDaysCalculator();

            // Get opening balance for this month
            $openingBalance = null;

            // For the first month, use the session's beginning_balance if available
            if ($isFirstMonth && $session && $session->beginning_balance !== null) {
                $openingBalance = (float) $session->beginning_balance;
                $isFirstMonth = false;
            }
            // Otherwise check if first transaction has beginning_balance
            elseif (!empty($month['transactions'])) {
                $firstTxn = $month['transactions'][0];
                if (isset($firstTxn['beginning_balance'])) {
                    $openingBalance = $firstTxn['beginning_balance'];
                }
            }

            // Calculate negative days
            $negativeDaysResult = $calculator->calculateNegativeDays($month['transactions'], $openingBalance);
            $month['negative_days'] = $negativeDaysResult['negative_days_count'];
            $month['negative_dates'] = $negativeDaysResult['negative_dates'];
            $month['negative_days_method'] = $negativeDaysResult['method_used'];
            $month['beginning_balance'] = $openingBalance; // Store for use in combined view

            // Debug logging
            \Log::info('Negative Days Calculation', [
                'month' => $month['month_name'],
                'negative_days_count' => $negativeDaysResult['negative_days_count'],
                'method_used' => $negativeDaysResult['method_used'],
                'opening_balance' => $openingBalance,
                'transaction_count' => count($month['transactions']),
                'negative_dates' => $negativeDaysResult['negative_dates'],
            ]);

            // Calculate NSF counts
            $nsfResult = $calculator->calculateNsfCounts($month['transactions']);
            $month['nsf_count'] = $nsfResult['unique_nsf_events']; // Use unique events for the main count
            $month['nsf_fee_count'] = $nsfResult['nsf_fee_count'];
            $month['returned_item_count'] = $nsfResult['returned_item_count'];
            $month['nsf_details'] = [
                'fees' => $nsfResult['nsf_fees'],
                'returned_items' => $nsfResult['returned_items'],
                'unique_events' => $nsfResult['unique_events'],
            ];

            // Debug logging
            \Log::info('NSF Calculation', [
                'month' => $month['month_name'],
                'unique_nsf_events' => $nsfResult['unique_nsf_events'],
                'nsf_fee_count' => $nsfResult['nsf_fee_count'],
                'returned_item_count' => $nsfResult['returned_item_count'],
            ]);
        }
        unset($month); // Important: unset reference to prevent PHP reference bug

        // Sort by month key
        ksort($monthlyGroups);

        // Calculate totals and averages
        $totals = [
            'deposits' => 0,
            'adjustments' => 0,
            'true_revenue' => 0,
            'debits' => 0,
            'deposit_count' => 0,
            'debit_count' => 0,
            'nsf_count' => 0,
            'nsf_fee_count' => 0,
            'returned_item_count' => 0,
            'average_daily' => 0,
            'negative_days' => 0,
        ];

        $monthCount = count($monthlyGroups);

        foreach ($monthlyGroups as $month) {
            $totals['deposits'] += $month['deposits'];
            $totals['adjustments'] += $month['adjustments'];
            $totals['true_revenue'] += $month['true_revenue'];
            $totals['debits'] += $month['debits'];
            $totals['deposit_count'] += $month['deposit_count'];
            $totals['debit_count'] += $month['debit_count'];
            $totals['nsf_count'] += $month['nsf_count']; // Unique NSF events
            $totals['nsf_fee_count'] += $month['nsf_fee_count'] ?? 0;
            $totals['returned_item_count'] += $month['returned_item_count'] ?? 0;
            $totals['average_daily'] += $month['average_daily'];
            $totals['negative_days'] += $month['negative_days'];
        }

        // Calculate averages
        $averages = [
            'deposits' => $monthCount > 0 ? $totals['deposits'] / $monthCount : 0,
            'adjustments' => $monthCount > 0 ? $totals['adjustments'] / $monthCount : 0,
            'true_revenue' => $monthCount > 0 ? $totals['true_revenue'] / $monthCount : 0,
            'debits' => $monthCount > 0 ? $totals['debits'] / $monthCount : 0,
            'deposit_count' => $monthCount > 0 ? $totals['deposit_count'] / $monthCount : 0,
            'average_daily' => $monthCount > 0 ? $totals['average_daily'] / $monthCount : 0,
        ];

        return [
            'months' => array_values($monthlyGroups),
            'totals' => $totals,
            'averages' => $averages,
            'month_count' => $monthCount,
        ];
    }

    /**
     * Save an MCA offer calculation.
     */
    public function saveOffer(Request $request)
    {
        $request->validate([
            'session_uuid' => 'required|string',
            'true_revenue_monthly' => 'required|numeric|min:0',
            'revenue_override' => 'boolean',
            'override_revenue' => 'nullable|numeric|min:0',
            'existing_mca_payment' => 'nullable|numeric|min:0',
            'withhold_percent' => 'required|numeric|min:0|max:100',
            'factor_rate' => 'required|numeric|min:1|max:2',
            'term_months' => 'required|integer|min:1|max:36',
            'advance_amount' => 'required|numeric|min:0',
            'offer_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $trueRevenue = $request->revenue_override
            ? (float) $request->override_revenue
            : (float) $request->true_revenue_monthly;

        $existingPayment = (float) ($request->existing_mca_payment ?? 0);
        $withholdPercent = (float) $request->withhold_percent;
        $factorRate = (float) $request->factor_rate;
        $termMonths = (int) $request->term_months;
        $advanceAmount = (float) $request->advance_amount;

        // Calculate derived values
        $capAmount = $trueRevenue * ($withholdPercent / 100);
        $newPaymentAvailable = $capAmount - $existingPayment;
        $totalPayback = $advanceAmount * $factorRate;
        $monthlyPayment = $totalPayback / $termMonths;
        $maxFundedAmount = ($newPaymentAvailable * $termMonths) / $factorRate;

        $offer = \App\Models\McaOffer::create([
            'offer_id' => \Illuminate\Support\Str::uuid()->toString(),
            'session_uuid' => $request->session_uuid,
            'user_id' => auth()->id(),
            'true_revenue_monthly' => $trueRevenue,
            'revenue_override' => (bool) $request->revenue_override,
            'override_revenue' => $request->override_revenue,
            'existing_mca_payment' => $existingPayment,
            'withhold_percent' => $withholdPercent,
            'cap_amount' => $capAmount,
            'new_payment_available' => $newPaymentAvailable,
            'factor_rate' => $factorRate,
            'term_months' => $termMonths,
            'advance_amount' => $advanceAmount,
            'total_payback' => $totalPayback,
            'monthly_payment' => $monthlyPayment,
            'max_funded_amount' => $maxFundedAmount,
            'offer_name' => $request->offer_name,
            'notes' => $request->notes,
        ]);

        Log::info("MCA Offer saved: {$offer->offer_id} for session {$request->session_uuid}");

        return response()->json([
            'success' => true,
            'message' => 'Offer saved successfully',
            'offer' => $offer,
        ]);
    }

    /**
     * Load saved MCA offers for a session.
     */
    public function loadOffers(Request $request)
    {
        $request->validate([
            'session_uuid' => 'required|string',
        ]);

        $offers = \App\Models\McaOffer::where('session_uuid', $request->session_uuid)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'offers' => $offers,
            'count' => $offers->count(),
        ]);
    }

    /**
     * Delete a saved MCA offer.
     */
    public function deleteOffer(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|string',
        ]);

        $offer = \App\Models\McaOffer::where('offer_id', $request->offer_id)->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found',
            ], 404);
        }

        $offer->delete();

        Log::info("MCA Offer deleted: {$request->offer_id}");

        return response()->json([
            'success' => true,
            'message' => 'Offer deleted successfully',
        ]);
    }

    /**
     * Toggle favorite status for an MCA offer.
     */
    public function toggleOfferFavorite(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|string',
        ]);

        $offer = \App\Models\McaOffer::where('offer_id', $request->offer_id)->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found',
            ], 404);
        }

        $offer->is_favorite = !$offer->is_favorite;
        $offer->save();

        return response()->json([
            'success' => true,
            'is_favorite' => $offer->is_favorite,
        ]);
    }
}
