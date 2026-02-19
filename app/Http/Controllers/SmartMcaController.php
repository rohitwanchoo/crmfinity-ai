<?php

namespace App\Http\Controllers;

use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Models\TransactionCorrection;
use App\Services\BankPatternService;
use App\Services\DynamicMCACalculator;
use App\Services\TrueRevenueEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SmartMcaController extends Controller
{
    public function index()
    {
        // Get recent analysis sessions for history
        $recentSessions = AnalysisSession::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $stats = [
            'total_sessions' => AnalysisSession::where('user_id', auth()->id())->count(),
            'total_transactions' => AnalyzedTransaction::whereHas('session', function ($q) {
                $q->where('user_id', auth()->id());
            })->count(),
            'corrections_made' => TransactionCorrection::where('user_id', auth()->id())->count(),
        ];

        return view('smartmca.index', compact('recentSessions', 'stats'));
    }

    public function analyze(Request $request)
    {
        $request->validate([
            'statements.*' => 'required|file|mimes:pdf|max:10240',
        ]);

        $allTransactions = [];
        $uploadPath = storage_path('app/uploads');

        if (! is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        if ($request->hasFile('statements')) {
            // First, save all files to disk to prevent upload timeout issues
            $savedFiles = [];
            foreach ($request->file('statements') as $file) {
                $originalFilename = $file->getClientOriginalName();
                $filename = 'mca_'.Str::random(10).'.pdf';
                $filePath = $uploadPath.'/'.$filename;
                $file->move($uploadPath, $filename);

                $savedFiles[] = [
                    'originalName' => $originalFilename,
                    'path' => $filePath,
                ];
            }

            // Now process each file one by one (sequentially)
            foreach ($savedFiles as $fileInfo) {
                $originalFilename = $fileInfo['originalName'];
                $filePath = $fileInfo['path'];

                // Step 1: Extract text from PDF using PyMuPDF
                // Enable layout-preserving mode (true) to maintain column structure for better accuracy
                $scriptPath = storage_path('app/scripts/extract_pdf_text.py');
                $command = '/var/www/html/crmfinity_underwriting/crmfinity-ai/venv/bin/python3 '.escapeshellarg($scriptPath).' '.escapeshellarg($filePath).' true 2>&1';
                $output = shell_exec($command);
                $result = json_decode($output, true);

                $transactions = [];
                $pages = 0;
                $extractionError = null;

                if ($result && $result['success']) {
                    $text = $result['text'];
                    $pages = $result['pages'] ?? 0;

                    \Log::info("SmartMCA: Processing '{$originalFilename}' - {$pages} pages, ".strlen($text).' chars');

                    // DEBUG: Save extracted text for analysis
                    $debugFile = storage_path('app/uploads/debug_extracted_text.txt');
                    file_put_contents($debugFile, "=== {$originalFilename} ===\n\n" . $text);

                    // Step 2a: Extract expected totals from page 1 summary FIRST
                    $expectedTotals = $this->extractExpectedTotalsFromPage1($text);
                    if ($expectedTotals['found']) {
                        \Log::info("SmartMCA: Page 1 Summary - Expected {$expectedTotals['credit_count']} credits totaling \${$expectedTotals['expected_credits']}, {$expectedTotals['debit_count']} debits totaling \${$expectedTotals['expected_debits']}");
                    }

                    // Step 2b: Use dual AI parsing with learned patterns
                    // For long statements (3+ pages or 12K+ chars), process page by page
                    // This ensures AI can handle all transactions without truncation
                    if ($pages >= 3 || strlen($text) > 12000) {
                        $transactions = $this->parseTransactionsInChunks($text, $pages, $expectedTotals);
                    } else {
                        $transactions = $this->parseTransactionsWithDualAI($text);
                        // Validate against page 1 expected totals
                        $this->validateExtractedTotals($text, $transactions, $expectedTotals);
                    }

                    // Step 3: Apply any user corrections to override AI predictions
                    $transactions = $this->applyLearnedCorrections($transactions);

                    // Debug: Log final summary before returning to view
                    $finalSummary = $this->calculateSummary($transactions);
                    \Log::info("SmartMCA: Completed '{$originalFilename}' - ".count($transactions)." transactions found");
                    \Log::info("SmartMCA: Final Summary - Credits: \${$finalSummary['total_credits']} ({$finalSummary['credit_count']} txns), Debits: \${$finalSummary['total_debits']} ({$finalSummary['debit_count']} txns)");
                } else {
                    $extractionError = $result['error'] ?? 'Unknown error extracting PDF text';
                    \Log::error("SmartMCA: Failed to extract text from '{$originalFilename}': ".$extractionError);
                }

                $statementData = [
                    'file' => $originalFilename,
                    'pages' => $pages,
                    'transactions' => $transactions,
                    'summary' => $this->calculateSummary($transactions),
                    'error' => $extractionError,
                ];

                // Step 4: Save to database for learning (only if we have transactions)
                if (! empty($transactions)) {
                    $session = $this->saveAnalysisSession($statementData, $transactions);
                    $statementData['session_id'] = $session->session_id;
                }

                $allTransactions[] = $statementData;

                // Clean up the PDF file after processing
                @unlink($filePath);

                // Small delay between API calls to avoid rate limiting
                if (count($savedFiles) > 1) {
                    usleep(500000); // 0.5 second delay
                }
            }
        }

        return view('smartmca.results', compact('allTransactions'));
    }

    /**
     * Save analysis session and transactions to database
     * Returns session and array of transaction IDs
     */
    private function saveAnalysisSession(array $data, array &$transactions): AnalysisSession
    {
        $session = AnalysisSession::createFromAnalysis($data, auth()->id());
        $transactionIds = AnalyzedTransaction::createFromAnalysis($session->id, $transactions);

        // Add database IDs back to the transactions array
        foreach ($transactionIds as $index => $id) {
            if (isset($transactions[$index])) {
                $transactions[$index]['id'] = $id;
            }
        }

        return $session;
    }

    /**
     * Save a transaction type correction for AI learning
     */
    public function saveCorrection(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:500',
            'original_type' => 'required|in:credit,debit',
            'correct_type' => 'required|in:credit,debit',
            'amount' => 'nullable|numeric',
            'transaction_id' => 'nullable|integer',
            'session_id' => 'nullable|string',
        ]);

        $amount = $request->amount ?? 0;
        $sessionUpdated = false;

        // Save to corrections table for AI learning
        $correction = TransactionCorrection::recordCorrection(
            $request->description,
            $request->original_type,
            $request->correct_type,
            $amount,
            auth()->id()
        );

        \Log::info("SmartMCA Correction: '{$request->description}' changed from {$request->original_type} to {$request->correct_type} (amount: {$amount})");

        // Update the specific analyzed transaction if ID provided
        if ($request->transaction_id) {
            $transaction = AnalyzedTransaction::find($request->transaction_id);
            if ($transaction) {
                $oldType = $transaction->type;
                $transaction->update([
                    'type' => $request->correct_type,
                    'was_corrected' => true,
                    'confidence' => 1.0, // User corrected = 100% confidence
                    'confidence_label' => 'high',
                ]);

                // Update the session totals
                $session = $transaction->session;
                if ($session && $oldType !== $request->correct_type) {
                    $this->updateSessionTotals($session, $transaction->amount, $oldType, $request->correct_type);
                    $sessionUpdated = true;
                }
            }
        }

        // Update any matching transactions in history (same description pattern)
        $normalizedPattern = TransactionCorrection::normalizePattern($request->description);
        $matchingTransactions = AnalyzedTransaction::where('description_normalized', 'LIKE', '%'.$normalizedPattern.'%')
            ->where('type', '!=', $request->correct_type)
            ->get();

        $updatedCount = 0;
        foreach ($matchingTransactions as $txn) {
            $oldType = $txn->type;
            $txn->update([
                'type' => $request->correct_type,
                'was_corrected' => true,
            ]);

            // Update session totals for each affected transaction
            $session = $txn->session;
            if ($session) {
                $this->updateSessionTotals($session, $txn->amount, $oldType, $request->correct_type);
            }
            $updatedCount++;
        }

        if ($updatedCount > 0) {
            \Log::info("SmartMCA: Also updated {$updatedCount} similar transactions in history");
        }

        return response()->json([
            'success' => true,
            'message' => 'Correction saved. AI will learn from this for future analysis.',
            'correction_id' => $correction->id,
            'similar_updated' => $updatedCount,
            'session_updated' => $sessionUpdated,
        ]);
    }

    /**
     * Update session totals when a transaction type changes
     */
    private function updateSessionTotals(AnalysisSession $session, float $amount, string $oldType, string $newType): void
    {
        if ($oldType === 'credit' && $newType === 'debit') {
            $session->total_credits -= $amount;
            $session->total_debits += $amount;
        } elseif ($oldType === 'debit' && $newType === 'credit') {
            $session->total_debits -= $amount;
            $session->total_credits += $amount;
        }

        $session->net_flow = $session->total_credits - $session->total_debits;
        $session->save();
    }

    /**
     * Get analysis history
     */
    public function history()
    {
        $sessions = AnalysisSession::where('user_id', auth()->id())
            ->withCount('transactions')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('smartmca.history', compact('sessions'));
    }

    /**
     * View a specific analysis session
     */
    public function viewSession(string $sessionId)
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $transactions = $session->transactions()->orderBy('transaction_date')->get();

        return view('smartmca.session', compact('session', 'transactions'));
    }

    /**
     * Get all learned patterns for display
     */
    public function getLearnedPatterns()
    {
        $patterns = TransactionCorrection::orderBy('usage_count', 'desc')
            ->limit(100)
            ->get();

        $stats = AnalyzedTransaction::getTypeStatistics();

        return response()->json([
            'success' => true,
            'patterns' => $patterns,
            'stats' => $stats,
        ]);
    }

    /**
     * Extract expected totals from page 1 summary section
     * This provides the ground truth for validation
     */
    private function extractExpectedTotalsFromPage1(string $fullText): array
    {
        $result = [
            'found' => false,
            'expected_credits' => null,
            'expected_debits' => null,
            'credit_count' => null,
            'debit_count' => null,
        ];

        // Get first page text only
        $pages = preg_split('/\n*=== PAGE BREAK ===\n*/i', $fullText);
        $page1Text = $pages[0] ?? $fullText;

        // First United Bank format: "29  Deposits/Credits" followed by "$257,395.20"
        // And "99  Checks/Debits" followed by "$315,118.65"

        // Credit patterns - multiple bank formats
        $creditPatterns = [
            // First United Bank: "29  Deposits/Credits\n$257,395.20"
            '/(\d+)\s+Deposits?\/Credits?\s*\n?\$?([\d,]+\.\d{2})/i',
            // Alternative: "29 Deposits/Credits $257,395.20"
            '/(\d+)\s+Deposits?\/Credits?\s+\$?([\d,]+\.\d{2})/i',
            // Wells Fargo: "Total Deposits and Other Credits $X"
            '/Total\s+Deposits?\s+(?:and\s+Other\s+)?Credits?\s*\$?([\d,]+\.\d{2})/i',
            // Generic: "Total Credits: $X"
            '/Total\s+Credits?\s*[:\-]?\s*\$?([\d,]+\.\d{2})/i',
            // PayPal: "Money In $X"
            '/Money\s+In\s*\$?([\d,]+\.\d{2})/i',
        ];

        foreach ($creditPatterns as $pattern) {
            if (preg_match($pattern, $page1Text, $m)) {
                if (count($m) >= 3) {
                    $result['credit_count'] = (int) $m[1];
                    $result['expected_credits'] = (float) str_replace(',', '', $m[2]);
                } else {
                    $result['expected_credits'] = (float) str_replace(',', '', $m[1]);
                }
                $result['found'] = true;
                break;
            }
        }

        // Debit patterns - multiple bank formats
        $debitPatterns = [
            // First United Bank: "99  Checks/Debits\n$315,118.65"
            '/(\d+)\s+Checks?\/Debits?\s*\n?\$?([\d,]+\.\d{2})/i',
            // Alternative: "99 Checks/Debits $315,118.65"
            '/(\d+)\s+Checks?\/Debits?\s+\$?([\d,]+\.\d{2})/i',
            // Wells Fargo: "Total Withdrawals and Other Debits $X"
            '/Total\s+Withdrawals?\s+(?:and\s+Other\s+)?Debits?\s*\$?([\d,]+\.\d{2})/i',
            // Generic: "Total Debits: $X"
            '/Total\s+Debits?\s*[:\-]?\s*\$?([\d,]+\.\d{2})/i',
            // PayPal: "Money Out $X"
            '/Money\s+Out\s*\$?([\d,]+\.\d{2})/i',
        ];

        foreach ($debitPatterns as $pattern) {
            if (preg_match($pattern, $page1Text, $m)) {
                if (count($m) >= 3) {
                    $result['debit_count'] = (int) $m[1];
                    $result['expected_debits'] = (float) str_replace(',', '', $m[2]);
                } else {
                    $result['expected_debits'] = (float) str_replace(',', '', $m[1]);
                }
                $result['found'] = true;
                break;
            }
        }

        // Log what we found
        if ($result['found']) {
            \Log::info("SmartMCA Page1 Extract: Credits=\${$result['expected_credits']} ({$result['credit_count']} txns), Debits=\${$result['expected_debits']} ({$result['debit_count']} txns)");
        } else {
            \Log::warning("SmartMCA: Could not extract expected totals from page 1 summary");
        }

        return $result;
    }

    /**
     * Parse transactions in chunks for long statements
     */
    private function parseTransactionsInChunks(string $text, int $pages, ?array $expectedTotals = null): array
    {
        // Pre-process Wells Fargo statements to mark column positions BEFORE splitting by page
        // This ensures each page chunk retains the [CREDIT]/[DEBIT] markers
        $text = $this->preprocessWellsFargoColumns($text);

        // Split by page break markers
        $pageTexts = preg_split('/\n*=== PAGE BREAK ===\n*/i', $text);

        $allTransactions = [];
        $seenTransactions = []; // Track duplicates by date+description+amount

        // IMPORTANT: Detect bank ONCE using the full text, not per-chunk
        // This prevents misdetection when later pages mention payment services like Zelle
        $bankPatternService = new BankPatternService;
        $bankContext = $bankPatternService->buildAIContext($text);

        \Log::info("SmartMCA: Using consistent bank detection '{$bankContext['detected_bank']}' for all {$pages} pages");

        foreach ($pageTexts as $index => $pageText) {
            $pageText = trim($pageText);
            if (empty($pageText) || strlen($pageText) < 50) {
                continue;
            }

            // Skip image/receipt pages (deposit slips, check images)
            // These pages duplicate transactions already listed in the main statement
            if ($this->isImageReceiptPage($pageText)) {
                \Log::info("SmartMCA: Skipping page ".($index + 1)." - detected as image/receipt page (duplicates)");
                continue;
            }

            // Add context about which page this is
            $contextText = 'PAGE '.($index + 1)." OF {$pages}\n\n".$pageText;

            // Use dual AI parsing for better accuracy (OpenAI + Claude)
            $transactions = $this->parseTransactionsWithDualAI($contextText, $bankContext);

            // Log extracted transactions before dedup
            $beforeCredits = count(array_filter($transactions, fn($t) => $t['type'] === 'credit'));
            $beforeDebits = count(array_filter($transactions, fn($t) => $t['type'] === 'debit'));
            \Log::info("SmartMCA Page " . ($index + 1) . ": BEFORE dedup - Credits: {$beforeCredits}, Debits: {$beforeDebits}, Total: " . count($transactions));
            
            // Log all transactions for debugging
            foreach ($transactions as $idx => $txn) {
                \Log::debug("  TXN[{$idx}] {$txn['date']} | \${$txn['amount']} | {$txn['type']} | " . substr($txn['description'], 0, 50));
            }

            // Deduplicate transactions - be careful not to remove legitimate duplicates
            // (e.g., multiple checks for the same amount on the same day are valid)
            foreach ($transactions as $txn) {
                // Normalize date format (handle variations like 2024-11-1 vs 2024-11-01)
                $normalizedDate = date('Y-m-d', strtotime($txn['date']));
                $amount = number_format((float)$txn['amount'], 2, '.', '');
                $type = $txn['type'] ?? 'debit';

                // Clean and normalize description for comparison
                $descClean = strtolower(preg_replace('/[^a-z0-9]/', '', $txn['description']));
                $descPrefix = substr($descClean, 0, 25); // Use more chars for better matching

                // Primary key: date + amount + type + description prefix (most specific)
                // This allows same-amount transactions with different descriptions
                $primaryKey = "{$normalizedDate}|{$amount}|{$type}|{$descPrefix}";

                // Secondary key: amount + full description (catches cross-page duplicates)
                // Only used if descriptions are substantially similar
                $secondaryKey = "{$amount}|{$descClean}";

                // Check if this is a duplicate
                $isDuplicate = isset($seenTransactions[$primaryKey])
                    || isset($seenTransactions[$secondaryKey]);

                if (!$isDuplicate) {
                    $seenTransactions[$primaryKey] = true;
                    $seenTransactions[$secondaryKey] = true;
                    $allTransactions[] = $txn;
                }
            }
        }

        // Sort by date
        usort($allTransactions, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        // POST-PROCESSING: Remove duplicates if we have too many transactions
        if ($expectedTotals && $expectedTotals['found']) {
            $allTransactions = $this->removeDuplicatesIfOverCount($allTransactions, $expectedTotals);
        }

        // VALIDATION: Check extracted totals against page 1 summary totals
        $this->validateExtractedTotals($text, $allTransactions, $expectedTotals);

        return $allTransactions;
    }

    /**
     * Remove duplicate transactions when we have more than expected
     * Uses amount-based grouping to find and remove likely duplicates
     */
    private function removeDuplicatesIfOverCount(array $transactions, array $expectedTotals): array
    {
        $expectedCreditCount = $expectedTotals['credit_count'] ?? null;
        $expectedDebitCount = $expectedTotals['debit_count'] ?? null;
        $expectedCredits = $expectedTotals['expected_credits'] ?? null;
        $expectedDebits = $expectedTotals['expected_debits'] ?? null;

        // Count current transactions
        $credits = array_filter($transactions, fn($t) => $t['type'] === 'credit');
        $debits = array_filter($transactions, fn($t) => $t['type'] === 'debit');

        $creditTotal = array_sum(array_map(fn($t) => $t['amount'], $credits));
        $debitTotal = array_sum(array_map(fn($t) => $t['amount'], $debits));

        \Log::info("SmartMCA Dedup: BEFORE - Credits: " . count($credits) . " (\${$creditTotal}), Debits: " . count($debits) . " (\${$debitTotal})");
        \Log::info("SmartMCA Dedup: EXPECTED - Credits: {$expectedCreditCount} (\${$expectedCredits}), Debits: {$expectedDebitCount} (\${$expectedDebits})");

        $removedCount = 0;

        // If we have too many credits and total is over expected, remove duplicates
        if ($expectedCreditCount && count($credits) > $expectedCreditCount && $expectedCredits && $creditTotal > $expectedCredits) {
            $overage = $creditTotal - $expectedCredits;
            \Log::info("SmartMCA Dedup: Credit overage of \${$overage} detected, looking for duplicates");

            // Group credits by amount to find potential duplicates
            $creditsByAmount = [];
            foreach ($transactions as $idx => $txn) {
                if ($txn['type'] === 'credit') {
                    $amt = number_format($txn['amount'], 2, '.', '');
                    $creditsByAmount[$amt][] = $idx;
                }
            }

            // Remove duplicates (same amount appearing multiple times)
            $toRemove = [];
            foreach ($creditsByAmount as $amt => $indices) {
                if (count($indices) > 1) {
                    // Keep the first, mark others for removal (up to overage)
                    for ($i = 1; $i < count($indices); $i++) {
                        $txnAmount = $transactions[$indices[$i]]['amount'];
                        if ($overage >= $txnAmount - 0.01) {
                            $toRemove[] = $indices[$i];
                            $overage -= $txnAmount;
                            $removedCount++;
                            \Log::debug("SmartMCA Dedup: Removing duplicate credit \${$txnAmount}");
                        }
                        if ($overage < 1) break;
                    }
                }
                if ($overage < 1) break;
            }

            // Remove marked transactions
            foreach ($toRemove as $idx) {
                unset($transactions[$idx]);
            }
            $transactions = array_values($transactions);
        }

        // If we have too many debits and total is over expected, remove duplicates
        if ($expectedDebitCount && count($debits) > $expectedDebitCount && $expectedDebits && $debitTotal > $expectedDebits) {
            $overage = $debitTotal - $expectedDebits;
            \Log::info("SmartMCA Dedup: Debit overage of \${$overage} detected, looking for duplicates");

            // Group debits by amount to find potential duplicates
            $debitsByAmount = [];
            foreach ($transactions as $idx => $txn) {
                if ($txn['type'] === 'debit') {
                    $amt = number_format($txn['amount'], 2, '.', '');
                    $debitsByAmount[$amt][] = $idx;
                }
            }

            // Remove duplicates (same amount appearing multiple times)
            $toRemove = [];
            foreach ($debitsByAmount as $amt => $indices) {
                if (count($indices) > 1) {
                    // Keep the first, mark others for removal (up to overage)
                    for ($i = 1; $i < count($indices); $i++) {
                        $txnAmount = $transactions[$indices[$i]]['amount'];
                        if ($overage >= $txnAmount - 0.01) {
                            $toRemove[] = $indices[$i];
                            $overage -= $txnAmount;
                            $removedCount++;
                            \Log::debug("SmartMCA Dedup: Removing duplicate debit \${$txnAmount}");
                        }
                        if ($overage < 1) break;
                    }
                }
                if ($overage < 1) break;
            }

            // Remove marked transactions
            foreach ($toRemove as $idx) {
                unset($transactions[$idx]);
            }
            $transactions = array_values($transactions);
        }

        if ($removedCount > 0) {
            \Log::info("SmartMCA Dedup: Removed {$removedCount} duplicate transactions");
            
            // Recount after dedup
            $finalCredits = count(array_filter($transactions, fn($t) => $t['type'] === 'credit'));
            $finalDebits = count(array_filter($transactions, fn($t) => $t['type'] === 'debit'));
            $finalCreditTotal = array_sum(array_map(fn($t) => $t['amount'], array_filter($transactions, fn($t) => $t['type'] === 'credit')));
            $finalDebitTotal = array_sum(array_map(fn($t) => $t['amount'], array_filter($transactions, fn($t) => $t['type'] === 'debit')));
            
            \Log::info("SmartMCA Dedup: AFTER - Credits: {$finalCredits} (\${$finalCreditTotal}), Debits: {$finalDebits} (\${$finalDebitTotal})");
        }

        return $transactions;
    }

    /**
     * Pre-process Wells Fargo statements to mark credit/debit based on column position.
     * This MUST be done before splitting by page breaks so column context is preserved.
     */
    private function preprocessWellsFargoColumns(string $text): string
    {
        // Only apply to Wells Fargo statements
        if (stripos($text, 'wells fargo') === false && stripos($text, 'wellsfargo') === false) {
            return $text;
        }

        // Check for the characteristic column headers
        if (stripos($text, 'Deposits/') === false || stripos($text, 'Withdrawals/') === false) {
            return $text;
        }

        \Log::info("SmartMCA: Detected Wells Fargo format - applying column position analysis");

        $lines = explode("\n", $text);
        $creditColStart = null;
        $debitColStart = null;
        $balanceColStart = null;
        $processedLines = [];

        // First pass: find column header positions
        foreach ($lines as $line) {
            if (preg_match('/Deposits\/.*Withdrawals\//', $line)) {
                // Find positions of column headers
                $creditPos = strpos($line, 'Deposits/');
                $debitPos = strpos($line, 'Withdrawals/');
                $balancePos = stripos($line, 'Ending daily') ?: stripos($line, 'balance');

                if ($creditPos !== false) $creditColStart = $creditPos;
                if ($debitPos !== false) $debitColStart = $debitPos;
                if ($balancePos !== false) $balanceColStart = $balancePos;

                \Log::info("SmartMCA Wells Fargo columns: Credit@{$creditColStart}, Debit@{$debitColStart}, Balance@{$balanceColStart}");
                break;
            }
        }

        // If we couldn't find column positions, return unchanged
        if ($creditColStart === null || $debitColStart === null) {
            \Log::warning("SmartMCA: Could not detect Wells Fargo column positions");
            return $text;
        }

        $creditCount = 0;
        $debitCount = 0;

        // Second pass: mark each transaction line
        foreach ($lines as $line) {
            // Skip header lines and empty lines
            if (strlen(trim($line)) < 10 || preg_match('/^(Date|Deposits\/|Withdrawals\/|Ending|Beginning)/i', trim($line))) {
                $processedLines[] = $line;
                continue;
            }

            // Look for dollar amounts in this line (format: 1,234.56 or 123.45)
            if (preg_match_all('/(\d{1,3}(?:,\d{3})*\.\d{2})/', $line, $matches, PREG_OFFSET_CAPTURE)) {
                $amounts = $matches[0];

                // Skip if no amounts found
                if (empty($amounts)) {
                    $processedLines[] = $line;
                    continue;
                }

                // Determine transaction type based on position of FIRST amount that's not balance
                $transactionType = null;
                $firstAmountPos = null;

                foreach ($amounts as $match) {
                    $pos = $match[1];
                    $amount = $match[0];

                    // Skip if this is in the balance column (rightmost)
                    if ($balanceColStart !== null && $pos >= $balanceColStart - 5) {
                        continue;
                    }

                    // Determine if this amount is in credit or debit column
                    if ($pos >= $debitColStart - 5) {
                        $transactionType = 'DEBIT';
                    } elseif ($pos >= $creditColStart - 5) {
                        $transactionType = 'CREDIT';
                    }

                    if ($transactionType !== null) {
                        $firstAmountPos = $pos;
                        break;
                    }
                }

                // Insert marker if we determined the type
                if ($transactionType !== null && $firstAmountPos !== null) {
                    // Insert [CREDIT] or [DEBIT] marker before the amount
                    $markedLine = rtrim(substr($line, 0, $firstAmountPos)) . " [{$transactionType}] " . substr($line, $firstAmountPos);
                    $processedLines[] = $markedLine;

                    if ($transactionType === 'CREDIT') {
                        $creditCount++;
                    } else {
                        $debitCount++;
                    }
                } else {
                    $processedLines[] = $line;
                }
            } else {
                $processedLines[] = $line;
            }
        }

        \Log::info("SmartMCA Wells Fargo preprocessing: Marked {$creditCount} credits and {$debitCount} debits");

        return implode("\n", $processedLines);
    }

    /**
     * Detect if a page is an image/receipt page (deposit slips, check images)
     * These pages contain duplicate transaction data that should be skipped
     */
    private function isImageReceiptPage(string $pageText): bool
    {
        // Pattern 1: First United Bank image pages have "Account: XXXXXX" format (with colon)
        // and "Deposit: 0 Date:" or "Number: XXXX Date:" lines
        $hasAccountColon = preg_match('/^Account:\s*[X\d]+/m', $pageText);
        $hasPageColon = preg_match('/^Page:\s*\d+\s+of\s+\d+/m', $pageText);
        $hasDepositLine = preg_match('/^Deposit:\s*\d+\s+Date:/m', $pageText);
        $hasNumberLine = preg_match('/^Number:\s*\d+\s+Date:/m', $pageText);
        $hasAmountWithF = preg_match('/Amount:\s*\$[\d,.]+<F>/m', $pageText);

        // If page has the colon format AND deposit/number lines, it's an image page
        if (($hasAccountColon || $hasPageColon) && ($hasDepositLine || $hasNumberLine || $hasAmountWithF)) {
            return true;
        }

        // Pattern 2: Look for common image page indicators
        $imageIndicators = [
            'SUBSTITUTE IMAGE',
            'VIRTUAL DOCUMENT',
            'DDA Deposit',
            'CHECKING WITHDRAWAL',
            'PC/TC AMOUNT',
            'AUXILIARY',
            'Workstation:',
            'HIN #:',
            'Branch Name:',
            'Teller ID:',
            'Drawer #:',
            'Trans #:',
        ];

        $indicatorCount = 0;
        foreach ($imageIndicators as $indicator) {
            if (stripos($pageText, $indicator) !== false) {
                $indicatorCount++;
            }
        }

        // If we find multiple image indicators, it's likely an image page
        if ($indicatorCount >= 3) {
            return true;
        }

        // Pattern 3: Page has mostly "Deposit: X Date: XX/XX/XXXX Amount: $X" format lines
        // Count these vs regular transaction lines
        $depositFormatCount = preg_match_all('/^(Deposit|Number):\s*\d+\s+Date:\s*\d+\/\d+\/\d+\s+Amount:\s*\$/m', $pageText);
        $regularTxnCount = preg_match_all('/^(Credit|Debit)\s+Transactions/m', $pageText);

        // If page has deposit format lines but no regular transaction headers, it's an image page
        if ($depositFormatCount >= 3 && $regularTxnCount === 0) {
            return true;
        }

        return false;
    }

    /**
     * Validate extracted transactions against statement totals and log discrepancies
     * Uses pre-extracted page 1 totals when available for accuracy
     */
    private function validateExtractedTotals(string $fullText, array &$transactions, ?array $preExtractedTotals = null): void
    {
        // Use pre-extracted page 1 totals if available (more reliable)
        $expectedDebits = null;
        $expectedCredits = null;
        $expectedDebitCount = null;
        $expectedCreditCount = null;

        if ($preExtractedTotals && $preExtractedTotals['found']) {
            $expectedDebits = $preExtractedTotals['expected_debits'];
            $expectedCredits = $preExtractedTotals['expected_credits'];
            $expectedDebitCount = $preExtractedTotals['debit_count'];
            $expectedCreditCount = $preExtractedTotals['credit_count'];
            \Log::info("SmartMCA Validation: Using page 1 summary totals - Debits: \$" . ($expectedDebits ?? 'N/A') . " ({$expectedDebitCount} txns), Credits: \$" . ($expectedCredits ?? 'N/A') . " ({$expectedCreditCount} txns)");
        } else {
            // Fallback: Extract statement totals using regex - multiple bank formats
            // Debit patterns (in order of specificity)
            $debitPatterns = [
                '/Total\s+Withdrawals\s+and\s+Other\s+Debits\s*[-\$]*([\d,]+\.\d{2})/i',  // Wells Fargo
                '/Total\s+Withdrawals\s*[:\-]?\s*[-\$]*([\d,]+\.\d{2})/i',
                '/Withdrawals\s*[:\-]\s*[-\$]*([\d,]+\.\d{2})/i',
                '/Total\s+Debits?\s*[:\-]?\s*[-\$]*([\d,]+\.\d{2})/i',
                '/Debits?\s*[:\-]\s*[-\$]*([\d,]+\.\d{2})/i',
                '/Money\s+Out\s*[-\$]*([\d,]+\.\d{2})/i',  // PayPal
                '/Total\s+Withdrawals\/Debits\s*[-\$]*([\d,]+\.\d{2})/i',
                '/\d+\s+Checks\/Debits\s*\n?\$?([\d,]+\.\d{2})/i',  // First United Bank: "99  Checks/Debits $315,118.65"
            ];

            foreach ($debitPatterns as $pattern) {
                if (preg_match($pattern, $fullText, $m)) {
                    $expectedDebits = (float) str_replace(',', '', $m[1]);
                    break;
                }
            }

            // Credit patterns (in order of specificity)
            $creditPatterns = [
                '/Total\s+Deposits\s+and\s+Other\s+Credits\s*[\$]*([\d,]+\.\d{2})/i',  // Wells Fargo
                '/Total\s+Deposits\s*[:\+]?\s*[\$]*([\d,]+\.\d{2})/i',
                '/Deposits\s*[:\+]\s*[\$]*([\d,]+\.\d{2})/i',
                '/Total\s+Credits?\s*[:\+]?\s*[\$]*([\d,]+\.\d{2})/i',
                '/Credits?\s*[:\+]\s*[\$]*([\d,]+\.\d{2})/i',
                '/Money\s+In\s*[\$]*([\d,]+\.\d{2})/i',  // PayPal
                '/Total\s+Deposits\/Credits\s*[\$]*([\d,]+\.\d{2})/i',
                '/\d+\s+Deposits\/Credits\s*\n?\$?([\d,]+\.\d{2})/i',  // First United Bank: "29  Deposits/Credits $257,395.20"
            ];

            foreach ($creditPatterns as $pattern) {
                if (preg_match($pattern, $fullText, $m)) {
                    $expectedCredits = (float) str_replace(',', '', $m[1]);
                    break;
                }
            }

            // Log what we found
            \Log::info("SmartMCA Validation: Expected totals (regex fallback) - Debits: \$" . ($expectedDebits ?? 'N/A') . ", Credits: \$" . ($expectedCredits ?? 'N/A'));
        }

        // Calculate extracted totals
        $extractedDebits = 0;
        $extractedCredits = 0;
        $debitCount = 0;
        $creditCount = 0;
        foreach ($transactions as $txn) {
            if ($txn['type'] === 'debit') {
                $extractedDebits += $txn['amount'];
                $debitCount++;
            } else {
                $extractedCredits += $txn['amount'];
                $creditCount++;
            }
        }

        \Log::info("SmartMCA Validation: Extracted totals - Debits: \${$extractedDebits} ({$debitCount} txns), Credits: \${$extractedCredits} ({$creditCount} txns)");

        // Also validate transaction counts if we have expected counts from page 1
        if ($expectedDebitCount !== null && $debitCount !== $expectedDebitCount) {
            \Log::warning("SmartMCA Validation: Debit count mismatch! Expected {$expectedDebitCount} txns, found {$debitCount} txns");
        }
        if ($expectedCreditCount !== null && $creditCount !== $expectedCreditCount) {
            \Log::warning("SmartMCA Validation: Credit count mismatch! Expected {$expectedCreditCount} txns, found {$creditCount} txns");
        }

        // Log validation results
        $significantDebitGap = false;
        $significantCreditGap = false;

        if ($expectedDebits !== null) {
            $debitGap = $expectedDebits - $extractedDebits;
            if (abs($debitGap) > 1.00) {
                \Log::warning("SmartMCA Validation: Debit mismatch! Expected \${$expectedDebits}, extracted \${$extractedDebits} (gap: \${$debitGap})");

                // If we're missing significant debits, try dual AI verification
                if ($debitGap > 100) {
                    // Step 1: Try regex recovery first
                    $this->attemptDebitRecovery($fullText, $transactions, $debitGap);

                    // Recalculate after regex recovery
                    $extractedDebits = array_sum(array_map(fn($t) => $t['type'] === 'debit' ? $t['amount'] : 0, $transactions));
                    $debitCount = count(array_filter($transactions, fn($t) => $t['type'] === 'debit'));
                    $debitGap = $expectedDebits - $extractedDebits;

                    // Step 2: If still significant gap, try OpenAI verification
                    if ($debitGap > 50) {
                        \Log::info("SmartMCA: Starting OpenAI verification for debit gap of \${$debitGap}");
                        $this->attemptOpenAIVerification($fullText, $transactions, $debitGap, 'debit');

                        // Recalculate
                        $extractedDebits = array_sum(array_map(fn($t) => $t['type'] === 'debit' ? $t['amount'] : 0, $transactions));
                        $debitCount = count(array_filter($transactions, fn($t) => $t['type'] === 'debit'));
                        $debitGap = $expectedDebits - $extractedDebits;
                    }

                    // Step 3: If still gap, try Claude verification as second check
                    if ($debitGap > 50) {
                        \Log::info("SmartMCA: Starting Claude verification for debit gap of \${$debitGap}");
                        $this->attemptClaudeRecovery($fullText, $transactions, $debitGap, 'debit');

                        // Recalculate
                        $extractedDebits = array_sum(array_map(fn($t) => $t['type'] === 'debit' ? $t['amount'] : 0, $transactions));
                        $debitCount = count(array_filter($transactions, fn($t) => $t['type'] === 'debit'));
                        $debitGap = $expectedDebits - $extractedDebits;
                    }

                    // Log remaining gap for review
                    if (abs($debitGap) > 10) {
                        \Log::warning("SmartMCA: Debit gap of \${$debitGap} remains ({$debitCount}/{$expectedDebitCount} txns) - manual review recommended");
                    }

                    $significantDebitGap = abs($debitGap) > 10;
                }
            } else {
                \Log::info("SmartMCA Validation: Debits MATCH - \${$extractedDebits} ({$debitCount} txns)");
            }
        }

        if ($expectedCredits !== null) {
            $creditGap = $expectedCredits - $extractedCredits;
            if (abs($creditGap) > 1.00) {
                \Log::warning("SmartMCA Validation: Credit mismatch! Expected \${$expectedCredits}, extracted \${$extractedCredits} (gap: \${$creditGap})");

                // If we're missing significant credits, try recovery
                if ($creditGap > 100) {
                    // Step 1: Try OpenAI verification for credits
                    \Log::info("SmartMCA: Starting OpenAI verification for credit gap of \${$creditGap}");
                    $this->attemptOpenAIVerification($fullText, $transactions, $creditGap, 'credit');

                    // Recalculate
                    $extractedCredits = array_sum(array_map(fn($t) => $t['type'] === 'credit' ? $t['amount'] : 0, $transactions));
                    $creditCount = count(array_filter($transactions, fn($t) => $t['type'] === 'credit'));
                    $creditGap = $expectedCredits - $extractedCredits;

                    // Step 2: If still gap, try Claude verification
                    if ($creditGap > 50) {
                        \Log::info("SmartMCA: Starting Claude verification for credit gap of \${$creditGap}");
                        $this->attemptClaudeRecovery($fullText, $transactions, $creditGap, 'credit');

                        // Recalculate
                        $extractedCredits = array_sum(array_map(fn($t) => $t['type'] === 'credit' ? $t['amount'] : 0, $transactions));
                        $creditCount = count(array_filter($transactions, fn($t) => $t['type'] === 'credit'));
                        $creditGap = $expectedCredits - $extractedCredits;
                    }

                    // Log remaining gap for review
                    if (abs($creditGap) > 10) {
                        \Log::warning("SmartMCA: Credit gap of \${$creditGap} remains ({$creditCount}/{$expectedCreditCount} txns) - manual review recommended");
                    }
                }

                $significantCreditGap = abs($creditGap) > 10;
            } else {
                \Log::info("SmartMCA Validation: Credits MATCH - \${$extractedCredits} ({$creditCount} txns)");
            }
        }

        // Log final status
        if ($significantDebitGap || $significantCreditGap) {
            \Log::warning('SmartMCA: Significant gap remains after all recovery attempts');
        } else if ($expectedDebits !== null || $expectedCredits !== null) {
            \Log::info('SmartMCA Validation: All gaps resolved or within tolerance');
        }
    }

    /**
     * Attempt to recover missing transactions using a targeted Claude pass
     */
    private function attemptClaudeRecovery(string $text, array &$transactions, float $gap, string $type): void
    {
        $anthropicKey = env('ANTHROPIC_API_KEY');
        if (empty($anthropicKey)) {
            return;
        }

        // Build list of existing amounts to exclude
        $existingAmounts = [];
        foreach ($transactions as $txn) {
            if ($txn['type'] === $type) {
                $existingAmounts[] = number_format($txn['amount'], 2);
            }
        }
        $existingList = implode(', ', array_slice($existingAmounts, 0, 50));

        $typeLabel = $type === 'debit' ? 'DEBIT (withdrawal/payment/check/fee)' : 'CREDIT (deposit/refund/transfer in)';
        $typeExamples = $type === 'debit'
            ? "checks (Check #XXXX), fees, ACH debits, wire transfers OUT, Zelle TO, purchases, withdrawals"
            : "deposits, ACH credits, wire transfers IN, Zelle FROM, refunds";

        $prompt = <<<PROMPT
MISSION: Find MISSING {$type} transactions in a bank statement.

═══════════════════════════════════════════════════════════════
                         PROBLEM DESCRIPTION
═══════════════════════════════════════════════════════════════

We extracted {$type} transactions but are SHORT by approximately \${$gap}.
This means we MISSED some {$type} transactions.

ALREADY EXTRACTED (DO NOT INCLUDE THESE AMOUNTS):
{$existingList}

═══════════════════════════════════════════════════════════════
                         WHAT TO LOOK FOR
═══════════════════════════════════════════════════════════════

Search specifically for these {$typeLabel} items:
• {$typeExamples}
• Small fees or charges that may have been overlooked
• Transactions near page breaks or section boundaries
• Transactions with unusual formatting or multi-line descriptions
• Transactions in "(continued)" sections

═══════════════════════════════════════════════════════════════
                         RULES
═══════════════════════════════════════════════════════════════

1. Only return transactions ACTUALLY present in the statement text
2. Do NOT fabricate or guess transactions
3. Do NOT include amounts already in the "ALREADY EXTRACTED" list
4. Each transaction must have a clear date, description, and amount
5. Amount must be POSITIVE (no $ signs, no commas)
6. Date format: YYYY-MM-DD

Return JSON only:
{"transactions": [{"date": "YYYY-MM-DD", "description": "exact text from statement", "amount": 0.00, "type": "{$type}"}]}

If no additional transactions found, return: {"transactions": []}
PROMPT;

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-key' => $anthropicKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-haiku-4-20250514',
                    'max_tokens' => 4096,
                    'system' => $prompt,
                    'messages' => [
                        ['role' => 'user', 'content' => "Find the missing {$type} transactions in this text:\n\n" . substr($text, 0, 50000)]
                    ],
                ]);

            if (!$response->successful()) {
                \Log::warning('SmartMCA Recovery: Claude API failed - ' . $response->status());
                return;
            }

            $responseData = $response->json();
            $resultText = $responseData['content'][0]['text'] ?? '';

            // Extract JSON
            $jsonStart = strpos($resultText, '{');
            $jsonEnd = strrpos($resultText, '}');
            if ($jsonStart !== false && $jsonEnd !== false) {
                $resultText = substr($resultText, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            $result = json_decode($resultText, true);
            $newTxns = $result['transactions'] ?? [];

            $addedCount = 0;
            $addedTotal = 0;
            $remainingGap = $gap;

            foreach ($newTxns as $txn) {
                if (!isset($txn['amount']) || $txn['amount'] <= 0) continue;

                $amount = (float) str_replace(['$', ','], '', $txn['amount']);
                $amountKey = number_format($amount, 2);

                // Skip if already exists
                if (in_array($amountKey, $existingAmounts)) continue;

                // Calculate if adding this transaction improves the situation
                $currentGap = $gap - $addedTotal;
                $newGap = abs($currentGap - $amount);

                // Skip if adding this makes the gap worse (overshoots more than it closes)
                if ($newGap > $currentGap && $amount > $currentGap * 1.5) {
                    \Log::debug("SmartMCA Recovery: Skipping \${$amount} - would make gap worse (current: \${$currentGap}, after: \${$newGap})");
                    continue;
                }

                $transactions[] = [
                    'date' => $txn['date'] ?? date('Y-m-d'),
                    'description' => $txn['description'] ?? 'Recovered transaction',
                    'amount' => $amount,
                    'type' => $type,
                    'confidence' => 0.70,
                    'confidence_label' => 'medium',
                    'detected_bank' => 'Recovery',
                ];
                $existingAmounts[] = $amountKey;
                $addedCount++;
                $addedTotal += $amount;

                // Stop if we've closed the gap (within tolerance)
                $remainingGap = abs($gap - $addedTotal);
                if ($remainingGap < 50) {
                    \Log::info("SmartMCA Recovery: Gap closed to \${$remainingGap}");
                    break;
                }
            }

            if ($addedCount > 0) {
                \Log::info("SmartMCA Recovery: Claude found {$addedCount} transactions totaling \${$addedTotal}");
            }

        } catch (\Exception $e) {
            \Log::warning('SmartMCA Recovery: Exception - ' . $e->getMessage());
        }
    }

    /**
     * Attempt to verify and recover missing transactions using OpenAI
     */
    private function attemptOpenAIVerification(string $text, array &$transactions, float $gap, string $type): void
    {
        $openaiKey = env('OPENAI_API_KEY');
        if (empty($openaiKey)) {
            \Log::warning('SmartMCA: OpenAI API key not configured for verification');
            return;
        }

        // Build list of existing amounts to exclude
        $existingAmounts = [];
        $existingDescriptions = [];
        foreach ($transactions as $txn) {
            if ($txn['type'] === $type) {
                $existingAmounts[] = number_format($txn['amount'], 2);
                $existingDescriptions[] = substr($txn['description'], 0, 30);
            }
        }
        $existingList = implode(', $', array_slice($existingAmounts, 0, 30));

        $typeLabel = $type === 'debit' ? 'withdrawals, payments, checks, fees, ACH debits, Zelle TO' : 'deposits, credits, refunds, ACH credits, Zelle FROM';
        $typeExamples = $type === 'debit'
            ? "• Check transactions (Check #1234, CHK 1234)\n• Fees (monthly fee, wire fee, ATM fee, overdraft)\n• ACH debits and electronic payments\n• Zelle TO [name] (sending money)\n• Wire transfers OUT\n• Bill payments"
            : "• Deposits (mobile, cash, check)\n• ACH credits and direct deposits\n• Zelle FROM [name] (receiving money)\n• Wire transfers IN\n• Refunds";

        $systemPrompt = <<<PROMPT
ROLE: Bank statement auditor performing transaction verification.

═══════════════════════════════════════════════════════════════
                         VERIFICATION TASK
═══════════════════════════════════════════════════════════════

We extracted {$type} transactions but have a GAP of approximately \${$gap}.
This means we MISSED some {$type} transactions.

ALREADY EXTRACTED (DO NOT INCLUDE THESE AMOUNTS):
\${$existingList}

═══════════════════════════════════════════════════════════════
                         SEARCH TARGETS
═══════════════════════════════════════════════════════════════

Search the statement text for these {$typeLabel}:
{$typeExamples}

Also look for:
• Transactions near page breaks that may have been split
• Multi-line transactions where description continues on next line
• Small fees or charges (even \$0.01)
• Transactions in "(continued)" sections
• Items with unusual formatting

═══════════════════════════════════════════════════════════════
                         STRICT RULES
═══════════════════════════════════════════════════════════════

1. ONLY return transactions that ACTUALLY appear in the statement text
2. Do NOT fabricate, guess, or estimate transactions
3. Do NOT include amounts already in the "ALREADY EXTRACTED" list
4. Each transaction MUST have: date, description, amount clearly visible in text
5. Amount format: POSITIVE number (e.g., 1234.56 - no $ or commas)
6. Date format: YYYY-MM-DD

Return JSON:
{"transactions": [{"date": "YYYY-MM-DD", "description": "exact text from statement", "amount": 0.00, "type": "{$type}"}], "verification_notes": "what you found"}

If nothing found: {"transactions": [], "verification_notes": "No additional transactions found"}
PROMPT;

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $openaiKey,
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'max_tokens' => 4096,
                    'temperature' => 0.1,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => "Find missing {$type} transactions in this bank statement:\n\n" . substr($text, 0, 50000)]
                    ],
                ]);

            if (!$response->successful()) {
                \Log::warning('SmartMCA OpenAI Verification: API failed - ' . $response->status());
                return;
            }

            $responseData = $response->json();
            $resultText = $responseData['choices'][0]['message']['content'] ?? '';

            // Log token usage
            $usage = $responseData['usage'] ?? [];
            $inputTokens = $usage['prompt_tokens'] ?? 0;
            $outputTokens = $usage['completion_tokens'] ?? 0;
            \Log::info("SmartMCA OpenAI Verification: {$inputTokens} input + {$outputTokens} output tokens");

            // Extract JSON
            $jsonStart = strpos($resultText, '{');
            $jsonEnd = strrpos($resultText, '}');
            if ($jsonStart !== false && $jsonEnd !== false) {
                $resultText = substr($resultText, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            $result = json_decode($resultText, true);
            $newTxns = $result['transactions'] ?? [];
            $notes = $result['verification_notes'] ?? '';

            if (!empty($notes)) {
                \Log::info("SmartMCA OpenAI Verification: {$notes}");
            }

            $addedCount = 0;
            $addedTotal = 0;

            foreach ($newTxns as $txn) {
                if (!isset($txn['amount']) || $txn['amount'] <= 0) continue;

                $amount = (float) str_replace(['$', ','], '', $txn['amount']);
                $amountKey = number_format($amount, 2);

                // Skip if already exists
                if (in_array($amountKey, $existingAmounts)) continue;

                // Skip very small or unreasonably large amounts
                if ($amount < 1 || $amount > 100000) continue;

                $transactions[] = [
                    'date' => $txn['date'] ?? date('Y-m-d'),
                    'description' => $txn['description'] ?? 'OpenAI recovered transaction',
                    'amount' => $amount,
                    'type' => $type,
                    'confidence' => 0.75,
                    'confidence_label' => 'medium',
                    'detected_bank' => 'OpenAI-Verification',
                ];
                $existingAmounts[] = $amountKey;
                $addedCount++;
                $addedTotal += $amount;
            }

            if ($addedCount > 0) {
                \Log::info("SmartMCA OpenAI Verification: Found {$addedCount} transactions totaling \${$addedTotal}");
            } else {
                \Log::info("SmartMCA OpenAI Verification: No additional transactions found");
            }

        } catch (\Exception $e) {
            \Log::warning('SmartMCA OpenAI Verification: Exception - ' . $e->getMessage());
        }
    }

    /**
     * Attempt to recover missing debit transactions from statement text
     */
    private function attemptDebitRecovery(string $text, array &$transactions, float $gap): void
    {
        // Build a set of existing transaction amounts for deduplication
        $existingAmounts = [];
        foreach ($transactions as $txn) {
            $key = number_format($txn['amount'], 2);
            $existingAmounts[$key] = ($existingAmounts[$key] ?? 0) + 1;
        }

        $recovered = [];
        $remainingGap = $gap;

        // Look for common Wells Fargo debit patterns that might have been missed
        // Pattern: Date followed by description and negative amount
        // Example: "08/15 PURCHASE AUTHORIZED ON 08/14 AMAZON.COM -45.99"
        preg_match_all('/(\d{2}\/\d{2})\s+([A-Z][^\n]+?)\s+(-?[\d,]+\.\d{2})\s*$/m', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $date = $match[1];
            $desc = trim($match[2]);
            $amount = abs((float) str_replace(',', '', $match[3]));

            // Skip very small or very large amounts
            if ($amount < 1 || $amount > 50000) {
                continue;
            }

            // Skip if this amount already exists
            $amountKey = number_format($amount, 2);
            if (isset($existingAmounts[$amountKey]) && $existingAmounts[$amountKey] > 0) {
                continue;
            }

            // Skip balance lines
            if (preg_match('/balance|total|statement|page/i', $desc)) {
                continue;
            }

            // Check if this amount would help close the gap
            if ($amount <= $remainingGap + 100) {
                // Normalize date to YYYY-MM-DD
                $normalizedDate = '2024-' . $date; // Assume current year

                $recovered[] = [
                    'date' => $normalizedDate,
                    'description' => $desc,
                    'amount' => $amount,
                    'type' => 'debit',
                    'confidence' => 0.75,
                    'confidence_label' => 'medium',
                ];

                $existingAmounts[$amountKey] = 1;
                $remainingGap -= $amount;

                \Log::info("SmartMCA Recovery: Added debit - {$desc} \${$amount}");

                if ($remainingGap < 10) {
                    break;
                }
            }
        }

        // Add recovered transactions
        foreach ($recovered as $txn) {
            $transactions[] = $txn;
        }

        if (count($recovered) > 0) {
            \Log::info('SmartMCA Recovery: Recovered ' . count($recovered) . " transactions, remaining gap: \${$remainingGap}");
        }
    }

    /**
     * Parse transactions using OpenAI GPT-4o via Python script (parse_transactions_openai.py)
     */
    private function parseTransactionsWithAI(string $text, ?array $bankContext = null): array
    {
        $openaiKey = env('OPENAI_API_KEY');

        if (empty($openaiKey)) {
            \Log::error('SmartMCA: OpenAI API key not configured');
            return [];
        }

        // Detect bank if not already provided
        if ($bankContext === null) {
            $bankPatternService = new BankPatternService;
            $bankContext = $bankPatternService->buildAIContext($text);
        }

        $detectedBank = $bankContext['detected_bank'] ?? 'Unknown';

        // Save text to temp file
        $tempFile = storage_path('app/uploads/temp_' . Str::random(10) . '.txt');
        file_put_contents($tempFile, $text);

        // Save bank context to patterns file
        $patternsFile = storage_path('app/uploads/patterns_' . Str::random(10) . '.json');
        file_put_contents($patternsFile, json_encode(['bank_context' => $bankContext]));

        // Output file for results
        $outputFile = storage_path('app/uploads/output_' . Str::random(10) . '.json');

        try {
            $scriptPath = storage_path('app/scripts/parse_transactions_openai.py');

            $command = 'timeout 300 /var/www/html/crmfinity_underwriting/crmfinity-ai/venv/bin/python3 ' . escapeshellarg($scriptPath)
                . ' ' . escapeshellarg($tempFile)
                . ' ' . escapeshellarg($openaiKey)
                . ' ' . escapeshellarg($patternsFile)
                . ' ' . escapeshellarg($outputFile)
                . ' 2>&1';

            $output = shell_exec($command);

            // Cleanup temp files
            @unlink($tempFile);
            @unlink($patternsFile);

            // Read result
            $result = null;
            if (file_exists($outputFile)) {
                $jsonContent = file_get_contents($outputFile);
                $result = json_decode($jsonContent, true);
                @unlink($outputFile);
            }

            if (empty($result)) {
                \Log::warning('SmartMCA: Empty result from OpenAI script');
                \Log::debug('SmartMCA stdout: ' . substr($output ?? '', 0, 500));
                return [];
            }

            // Log API usage from stderr
            if ($output && preg_match('/API Usage: (.+)/', $output, $apiMatch)) {
                \Log::info('SmartMCA OpenAI API: ' . $apiMatch[1]);
            }

            // Log validation warnings
            if ($output && preg_match('/WARNING: Debit mismatch! (.+)/', $output, $debitWarn)) {
                \Log::warning('SmartMCA Validation: Debit mismatch - ' . $debitWarn[1]);
            }
            if ($output && preg_match('/WARNING: Credit mismatch! (.+)/', $output, $creditWarn)) {
                \Log::warning('SmartMCA Validation: Credit mismatch - ' . $creditWarn[1]);
            }

            if ($result['success'] && isset($result['transactions'])) {
                $transactions = $result['transactions'];
                $txnCount = count($transactions);
                $finishReason = $result['finish_reason'] ?? 'unknown';

                // Add bank info to each transaction
                foreach ($transactions as &$txn) {
                    $txn['detected_bank'] = $detectedBank;
                    $txn['source'] = 'OpenAI';
                }

                \Log::info("SmartMCA OpenAI: Parsed {$txnCount} transactions from {$detectedBank} (finish_reason: {$finishReason})");

                // Log validation status
                if (isset($result['debit_match']) && !$result['debit_match']) {
                    $expected = $result['expected_debits'] ?? 0;
                    $extracted = $result['extracted_debits'] ?? 0;
                    \Log::warning("SmartMCA OpenAI: Debit mismatch - Expected \${$expected}, Got \${$extracted}");
                }

                return $transactions;
            }

            if (isset($result['error'])) {
                \Log::error('SmartMCA OpenAI: ' . $result['error']);
            }

            return [];

        } catch (\Exception $e) {
            \Log::error('SmartMCA OpenAI Exception: ' . $e->getMessage());
            @unlink($tempFile);
            @unlink($patternsFile);
            if (isset($outputFile) && file_exists($outputFile)) {
                @unlink($outputFile);
            }
            return [];
        }
    }

    /**
     * Parse transactions using Claude via Python script (parse_transactions_ai.py)
     */
    private function parseTransactionsWithClaude(string $text, ?array $bankContext = null): array
    {
        $anthropicKey = env('ANTHROPIC_API_KEY');

        if (empty($anthropicKey)) {
            \Log::warning('SmartMCA: Anthropic API key not configured for Claude parsing');
            return [];
        }

        // Detect bank if not already provided
        if ($bankContext === null) {
            $bankPatternService = new BankPatternService;
            $bankContext = $bankPatternService->buildAIContext($text);
        }

        $detectedBank = $bankContext['detected_bank'] ?? 'Unknown';
        $learnedPatterns = $this->getLearnedPatternsForAI();

        \Log::info("SmartMCA: Calling Claude via parse_transactions_ai.py script");

        // Write text to temp file
        $tempFile = storage_path('app/uploads/text_' . \Illuminate\Support\Str::random(10) . '.txt');
        file_put_contents($tempFile, $text);

        // Write patterns + bank context to JSON file
        $patternsData = [
            'learned_patterns' => $learnedPatterns,
            'bank_context' => $bankContext
        ];
        $patternsFile = storage_path('app/uploads/patterns_' . \Illuminate\Support\Str::random(10) . '.json');
        file_put_contents($patternsFile, json_encode($patternsData));

        // Output file for results
        $outputFile = storage_path('app/uploads/output_' . \Illuminate\Support\Str::random(10) . '.json');

        try {
            $scriptPath = storage_path('app/scripts/parse_transactions_ai.py');

            $command = 'timeout 300 /var/www/html/crmfinity_underwriting/crmfinity-ai/venv/bin/python3 ' . escapeshellarg($scriptPath)
                . ' ' . escapeshellarg($tempFile)
                . ' ' . escapeshellarg($anthropicKey)
                . ' ' . escapeshellarg($patternsFile)
                . ' ' . escapeshellarg($outputFile)
                . ' 2>&1';

            $output = shell_exec($command);

            // Cleanup temp files
            @unlink($tempFile);
            @unlink($patternsFile);

            // Read result
            $result = null;
            if (file_exists($outputFile)) {
                $jsonContent = file_get_contents($outputFile);
                $result = json_decode($jsonContent, true);
                @unlink($outputFile);
            }

            if (empty($result)) {
                \Log::warning('SmartMCA Claude: Empty result from Python script');
                \Log::debug('SmartMCA stdout: ' . substr($output ?? '', 0, 500));
                return [];
            }

            // Log API usage from script output (Claude logs to stderr)
            if ($output && preg_match('/Claude API Usage.*?: (.+)/', $output, $apiMatch)) {
                \Log::info('SmartMCA Claude API: ' . $apiMatch[1]);
            }

            // Log validation warnings
            if ($output && preg_match('/WARNING: (.+)/', $output, $warnMatch)) {
                \Log::warning('SmartMCA Claude: ' . $warnMatch[1]);
            }

            if ($result['success'] && isset($result['transactions'])) {
                $transactions = $result['transactions'];
                $txnCount = count($transactions);
                $finishReason = $result['finish_reason'] ?? 'unknown';

                // Add bank info to each transaction
                foreach ($transactions as &$txn) {
                    $txn['detected_bank'] = $detectedBank;
                    $txn['source'] = 'Claude'; // Mark source
                }

                \Log::info("SmartMCA Claude: Parsed {$txnCount} transactions from {$detectedBank} (finish_reason: {$finishReason})");

                return $transactions;
            }

            if (isset($result['error'])) {
                \Log::error('SmartMCA Claude: ' . $result['error']);
            }

            return [];

        } catch (\Exception $e) {
            \Log::error('SmartMCA Claude Exception: ' . $e->getMessage());
            @unlink($tempFile);
            @unlink($patternsFile);
            if (isset($outputFile) && file_exists($outputFile)) {
                @unlink($outputFile);
            }
            return [];
        }
    }

    /**
     * AI parsing - uses OpenAI only for transaction extraction
     * (Claude disabled per user request for faster, simpler processing)
     */
    private function parseTransactionsWithDualAI(string $text, ?array $bankContext = null): array
    {
        \Log::info('===== OPENAI PARSING START =====');
        \Log::info('SmartMCA: Parsing with OpenAI only');
        \Log::info('Text length: ' . strlen($text) . ' characters');

        // Parse with OpenAI (primary - faster and proven reliable)
        $openaiTransactions = $this->parseTransactionsWithAI($text, $bankContext);
        $openaiCount = count($openaiTransactions);
        \Log::info("SmartMCA: OpenAI extracted {$openaiCount} transactions");

        return $openaiTransactions;
    }

    /**
     * Merge transaction results from two AI sources, deduplicating by date+amount+description
     */
    private function mergeTransactionResults(array $transactions1, array $transactions2): array
    {
        $merged = [];
        $seen = [];

        // Helper to create unique key for transaction
        $makeKey = function($txn) {
            $date = $txn['date'] ?? '';
            $amount = number_format((float)($txn['amount'] ?? 0), 2);
            $desc = strtolower(substr($txn['description'] ?? '', 0, 20));
            return "{$date}|{$amount}|{$desc}";
        };

        // Add all from first source
        foreach ($transactions1 as $txn) {
            $key = $makeKey($txn);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $txn['source'] = $txn['source'] ?? 'OpenAI';
                $merged[] = $txn;
            }
        }

        // Add unique transactions from second source
        $addedFromClaude = 0;
        foreach ($transactions2 as $txn) {
            $key = $makeKey($txn);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $txn['source'] = $txn['source'] ?? 'Claude';
                $txn['confidence'] = max(0.7, ($txn['confidence'] ?? 0.8) - 0.05); // Slightly lower confidence for additional finds
                $merged[] = $txn;
                $addedFromClaude++;
            }
        }

        if ($addedFromClaude > 0) {
            \Log::info("SmartMCA Dual AI: Claude found {$addedFromClaude} additional transactions not in OpenAI results");
        }

        // Sort by date
        usort($merged, function($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });

        return $merged;
    }

    /**
     * Build Claude system prompt for transaction parsing
     */
    private function buildClaudeSystemPrompt(array $bankContext, array $learnedPatterns): string
    {
        $detectedBank = $bankContext['detected_bank'] ?? 'Unknown';
        $bankPrompt = $bankContext['bank_prompt'] ?? '';

        $learnedSection = "";
        if (!empty($learnedPatterns)) {
            $learnedSection = "\n\n═══ USER-CORRECTED PATTERNS (Override other rules) ═══\n";
            foreach (array_slice($learnedPatterns, 0, 50) as $pattern) {
                $learnedSection .= "• \"{$pattern['description_pattern']}\" → {$pattern['correct_type']}\n";
            }
        }

        return <<<PROMPT
You are an expert bank statement transaction extractor with 100% accuracy requirements. Missing even ONE transaction is unacceptable.

DETECTED BANK: {$detectedBank}
{$bankPrompt}

═══════════════════════════════════════════════════════════════
                    CHAIN-OF-THOUGHT EXTRACTION PROCESS
═══════════════════════════════════════════════════════════════

For EACH transaction, follow this reasoning process:

STEP 1 - IDENTIFY: "I see a line with date [X], description [Y], amount [Z]"
STEP 2 - CONTEXT: "This appears in the [section name] section / [column name] column"
STEP 3 - CLASSIFY: "Based on [context clues], this is money [IN/OUT] = [credit/debit]"
STEP 4 - VERIFY: "The amount [increases/decreases] the balance, confirming [credit/debit]"
STEP 5 - EXTRACT: Output the transaction with appropriate confidence

═══════════════════════════════════════════════════════════════
                    OUTPUT FORMAT REQUIREMENTS
═══════════════════════════════════════════════════════════════

For each transaction, provide:
• date: YYYY-MM-DD format (convert MM/DD using statement year, typically 2024/2025)
• description: Exact text from statement (preserve original wording)
• amount: Positive decimal only (e.g., 1234.56 - NO $ signs, NO commas, NO negatives)
• type: "credit" or "debit" (lowercase)
• confidence: "high", "medium", or "low"

═══════════════════════════════════════════════════════════════
                    BANK-SPECIFIC EXAMPLES
═══════════════════════════════════════════════════════════════

*** CHASE BANK ***
Format: Date | Description | Amount | Balance (running)
- "01/15 ORIG CO NAME:EMPLOYER INC ORIG ID:XXXXX $2,500.00 $5,000.00" → CREDIT (Direct deposit)
- "01/16 CHASE CREDIT CRD AUTOPAY $500.00 $4,500.00" → DEBIT (Credit card payment)
- "01/17 ZELLE PAYMENT TO JOHN DOE $100.00" → DEBIT (Zelle sent)
- "01/18 ZELLE PAYMENT FROM JANE DOE $50.00" → CREDIT (Zelle received)

*** BANK OF AMERICA ***
Format: Separate Deposits/Withdrawals sections
- Under "Deposits and Other Additions": ALL are CREDITS
- Under "Withdrawals and Other Subtractions": ALL are DEBITS
- "MOBILE CHECK DEPOSIT" → CREDIT
- "KEEP THE CHANGE TRANSFER TO" → DEBIT (savings transfer)

*** WELLS FARGO ***
Format: Date | Description | Withdrawals | Deposits | Balance
- Amount in "Withdrawals" column → DEBIT
- Amount in "Deposits" column → CREDIT
- "ONLINE TRANSFER FROM SAV...XXXXX" → CREDIT (transfer in)
- "ONLINE TRANSFER TO SAV...XXXXX" → DEBIT (transfer out)

*** CITIZENS BANK ***
Format: Multi-line (Date on line 1, Amount on line 2, Description on line 3)
- "04/07\n198.80\n6347 POS DEBIT - AMAZON" → DEBIT (POS purchase)
- "04/08\n2,000.00\nDEPOSIT" → CREDIT (deposit)
- "DBT RETURN" or "RETURN ITEM" → Usually CREDIT (refund)

*** TD BANK ***
- "FUNDS TRANSFER CREDIT" → CREDIT
- "FUNDS TRANSFER DEBIT" → DEBIT
- "VISA DDA PUR" → DEBIT (Visa purchase)
- "VISA CREDIT" → CREDIT (Visa refund)

*** PNC BANK ***
- "ACH CREDIT" in description → CREDIT
- "ACH DEBIT" in description → DEBIT
- "TRANSFER FROM [account]" → CREDIT
- "TRANSFER TO [account]" → DEBIT

═══════════════════════════════════════════════════════════════
                    CREDIT vs DEBIT CLASSIFICATION
═══════════════════════════════════════════════════════════════

*** CREDITS (Money INTO the account) ***
✓ Deposits (cash, check, mobile deposit)
✓ Direct deposits (payroll, Social Security)
✓ ACH credits / Electronic deposits
✓ Wire transfers IN
✓ Zelle/Venmo RECEIVED ("Zelle from...", "Zelle credit from...")
✓ Refunds and reversals ("RETURN", "REFUND", "REVERSAL")
✓ Interest earned
✓ Transfer FROM another account (internal transfer in)
✓ PayPal: "Money received", "Payment received", positive amounts

*** DEBITS (Money OUT of the account) ***
✓ Checks written (Check #1234, CHK 1234)
✓ Withdrawals (ATM, cash)
✓ Purchases (POS, debit card, online, "PURCHASE", "PUR")
✓ Bill payments (utilities, rent, subscriptions)
✓ ACH debits / Electronic payments
✓ Wire transfers OUT
✓ Zelle/Venmo SENT ("Zelle to...", "Zelle payment to...")
✓ ALL fees (monthly fee, overdraft, NSF, wire fee, ATM fee)
✓ Transfer TO another account (internal transfer out)
✓ Loan payments
✓ PayPal: "Payment sent", "Purchase", negative amounts, transaction fees

═══════════════════════════════════════════════════════════════
                    CRITICAL: DIRECTION KEYWORDS
═══════════════════════════════════════════════════════════════

MONEY COMING IN (CREDIT):
• "FROM", "RECEIVED", "CREDIT", "DEPOSIT", "INCOMING"
• "ZELLE FROM", "VENMO FROM", "CASHAPP FROM"
• "TRANSFER FROM", "ACH CREDIT", "WIRE IN"
• "REFUND", "RETURN", "REVERSAL", "CASHBACK"

MONEY GOING OUT (DEBIT):
• "TO", "SENT", "PAYMENT", "PURCHASE", "WITHDRAWAL"
• "ZELLE TO", "VENMO TO", "CASHAPP TO"
• "TRANSFER TO", "ACH DEBIT", "WIRE OUT"
• "FEE", "CHARGE", "CHECK", "ATM", "POS"

═══════════════════════════════════════════════════════════════
                    SKIP THESE (NOT transactions)
═══════════════════════════════════════════════════════════════

✗ "Beginning Balance", "Opening Balance", "Balance Forward"
✗ "Ending Balance", "Closing Balance"
✗ Summary lines: "Total Deposits", "Total Withdrawals", "XX Deposits/Credits", "XX Checks/Debits"
✗ Page headers/footers, page numbers
✗ Account numbers, statement period info
✗ Column headers (Date, Description, Amount, Balance)
✗ Running balance values (these are NOT transaction amounts)

═══════════════════════════════════════════════════════════════
                    SPECIAL HANDLING RULES
═══════════════════════════════════════════════════════════════

1. SECTION HEADERS: "Credit Transactions" section = all CREDITS, "Debit Transactions" section = all DEBITS
2. CHECK TRANSACTIONS: Include check number (e.g., "Check #1234 - Payee Name")
3. MULTI-LINE DESCRIPTIONS: Combine continuation lines into one transaction
4. CONTINUATION PAGES: "(continued)" or "continued from previous page" has more transactions
5. FEES: Include ALL fees - monthly fees, wire fees, ATM fees, overdraft fees
6. IMAGE PAGES: Skip pages with only check images or deposit slip images (they duplicate data)
7. SMALL AMOUNTS: Include ALL transactions, even $0.01 fees
8. RUNNING BALANCE: Use balance changes to VERIFY your classification (balance up = credit, balance down = debit)
{$learnedSection}

═══════════════════════════════════════════════════════════════

Return ONLY valid JSON (no markdown, no explanation):
{"transactions": [{"date": "YYYY-MM-DD", "description": "...", "amount": 0.00, "type": "credit", "confidence": "high"}]}
PROMPT;
    }

    /**
     * Build Claude user message
     */
    private function buildClaudeUserMessage(string $text): string
    {
        return <<<MSG
TASK: Extract ALL transactions from this bank statement text.

EXTRACTION PROCESS:
1. Identify the bank name and statement period from the header
2. Locate the "Credit Transactions" or "Deposits" section → extract ALL as type "credit"
3. Locate the "Debit Transactions" or "Withdrawals" section → extract ALL as type "debit"
4. Look for check transactions (Check #XXXX) → type "debit"
5. Look for ALL fees (monthly fee, wire fee, etc.) → type "debit"
6. Check for "(continued)" sections on subsequent pages
7. Verify: Does the number of transactions match the summary count on page 1?

BANK STATEMENT TEXT:
═══════════════════════════════════════════════════════════════
{$text}
═══════════════════════════════════════════════════════════════

VERIFICATION CHECKLIST (complete before returning):
□ Extracted ALL items from "Credit Transactions" section
□ Extracted ALL items from "Debit Transactions" section
□ Included ALL check transactions (Check #XXXX)
□ Included ALL fees (monthly fee, wire fee, ATM fee, overdraft, etc.)
□ Checked "(continued)" sections for additional transactions
□ ZELLE TO = DEBIT (sending money), ZELLE FROM = CREDIT (receiving money)
□ All amounts are positive numbers (no $ or commas)
□ All dates in YYYY-MM-DD format

Return ONLY the JSON with ALL transactions. Accuracy is critical.
MSG;
    }

    /**
     * Clean and validate transactions from Claude response
     */
    private function cleanTransactions(array $transactions, string $detectedBank): array
    {
        $cleaned = [];
        $balanceKeywords = ['BALANCE FORWARD', 'BEGINNING BALANCE', 'ENDING BALANCE', 'OPENING BALANCE', 'CLOSING BALANCE'];

        foreach ($transactions as $txn) {
            if (!isset($txn['date'], $txn['description'], $txn['amount'], $txn['type'])) {
                continue;
            }

            // Parse amount
            $amount = (float) str_replace(['$', ','], '', $txn['amount']);
            if ($amount <= 0) {
                continue;
            }

            // Skip balance lines
            $descUpper = strtoupper($txn['description']);
            $isBalance = false;
            foreach ($balanceKeywords as $kw) {
                if (strpos($descUpper, $kw) !== false) {
                    $isBalance = true;
                    break;
                }
            }
            if ($isBalance) {
                continue;
            }

            // Validate type
            $type = strtolower(trim($txn['type']));
            if (!in_array($type, ['credit', 'debit'])) {
                $type = 'debit';
            }

            // Map confidence
            $confStr = strtolower($txn['confidence'] ?? 'medium');
            $confidence = $confStr === 'high' ? 0.95 : ($confStr === 'medium' ? 0.80 : 0.60);

            $cleaned[] = [
                'date' => trim($txn['date']),
                'description' => trim($txn['description']),
                'amount' => $amount,
                'type' => $type,
                'confidence' => $confidence,
                'confidence_label' => $confStr,
                'detected_bank' => $detectedBank,
            ];
        }

        return $cleaned;
    }

    /**
     * Get learned patterns from both corrections and historical corrected transactions
     */
    private function getLearnedPatternsForAI(): array
    {
        // Get from user corrections
        $corrections = TransactionCorrection::getLearnedPatterns();

        // Get from historically corrected transactions
        $historical = AnalyzedTransaction::getLearnedPatterns();

        // Merge and deduplicate
        $allPatterns = array_merge($corrections, $historical);
        $unique = [];
        $seen = [];

        foreach ($allPatterns as $pattern) {
            $key = strtolower($pattern['description_pattern'] ?? $pattern['description_normalized'] ?? '');
            if (! empty($key) && ! isset($seen[$key])) {
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
    private function applyLearnedCorrections(array $transactions): array
    {
        $corrections = TransactionCorrection::all()->keyBy(function ($item) {
            return strtolower($item->description_pattern);
        });

        // Also get corrections from historical data
        $historicalCorrections = AnalyzedTransaction::where('was_corrected', true)
            ->get()
            ->keyBy(function ($item) {
                return strtolower($item->description_normalized);
            });

        if ($corrections->isEmpty() && $historicalCorrections->isEmpty()) {
            return $transactions;
        }

        foreach ($transactions as &$txn) {
            $normalizedDesc = TransactionCorrection::normalizePattern($txn['description']);
            $normalizedDescLower = strtolower($normalizedDesc);

            // Check user corrections first (higher priority)
            // IMPORTANT: Only match if the pattern is found IN the description
            // Skip overly broad patterns (less than 10 chars) to avoid false matches
            $corrected = false;
            foreach ($corrections as $pattern => $correction) {
                // Skip patterns that are too short/broad
                if (strlen($pattern) < 10) {
                    continue;
                }
                // Only check if pattern is found in description (not reverse)
                if (stripos($normalizedDescLower, $pattern) !== false) {
                    if ($txn['type'] !== $correction->correct_type) {
                        $txn['type'] = $correction->correct_type;
                        $txn['corrected'] = true;
                        $txn['confidence'] = 0.99;
                        $txn['confidence_label'] = 'high';
                        $corrected = true;
                    }
                    break;
                }
            }

            // Check historical corrections if not already corrected
            if (! $corrected) {
                foreach ($historicalCorrections as $pattern => $histTxn) {
                    // Skip patterns that are too short/broad
                    if (strlen($pattern) < 10) {
                        continue;
                    }
                    // Only check if pattern is found in description (not reverse)
                    if (stripos($normalizedDescLower, $pattern) !== false) {
                        if ($txn['type'] !== $histTxn->type) {
                            $txn['type'] = $histTxn->type;
                            $txn['corrected'] = true;
                            $txn['confidence'] = 0.95;
                            $txn['confidence_label'] = 'high';
                        }
                        break;
                    }
                }
            }
        }

        return $transactions;
    }

    /**
     * Calculate true revenue from analyzed transactions using TrueRevenueEngine.
     *
     * This method now uses the centralized TrueRevenueEngine for accurate
     * revenue classification with:
     * - 40+ MCA funder detection
     * - Owner injection exclusion
     * - Tax refund exclusion
     * - Card processor settlement recognition
     * - Business day calculations
     */
    public function calculateTrueRevenue(Request $request)
    {
        $request->validate([
            'session_ids' => 'required|array',
            'session_ids.*' => 'string',
            'industry' => 'nullable|string',
        ]);

        $sessionIds = $request->session_ids;
        $industry = $request->industry;

        // Get all sessions
        $sessions = AnalysisSession::whereIn('session_id', $sessionIds)
            ->where('user_id', auth()->id())
            ->get();

        if ($sessions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid sessions found',
            ]);
        }

        // Initialize TrueRevenueEngine
        $engine = new TrueRevenueEngine();

        // Collect all transactions
        $allTransactions = [];
        foreach ($sessions as $session) {
            $transactions = AnalyzedTransaction::where('analysis_session_id', $session->id)
                ->get()
                ->map(function ($txn) {
                    return [
                        'date' => $txn->transaction_date ? $txn->transaction_date->format('Y-m-d') : null,
                        'description' => $txn->description,
                        'amount' => (float) $txn->amount,
                        'type' => $txn->type,
                    ];
                })
                ->toArray();

            $allTransactions = array_merge($allTransactions, $transactions);
        }

        // Calculate True Revenue using TrueRevenueEngine
        $result = $engine->calculateTrueRevenue($allTransactions, $industry);

        // Extract excluded items for display
        $excludedItems = [];
        $needsReviewItems = [];
        foreach ($result['classified_transactions'] as $txn) {
            $classification = $txn['classification_result']['classification'] ?? null;
            if ($classification === TrueRevenueEngine::CLASSIFICATION_EXCLUDED) {
                $excludedItems[] = [
                    'description' => $txn['description'],
                    'amount' => (float) $txn['amount'],
                    'date' => $txn['date'],
                    'reason' => $txn['classification_result']['reason'] ?? 'Unknown',
                ];
            } elseif ($classification === TrueRevenueEngine::CLASSIFICATION_NEEDS_REVIEW) {
                $needsReviewItems[] = [
                    'description' => $txn['description'],
                    'amount' => (float) $txn['amount'],
                    'date' => $txn['date'],
                    'reason' => $txn['classification_result']['reason'] ?? 'Unknown',
                ];
            }
        }

        // Get monthly breakdown for volatility analysis
        $monthlyBreakdown = $engine->getMonthlyBreakdown($allTransactions, $industry);
        $volatility = $engine->getVolatilityMetrics($monthlyBreakdown);

        // Detect MCA payments
        $mcaPayments = $engine->detectMcaPayments($allTransactions);

        // Calculate MCA capacity
        $monthCount = count($monthlyBreakdown);
        $avgMonthlyRevenue = $monthCount > 0 ? $result['true_revenue'] / $monthCount : $result['true_revenue'];
        $capacity = $engine->calculateMcaCapacity($avgMonthlyRevenue, $mcaPayments['total_daily_payment']);

        // Update session status to finalized
        foreach ($sessions as $session) {
            $session->update([
                'status' => 'finalized',
            ]);
        }

        $trueRevenue = $result['true_revenue'];
        $revenueDeposits = $result['counts']['revenue'];
        $totalCredits = $result['total_credits'];
        $totalCreditCount = $result['counts']['total'];
        $excludedAmount = $result['excluded_amount'];
        $excludedCount = $result['counts']['excluded'];
        $needsReviewAmount = $result['needs_review_amount'];
        $needsReviewCount = $result['counts']['needs_review'];
        $revenueRatio = $result['revenue_ratio'];

        \Log::info("SmartMCA True Revenue (TrueRevenueEngine): \${$trueRevenue} from {$revenueDeposits} deposits (excluded: \${$excludedAmount} from {$excludedCount}, needs review: \${$needsReviewAmount} from {$needsReviewCount})");

        // Generate File Control Sheet PDF
        $reportId = sprintf('%d_%05d_%s', auth()->id(), rand(10000, 99999), time());
        $pdfFilename = "fcs_pdf_{$reportId}.pdf";

        // Prepare statement data for PDF
        $statementsData = [];
        foreach ($sessions as $session) {
            $sessionCredits = AnalyzedTransaction::where('analysis_session_id', $session->id)
                ->where('type', 'credit')
                ->sum('amount');
            $sessionDebits = AnalyzedTransaction::where('analysis_session_id', $session->id)
                ->where('type', 'debit')
                ->sum('amount');

            $statementsData[] = [
                'filename' => $session->original_filename ?? 'Unknown',
                'bank_name' => $session->detected_bank ?? 'Not Detected',
                'date_range' => $session->statement_period ?? 'N/A',
                'credits' => $sessionCredits,
                'debits' => $sessionDebits,
            ];
        }

        // Generate PDF
        $pdfData = [
            'report_id' => $reportId,
            'generated_at' => now()->format('F j, Y g:i A'),
            'analyst_name' => auth()->user()->name ?? 'System User',
            'statement_count' => $sessions->count(),
            'total_credits' => round($totalCredits, 2),
            'total_credit_count' => $totalCreditCount,
            'excluded_amount' => round($excludedAmount, 2),
            'excluded_count' => $excludedCount,
            'true_revenue' => round($trueRevenue, 2),
            'revenue_deposits' => $revenueDeposits,
            'revenue_ratio' => $revenueRatio,
            'statements' => $statementsData,
            'excluded_items' => array_slice($excludedItems, 0, 50), // Include up to 50 excluded items in PDF
        ];

        try {
            $pdf = Pdf::loadView('pdf.file-control-sheet', $pdfData);
            $pdf->setPaper('letter', 'portrait');

            // Save PDF to storage
            $pdfPath = 'fcs_reports/'.$pdfFilename;
            \Storage::put($pdfPath, $pdf->output());

            $pdfUrl = route('smartmca.download-fcs', ['filename' => $pdfFilename]);

            \Log::info("SmartMCA: Generated FCS PDF: {$pdfFilename}");
        } catch (\Exception $e) {
            \Log::error('SmartMCA: Failed to generate FCS PDF: '.$e->getMessage());
            $pdfUrl = null;
            $pdfFilename = null;
        }

        return response()->json([
            'success' => true,
            'true_revenue' => round($trueRevenue, 2),
            'revenue_deposits' => $revenueDeposits,
            'total_credits' => round($totalCredits, 2),
            'total_credit_count' => $totalCreditCount,
            'excluded_amount' => round($excludedAmount, 2),
            'excluded_count' => $excludedCount,
            'needs_review_amount' => round($needsReviewAmount, 2),
            'needs_review_count' => $needsReviewCount,
            'revenue_ratio' => $revenueRatio,
            'excluded_items' => array_slice($excludedItems, 0, 20), // Limit to 20 items for display
            'needs_review_items' => array_slice($needsReviewItems, 0, 20),
            'sessions_finalized' => $sessions->count(),
            'fcs_pdf_url' => $pdfUrl,
            'fcs_pdf_filename' => $pdfFilename,
            // Enhanced metrics from TrueRevenueEngine
            'monthly_breakdown' => $monthlyBreakdown,
            'volatility' => $volatility,
            'mca_exposure' => $mcaPayments,
            'mca_capacity' => $capacity,
            'classification_engine' => 'TrueRevenueEngine v1.0',
            // Existing MCA daily payment for offer calculation
            'existing_daily_payment' => round($mcaPayments['total_daily_payment'] ?? 0, 2),
        ]);
    }

    /**
     * Download File Control Sheet PDF
     */
    public function downloadFcs(string $filename)
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);

        // Only allow fcs_pdf files
        if (! str_starts_with($filename, 'fcs_pdf_') || ! str_ends_with($filename, '.pdf')) {
            abort(404, 'Invalid file requested');
        }

        $path = 'fcs_reports/'.$filename;

        if (! \Storage::exists($path)) {
            abort(404, 'File not found');
        }

        return \Storage::download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Calculate summary statistics for transactions
     */
    private function calculateSummary(array $transactions): array
    {
        $totalCredits = 0;
        $totalDebits = 0;
        $creditCount = 0;
        $debitCount = 0;
        $highConfidence = 0;
        $mediumConfidence = 0;
        $lowConfidence = 0;

        foreach ($transactions as $txn) {
            if ($txn['type'] === 'credit') {
                $totalCredits += $txn['amount'];
                $creditCount++;
            } else {
                $totalDebits += $txn['amount'];
                $debitCount++;
            }

            // Count confidence levels
            $confidence = $txn['confidence'] ?? 0.8;
            if ($confidence >= 0.9) {
                $highConfidence++;
            } elseif ($confidence >= 0.7) {
                $mediumConfidence++;
            } else {
                $lowConfidence++;
            }
        }

        return [
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'credit_count' => $creditCount,
            'debit_count' => $debitCount,
            'net_flow' => $totalCredits - $totalDebits,
            'transaction_count' => count($transactions),
            'high_confidence' => $highConfidence,
            'medium_confidence' => $mediumConfidence,
            'low_confidence' => $lowConfidence,
        ];
    }

    /**
     * Display the MCA Pricing Calculator page
     */
    public function pricing()
    {
        return view('smartmca.pricing', [
            'trueRevenue' => null,
            'existingPayment' => 0,
        ]);
    }

    /**
     * Display the MCA Pricing Calculator with pre-filled data from a session
     */
    public function pricingWithSession(string $sessionId)
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('user_id', auth()->id())
            ->first();

        $trueRevenue = null;
        $existingPayment = 0;

        if ($session) {
            // Get True Revenue from session metadata or calculate from transactions
            $metadata = $session->metadata ?? [];
            $trueRevenue = $metadata['true_revenue'] ?? null;

            // Calculate existing MCA daily payment from detected positions
            $transactions = $session->transactions()->get();
            $mcaPayments = [];

            foreach ($transactions as $txn) {
                $desc = strtolower($txn->description ?? '');
                // Check if this is an MCA payment (debit)
                if ($txn->type === 'debit' && preg_match('/kapitus|merchant marketplace|yellowstone|credibly|fundbox|ondeck|bluevine|kabbage|can capital|forward|rapid|swift|clear|bizfi|lendio|fora financial|libertas|forward financing|national funding|merchants capital/i', $desc)) {
                    $mcaPayments[] = $txn->amount;
                }
            }

            // Estimate daily payment (average of MCA debits found)
            if (count($mcaPayments) > 0) {
                // Assume transactions span roughly 30 days, estimate daily
                $totalMcaPayments = array_sum($mcaPayments);
                $countPayments = count($mcaPayments);
                // Typical MCA payments are daily, so count is roughly business days
                $existingPayment = $countPayments > 0 ? round($totalMcaPayments / $countPayments, 2) : 0;
            }
        }

        return view('smartmca.pricing', [
            'trueRevenue' => $trueRevenue,
            'existingPayment' => $existingPayment,
            'sessionId' => $sessionId,
        ]);
    }

    /**
     * Calculate MCA Pricing (Web Route)
     * Proxies to DynamicMCACalculator for session-based auth
     */
    public function pricingCalculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_true_revenue' => 'required|numeric|min:1000',
            'existing_daily_payment' => 'nullable|numeric|min:0',
            'requested_amount' => 'required|numeric|min:1000',
            'position' => 'nullable|integer|min:1|max:4',
            'term_months' => 'nullable|integer|min:2|max:18',
            'factor_rate' => 'nullable|numeric|min:1.10|max:1.75',
            'industry' => 'nullable|string|max:50',
            'credit_score' => 'nullable|integer|min:300|max:850',
            'risk_score' => 'nullable|integer|min:0|max:100',
            'volatility_level' => 'nullable|string|in:low,medium,high',
        ]);

        $calculator = new DynamicMCACalculator();
        $result = $calculator->calculateOffer($validated);

        return response()->json([
            'success' => $result['can_fund'],
            'status' => $result['status'],
            'data' => $result,
        ]);
    }

    /**
     * Generate MCA Pricing Scenarios (Web Route)
     * Proxies to DynamicMCACalculator for session-based auth
     */
    public function pricingScenarios(Request $request): JsonResponse
    {
        $baseInput = $request->validate([
            'monthly_true_revenue' => 'required|numeric|min:1000',
            'existing_daily_payment' => 'nullable|numeric|min:0',
            'requested_amount' => 'required|numeric|min:1000',
            'position' => 'nullable|integer|min:1|max:4',
            'industry' => 'nullable|string|max:50',
            'credit_score' => 'nullable|integer|min:300|max:850',
            'risk_score' => 'nullable|integer|min:0|max:100',
        ]);

        $scenarios = [
            'conservative_short' => [
                'term_months' => 4,
                'factor_rate' => 1.45,
            ],
            'standard_medium' => [
                'term_months' => 6,
                'factor_rate' => 1.35,
            ],
            'aggressive_long' => [
                'term_months' => 9,
                'factor_rate' => 1.25,
            ],
        ];

        $calculator = new DynamicMCACalculator();
        $results = $calculator->calculateScenarios($baseInput, $scenarios);

        $comparison = [];
        foreach ($results as $name => $result) {
            if ($result['can_fund'] && $result['offer']) {
                $comparison[$name] = [
                    'can_fund' => true,
                    'funding_amount' => $result['offer']['funding_amount'],
                    'factor_rate' => $result['offer']['factor_rate'],
                    'term_months' => $result['offer']['term_months'],
                    'daily_payment' => $result['offer']['daily_payment'],
                    'payback_amount' => $result['offer']['payback_amount'],
                    'cost_of_capital' => $result['offer']['cost_of_capital'],
                    'withhold_percent' => $result['offer']['withhold_breakdown']['new_withhold_percent'],
                ];
            } else {
                $comparison[$name] = [
                    'can_fund' => false,
                    'reason' => $result['decline_reason'] ?? $result['explanation'] ?? 'Unable to calculate',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'scenarios' => $comparison,
            'detailed_results' => $results,
        ]);
    }

    /**
     * Accuracy Metrics Dashboard
     * Part of PR4: Production Hardening
     */
    public function accuracyDashboard()
    {
        $userId = auth()->id();

        // Get all sessions for this user
        $sessions = AnalysisSession::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate overall statistics
        $totalSessions = $sessions->count();
        $totalTransactions = AnalyzedTransaction::whereHas('session', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();

        $corrections = TransactionCorrection::where('user_id', $userId)->get();
        $totalCorrections = $corrections->count();

        // Calculate accuracy rate (transactions without corrections / total)
        $accuracyRate = $totalTransactions > 0
            ? round((($totalTransactions - $totalCorrections) / $totalTransactions) * 100, 1)
            : 100;

        // Get confidence distribution
        $highConfidence = AnalyzedTransaction::whereHas('session', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('confidence', 'high')->count();

        $mediumConfidence = AnalyzedTransaction::whereHas('session', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('confidence', 'medium')->count();

        $lowConfidence = AnalyzedTransaction::whereHas('session', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('confidence', 'low')->count();

        // Calculate average confidence score
        $avgConfidenceScore = 0;
        if ($totalTransactions > 0) {
            $avgConfidenceScore = round(
                (($highConfidence * 0.95) + ($mediumConfidence * 0.75) + ($lowConfidence * 0.5)) / $totalTransactions,
                2
            );
        }

        // Get correction breakdown by type
        $typeCorrections = $corrections->where('field', 'type')->count();
        $amountCorrections = $corrections->where('field', 'amount')->count();
        $dateCorrections = $corrections->where('field', 'date')->count();
        $descCorrections = $corrections->where('field', 'description')->count();

        // Get accuracy trend (last 30 days)
        $accuracyTrend = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayTransactions = AnalyzedTransaction::whereHas('session', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->whereDate('created_at', $date)->count();

            $dayCorrections = TransactionCorrection::where('user_id', $userId)
                ->whereDate('created_at', $date)
                ->count();

            $dayAccuracy = $dayTransactions > 0
                ? round((($dayTransactions - $dayCorrections) / $dayTransactions) * 100, 1)
                : null;

            $accuracyTrend[] = [
                'date' => now()->subDays($i)->format('M d'),
                'accuracy' => $dayAccuracy,
                'transactions' => $dayTransactions,
            ];
        }

        // Get bank performance (from session metadata)
        $bankPerformance = [];
        foreach ($sessions as $session) {
            $metadata = $session->metadata ?? [];
            $bankName = $metadata['bank_name'] ?? 'Unknown';

            if (!isset($bankPerformance[$bankName])) {
                $bankPerformance[$bankName] = [
                    'name' => $bankName,
                    'sessions' => 0,
                    'transactions' => 0,
                    'corrections' => 0,
                ];
            }

            $bankPerformance[$bankName]['sessions']++;
            $sessionTxns = $session->transactions()->count();
            $bankPerformance[$bankName]['transactions'] += $sessionTxns;

            // Count corrections for this session
            $sessionCorrections = TransactionCorrection::whereHas('transaction', function ($q) use ($session) {
                $q->where('session_id', $session->id);
            })->count();
            $bankPerformance[$bankName]['corrections'] += $sessionCorrections;
        }

        // Calculate accuracy per bank
        foreach ($bankPerformance as &$bank) {
            $bank['accuracy'] = $bank['transactions'] > 0
                ? round((($bank['transactions'] - $bank['corrections']) / $bank['transactions']) * 100, 1)
                : 100;
        }

        // Sort banks by session count
        usort($bankPerformance, fn($a, $b) => $b['sessions'] - $a['sessions']);

        // Get recent corrections for review
        $recentCorrections = TransactionCorrection::where('user_id', $userId)
            ->with('transaction')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('smartmca.dashboard', compact(
            'totalSessions',
            'totalTransactions',
            'totalCorrections',
            'accuracyRate',
            'highConfidence',
            'mediumConfidence',
            'lowConfidence',
            'avgConfidenceScore',
            'typeCorrections',
            'amountCorrections',
            'dateCorrections',
            'descCorrections',
            'accuracyTrend',
            'bankPerformance',
            'recentCorrections'
        ));
    }
}
