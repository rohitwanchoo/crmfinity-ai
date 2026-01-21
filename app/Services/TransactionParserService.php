<?php

namespace App\Services;

use Carbon\Carbon;

class TransactionParserService
{
    protected array $bankPatterns = [];

    protected array $transactionPatterns = [];

    public function __construct()
    {
        $this->loadBankPatterns();
        $this->loadTransactionPatterns();
    }

    /**
     * Parse transactions from extracted PDF text
     */
    public function parseTransactions(string $text, ?string $bankName = null): array
    {
        $detectedBank = $bankName ?? $this->detectBank($text);
        $lines = $this->preprocessText($text);
        $transactions = [];
        $metadata = $this->extractMetadata($text, $detectedBank);

        foreach ($lines as $lineNumber => $line) {
            $transaction = $this->parseLine($line, $detectedBank);
            if ($transaction) {
                $transaction['line_number'] = $lineNumber + 1;
                $transaction['raw_text'] = $line;
                $transactions[] = $transaction;
            }
        }

        // Sort by date
        usort($transactions, function ($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        return [
            'success' => true,
            'bank' => $detectedBank,
            'metadata' => $metadata,
            'transactions' => $transactions,
            'summary' => $this->calculateSummary($transactions),
        ];
    }

    /**
     * Detect bank from statement text
     */
    public function detectBank(string $text): string
    {
        $textLower = strtolower($text);

        foreach ($this->bankPatterns as $bank => $patterns) {
            foreach ($patterns['identifiers'] as $identifier) {
                if (str_contains($textLower, strtolower($identifier))) {
                    return $bank;
                }
            }
        }

        return 'generic';
    }

    /**
     * Preprocess text for parsing
     */
    protected function preprocessText(string $text): array
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Split into lines
        $lines = explode("\n", $text);

        // Clean and filter lines
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function ($line) {
            return strlen($line) > 10; // Filter out very short lines
        });

        return array_values($lines);
    }

    /**
     * Parse a single line for transaction data
     */
    protected function parseLine(string $line, string $bank): ?array
    {
        // Try bank-specific patterns first
        if (isset($this->bankPatterns[$bank]['transaction_pattern'])) {
            $result = $this->parseWithPattern($line, $this->bankPatterns[$bank]['transaction_pattern']);
            if ($result) {
                return $result;
            }
        }

        // Fall back to generic patterns
        return $this->parseGenericTransaction($line);
    }

    /**
     * Parse line with specific regex pattern
     */
    protected function parseWithPattern(string $line, string $pattern): ?array
    {
        if (preg_match($pattern, $line, $matches)) {
            return $this->normalizeTransaction($matches);
        }

        return null;
    }

    /**
     * Parse transaction using generic patterns
     */
    protected function parseGenericTransaction(string $line): ?array
    {
        foreach ($this->transactionPatterns as $pattern) {
            if (preg_match($pattern['regex'], $line, $matches)) {
                $transaction = $this->normalizeTransaction($matches);
                if ($transaction) {
                    return $transaction;
                }
            }
        }

        return null;
    }

    /**
     * Normalize transaction data from regex matches
     */
    protected function normalizeTransaction(array $matches): ?array
    {
        $date = $matches['date'] ?? $matches[1] ?? null;
        $description = $matches['description'] ?? $matches[2] ?? null;
        $amount = $matches['amount'] ?? $matches[3] ?? null;

        if (! $date || ! $amount) {
            return null;
        }

        // Parse and validate date
        $parsedDate = $this->parseDate($date);
        if (! $parsedDate) {
            return null;
        }

        // Parse amount and determine type
        $amountInfo = $this->parseAmount($amount);
        if (! $amountInfo) {
            return null;
        }

        // Classify transaction
        $classification = $this->classifyTransaction($description ?? '', $amountInfo['type']);

        return [
            'date' => $parsedDate,
            'description' => trim($description ?? ''),
            'amount' => $amountInfo['value'],
            'type' => $amountInfo['type'],
            'category' => $classification['category'],
            'is_revenue' => $classification['is_revenue'],
            'is_mca_related' => $classification['is_mca_related'],
            'is_transfer' => $classification['is_transfer'],
            'confidence' => $classification['confidence'],
        ];
    }

    /**
     * Parse various date formats
     */
    protected function parseDate(string $date): ?string
    {
        $formats = [
            'm/d/Y', 'm/d/y', 'n/j/Y', 'n/j/y',
            'm-d-Y', 'm-d-y',
            'Y-m-d',
            'M d, Y', 'M j, Y',
            'd M Y', 'j M Y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, trim($date));
                if ($parsed) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Parse amount string to float
     */
    protected function parseAmount(string $amount): ?array
    {
        // Remove currency symbols and whitespace
        $cleaned = preg_replace('/[^\d.,\-\(\)]/', '', $amount);

        // Determine if negative (debit)
        $isNegative = str_contains($amount, '-') ||
                      str_contains($amount, '(') ||
                      preg_match('/\bDR\b|\bDEBIT\b/i', $amount);

        // Remove parentheses and negative signs
        $cleaned = str_replace(['(', ')', '-'], '', $cleaned);

        // Handle comma as thousands separator
        if (preg_match('/^\d{1,3}(,\d{3})*(\.\d{2})?$/', $cleaned)) {
            $cleaned = str_replace(',', '', $cleaned);
        }
        // Handle comma as decimal separator (European)
        elseif (preg_match('/^\d+,\d{2}$/', $cleaned)) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        $value = floatval($cleaned);
        if ($value <= 0) {
            return null;
        }

        return [
            'value' => $value,
            'type' => $isNegative ? 'debit' : 'credit',
        ];
    }

    /**
     * Classify transaction based on description
     */
    protected function classifyTransaction(string $description, string $type): array
    {
        $descLower = strtolower($description);

        $classification = [
            'category' => 'other',
            'is_revenue' => false,
            'is_mca_related' => false,
            'is_transfer' => false,
            'confidence' => 0.5,
        ];

        // Check for transfers
        $transferPatterns = [
            'transfer', 'xfer', 'zelle', 'venmo', 'paypal', 'cashapp',
            'wire', 'ach', 'internal', 'between accounts',
        ];
        foreach ($transferPatterns as $pattern) {
            if (str_contains($descLower, $pattern)) {
                $classification['category'] = 'transfer';
                $classification['is_transfer'] = true;
                $classification['confidence'] = 0.9;

                return $classification;
            }
        }

        // Check for MCA-related transactions
        $mcaPatterns = [
            'mca', 'merchant cash', 'business funding', 'working capital',
            'advance', 'funder', 'capital', 'ondeck', 'kabbage', 'can capital',
            'rapid', 'bizfi', 'credibly', 'national funding', 'fundbox',
            'bluevine', 'lendio', 'fundera', 'square capital', 'paypal working',
        ];
        foreach ($mcaPatterns as $pattern) {
            if (str_contains($descLower, $pattern)) {
                $classification['category'] = 'mca';
                $classification['is_mca_related'] = true;
                $classification['confidence'] = 0.85;

                return $classification;
            }
        }

        // Check for loan payments
        $loanPatterns = [
            'loan', 'sba', 'credit line', 'loc payment', 'term loan',
            'financing', 'equipment lease',
        ];
        foreach ($loanPatterns as $pattern) {
            if (str_contains($descLower, $pattern)) {
                $classification['category'] = 'loan';
                $classification['confidence'] = 0.8;

                return $classification;
            }
        }

        // Check for payroll
        $payrollPatterns = [
            'payroll', 'adp', 'paychex', 'gusto', 'salary', 'wages',
            'quickbooks payroll', 'employee',
        ];
        foreach ($payrollPatterns as $pattern) {
            if (str_contains($descLower, $pattern)) {
                $classification['category'] = 'payroll';
                $classification['confidence'] = 0.9;

                return $classification;
            }
        }

        // Check for credit card processing (revenue indicator)
        $processingPatterns = [
            'square', 'stripe', 'shopify', 'merchant serv', 'credit card',
            'pos', 'clover', 'toast', 'lightspeed', 'authorize.net',
            'braintree', 'heartland', 'first data', 'worldpay',
        ];
        foreach ($processingPatterns as $pattern) {
            if (str_contains($descLower, $pattern) && $type === 'credit') {
                $classification['category'] = 'card_processing';
                $classification['is_revenue'] = true;
                $classification['confidence'] = 0.9;

                return $classification;
            }
        }

        // Check for deposits (potential revenue)
        $depositPatterns = ['deposit', 'customer', 'payment received', 'invoice'];
        foreach ($depositPatterns as $pattern) {
            if (str_contains($descLower, $pattern) && $type === 'credit') {
                $classification['category'] = 'deposit';
                $classification['is_revenue'] = true;
                $classification['confidence'] = 0.7;

                return $classification;
            }
        }

        // Check for NSF/Overdraft
        $nsfPatterns = ['nsf', 'overdraft', 'insufficient', 'returned item', 'od fee'];
        foreach ($nsfPatterns as $pattern) {
            if (str_contains($descLower, $pattern)) {
                $classification['category'] = 'nsf';
                $classification['confidence'] = 0.95;

                return $classification;
            }
        }

        // Check for fees
        $feePatterns = ['fee', 'charge', 'service charge', 'maintenance'];
        foreach ($feePatterns as $pattern) {
            if (str_contains($descLower, $pattern)) {
                $classification['category'] = 'fee';
                $classification['confidence'] = 0.8;

                return $classification;
            }
        }

        // Default: credits are potential revenue
        if ($type === 'credit') {
            $classification['is_revenue'] = true;
            $classification['confidence'] = 0.5;
        }

        return $classification;
    }

    /**
     * Extract metadata from statement
     */
    protected function extractMetadata(string $text, string $bank): array
    {
        $metadata = [
            'bank' => $bank,
            'account_number' => null,
            'statement_period' => null,
            'beginning_balance' => null,
            'ending_balance' => null,
        ];

        // Try to extract account number (masked)
        if (preg_match('/account[:\s#]*[\*x]*(\d{4})/i', $text, $matches)) {
            $metadata['account_number'] = '****'.$matches[1];
        }

        // Try to extract statement period
        $periodPatterns = [
            '/statement\s+period[:\s]*([\d\/\-]+)\s*(?:to|through|-)\s*([\d\/\-]+)/i',
            '/from\s*([\d\/\-]+)\s*(?:to|through|-)\s*([\d\/\-]+)/i',
        ];
        foreach ($periodPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $metadata['statement_period'] = [
                    'start' => $this->parseDate($matches[1]),
                    'end' => $this->parseDate($matches[2]),
                ];
                break;
            }
        }

        // Try to extract balances
        if (preg_match('/beginning\s+balance[:\s\$]*([\d,\.]+)/i', $text, $matches)) {
            $metadata['beginning_balance'] = floatval(str_replace(',', '', $matches[1]));
        }
        if (preg_match('/ending\s+balance[:\s\$]*([\d,\.]+)/i', $text, $matches)) {
            $metadata['ending_balance'] = floatval(str_replace(',', '', $matches[1]));
        }

        return $metadata;
    }

    /**
     * Calculate transaction summary
     */
    protected function calculateSummary(array $transactions): array
    {
        $summary = [
            'total_transactions' => count($transactions),
            'total_credits' => 0,
            'total_debits' => 0,
            'credit_count' => 0,
            'debit_count' => 0,
            'revenue_total' => 0,
            'mca_payments' => 0,
            'transfer_total' => 0,
            'nsf_count' => 0,
            'average_credit' => 0,
            'average_debit' => 0,
            'categories' => [],
        ];

        foreach ($transactions as $txn) {
            if ($txn['type'] === 'credit') {
                $summary['total_credits'] += $txn['amount'];
                $summary['credit_count']++;
            } else {
                $summary['total_debits'] += $txn['amount'];
                $summary['debit_count']++;
            }

            if ($txn['is_revenue']) {
                $summary['revenue_total'] += $txn['amount'];
            }

            if ($txn['is_mca_related'] && $txn['type'] === 'debit') {
                $summary['mca_payments'] += $txn['amount'];
            }

            if ($txn['is_transfer']) {
                $summary['transfer_total'] += $txn['amount'];
            }

            if ($txn['category'] === 'nsf') {
                $summary['nsf_count']++;
            }

            // Count by category
            $cat = $txn['category'];
            if (! isset($summary['categories'][$cat])) {
                $summary['categories'][$cat] = ['count' => 0, 'total' => 0];
            }
            $summary['categories'][$cat]['count']++;
            $summary['categories'][$cat]['total'] += $txn['amount'];
        }

        if ($summary['credit_count'] > 0) {
            $summary['average_credit'] = round($summary['total_credits'] / $summary['credit_count'], 2);
        }
        if ($summary['debit_count'] > 0) {
            $summary['average_debit'] = round($summary['total_debits'] / $summary['debit_count'], 2);
        }

        $summary['total_credits'] = round($summary['total_credits'], 2);
        $summary['total_debits'] = round($summary['total_debits'], 2);
        $summary['revenue_total'] = round($summary['revenue_total'], 2);
        $summary['net_cash_flow'] = round($summary['total_credits'] - $summary['total_debits'], 2);

        return $summary;
    }

    /**
     * Load bank-specific parsing patterns
     */
    protected function loadBankPatterns(): void
    {
        $this->bankPatterns = [
            'chase' => [
                'identifiers' => ['chase bank', 'jpmorgan chase', 'chase.com'],
                'transaction_pattern' => '/^(?<date>\d{2}\/\d{2})\s+(?<description>.+?)\s+(?<amount>-?[\d,]+\.\d{2})$/',
            ],
            'bank_of_america' => [
                'identifiers' => ['bank of america', 'bofa', 'bankofamerica.com'],
                'transaction_pattern' => '/^(?<date>\d{2}\/\d{2}\/\d{2,4})\s+(?<description>.+?)\s+(?<amount>-?[\d,]+\.\d{2})$/',
            ],
            'wells_fargo' => [
                'identifiers' => ['wells fargo', 'wellsfargo.com'],
                'transaction_pattern' => '/^(?<date>\d{1,2}\/\d{1,2})\s+(?<description>.+?)\s+(?<amount>-?[\d,]+\.\d{2})$/',
            ],
            'td_bank' => [
                'identifiers' => ['td bank', 'tdbank.com'],
                'transaction_pattern' => '/^(?<date>\d{2}\/\d{2}\/\d{4})\s+(?<description>.+?)\s+(?<amount>[\d,]+\.\d{2})(?:\s+(?<balance>[\d,]+\.\d{2}))?$/',
            ],
            'pnc' => [
                'identifiers' => ['pnc bank', 'pnc.com'],
                'transaction_pattern' => '/^(?<date>\d{2}\/\d{2}\/\d{4})\s+(?<description>.+?)\s+(?<amount>[\$\-\d,]+\.\d{2})$/',
            ],
            'us_bank' => [
                'identifiers' => ['u.s. bank', 'usbank.com', 'us bank'],
                'transaction_pattern' => '/^(?<date>\d{2}\/\d{2})\s+(?<description>.+?)\s+(?<amount>-?[\d,]+\.\d{2})$/',
            ],
            'capital_one' => [
                'identifiers' => ['capital one', 'capitalone.com'],
                'transaction_pattern' => '/^(?<date>\w+\s+\d{1,2})\s+(?<description>.+?)\s+(?<amount>[\-\$\d,]+\.\d{2})$/',
            ],
            'citibank' => [
                'identifiers' => ['citibank', 'citi.com', 'citigroup'],
                'transaction_pattern' => '/^(?<date>\d{2}\/\d{2})\s+(?<description>.+?)\s+(?<amount>[\d,]+\.\d{2})\s*(?<type>CR|DR)?$/',
            ],
            'generic' => [
                'identifiers' => [],
                'transaction_pattern' => null,
            ],
        ];
    }

    /**
     * Load generic transaction patterns
     */
    protected function loadTransactionPatterns(): void
    {
        $this->transactionPatterns = [
            // MM/DD/YYYY Description Amount
            [
                'name' => 'date_desc_amount',
                'regex' => '/^(?<date>\d{1,2}\/\d{1,2}(?:\/\d{2,4})?)\s+(?<description>.{10,60}?)\s+(?<amount>-?\$?[\d,]+\.\d{2})$/',
            ],
            // Date Description Debit/Credit Amount
            [
                'name' => 'date_desc_type_amount',
                'regex' => '/^(?<date>\d{1,2}\/\d{1,2}(?:\/\d{2,4})?)\s+(?<description>.+?)\s+(?<amount>[\d,]+\.\d{2})\s*(?<type>CR|DR|CREDIT|DEBIT)?$/i',
            ],
            // Description Amount Date
            [
                'name' => 'desc_amount_date',
                'regex' => '/^(?<description>.{10,50}?)\s+(?<amount>-?\$?[\d,]+\.\d{2})\s+(?<date>\d{1,2}\/\d{1,2}(?:\/\d{2,4})?)$/',
            ],
            // Amount Description Date
            [
                'name' => 'amount_desc_date',
                'regex' => '/^(?<amount>-?\$?[\d,]+\.\d{2})\s+(?<description>.{10,50}?)\s+(?<date>\d{1,2}\/\d{1,2}(?:\/\d{2,4})?)$/',
            ],
        ];
    }

    /**
     * Get monthly breakdown of transactions
     */
    public function getMonthlyBreakdown(array $transactions): array
    {
        $monthly = [];

        foreach ($transactions as $txn) {
            $month = Carbon::parse($txn['date'])->format('Y-m');

            if (! isset($monthly[$month])) {
                $monthly[$month] = [
                    'month' => $month,
                    'credits' => 0,
                    'debits' => 0,
                    'revenue' => 0,
                    'mca_payments' => 0,
                    'transaction_count' => 0,
                    'nsf_count' => 0,
                ];
            }

            $monthly[$month]['transaction_count']++;

            if ($txn['type'] === 'credit') {
                $monthly[$month]['credits'] += $txn['amount'];
            } else {
                $monthly[$month]['debits'] += $txn['amount'];
            }

            if ($txn['is_revenue']) {
                $monthly[$month]['revenue'] += $txn['amount'];
            }

            if ($txn['is_mca_related'] && $txn['type'] === 'debit') {
                $monthly[$month]['mca_payments'] += $txn['amount'];
            }

            if ($txn['category'] === 'nsf') {
                $monthly[$month]['nsf_count']++;
            }
        }

        // Sort by month
        ksort($monthly);

        return array_values($monthly);
    }
}
