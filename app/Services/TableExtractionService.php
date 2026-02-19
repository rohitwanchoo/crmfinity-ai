<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Service for extracting tables from PDF bank statements using tabula-py
 * Part of PR3: Advanced Features - Table Extraction
 */
class TableExtractionService
{
    /**
     * Extract tables from a PDF file using tabula-py
     *
     * @param string $pdfPath Path to the PDF file
     * @param array $options Extraction options
     * @return array Extracted transactions
     */
    public function extractTables(string $pdfPath, array $options = []): array
    {
        if (!file_exists($pdfPath)) {
            Log::warning("TableExtraction: PDF file not found: {$pdfPath}");
            return [];
        }

        try {
            $pythonScript = $this->buildPythonScript($pdfPath, $options);
            $tempScript = storage_path('app/temp_table_extract_' . uniqid() . '.py');

            file_put_contents($tempScript, $pythonScript);

            $result = Process::timeout(120)->run("/var/www/html/crmfinity-ai/venv/bin/python3 {$tempScript}");

            // Clean up temp script
            @unlink($tempScript);

            if ($result->failed()) {
                Log::warning("TableExtraction: Script failed - " . $result->errorOutput());
                return [];
            }

            $output = trim($result->output());
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("TableExtraction: Invalid JSON output");
                return [];
            }

            $transactions = $data['transactions'] ?? [];
            Log::info("TableExtraction: Extracted " . count($transactions) . " transactions from tables");

            return $transactions;

        } catch (\Exception $e) {
            Log::error("TableExtraction: Error - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Build Python script for table extraction
     */
    private function buildPythonScript(string $pdfPath, array $options): string
    {
        $escapedPath = addslashes($pdfPath);
        $pages = $options['pages'] ?? 'all';

        return <<<PYTHON
#!/usr/bin/env python3
import json
import sys
import re
from datetime import datetime

try:
    import tabula
    import pandas as pd
except ImportError as e:
    print(json.dumps({"error": f"Missing dependency: {e}", "transactions": []}))
    sys.exit(0)

def parse_amount(amount_str):
    """Parse amount string to float"""
    if pd.isna(amount_str) or amount_str == '' or amount_str is None:
        return None
    amount_str = str(amount_str).strip()
    # Remove currency symbols and commas
    amount_str = re.sub(r'[\$,]', '', amount_str)
    # Handle parentheses as negative (but we want positive)
    amount_str = amount_str.replace('(', '').replace(')', '')
    # Handle negative signs
    amount_str = amount_str.replace('-', '')
    try:
        return abs(float(amount_str))
    except:
        return None

def parse_date(date_str, year=None):
    """Parse date string to YYYY-MM-DD format"""
    if pd.isna(date_str) or date_str == '' or date_str is None:
        return None
    date_str = str(date_str).strip()

    if not year:
        year = datetime.now().year

    # Try various date formats
    formats = [
        '%m/%d/%Y', '%m/%d/%y', '%m-%d-%Y', '%m-%d-%y',
        '%m/%d', '%m-%d', '%Y-%m-%d', '%d/%m/%Y'
    ]

    for fmt in formats:
        try:
            dt = datetime.strptime(date_str, fmt)
            if dt.year < 2000:
                dt = dt.replace(year=year)
            return dt.strftime('%Y-%m-%d')
        except:
            continue

    # Try to extract MM/DD pattern
    match = re.search(r'(\d{1,2})[/-](\d{1,2})', date_str)
    if match:
        month, day = int(match.group(1)), int(match.group(2))
        if 1 <= month <= 12 and 1 <= day <= 31:
            return f"{year}-{month:02d}-{day:02d}"

    return None

def classify_transaction(description, amount_col=None):
    """Classify transaction as credit or debit based on description and column"""
    if not description:
        return 'debit', 'medium'

    desc_upper = str(description).upper()

    # Strong credit indicators
    credit_keywords = [
        'DEPOSIT', 'CREDIT', 'REFUND', 'RETURN', 'REVERSAL',
        'INTEREST', 'DIVIDEND', 'FROM ', ' FROM', 'RECEIVED',
        'INCOMING', 'TRANSFER IN', 'WIRE IN', 'ACH CREDIT',
        'PAYROLL', 'DIRECT DEP', 'MOBILE DEPOSIT', 'CHECK DEPOSIT'
    ]

    # Strong debit indicators
    debit_keywords = [
        'WITHDRAWAL', 'DEBIT', 'PURCHASE', 'POS ', 'ATM ',
        'CHECK #', 'CHK ', 'FEE', 'CHARGE', 'PAYMENT',
        'TO ', ' TO', 'SENT', 'TRANSFER OUT', 'WIRE OUT',
        'ACH DEBIT', 'AUTOPAY', 'BILL PAY', 'LOAN'
    ]

    # Check column name hints
    if amount_col:
        col_upper = str(amount_col).upper()
        if any(kw in col_upper for kw in ['DEPOSIT', 'CREDIT', 'ADDITION']):
            return 'credit', 'high'
        if any(kw in col_upper for kw in ['WITHDRAW', 'DEBIT', 'SUBTRACTION', 'CHECK']):
            return 'debit', 'high'

    # Check description keywords
    for kw in credit_keywords:
        if kw in desc_upper:
            return 'credit', 'high'

    for kw in debit_keywords:
        if kw in desc_upper:
            return 'debit', 'high'

    return 'debit', 'medium'  # Default to debit

def is_balance_line(description):
    """Check if this is a balance line (not a transaction)"""
    if not description:
        return False
    desc_upper = str(description).upper()
    balance_keywords = [
        'BALANCE FORWARD', 'BEGINNING BALANCE', 'ENDING BALANCE',
        'OPENING BALANCE', 'CLOSING BALANCE', 'PREVIOUS BALANCE',
        'NEW BALANCE', 'TOTAL DEPOSIT', 'TOTAL WITHDRAWAL',
        'DAILY BALANCE', 'AVAILABLE BALANCE'
    ]
    return any(kw in desc_upper for kw in balance_keywords)

def extract_transactions_from_tables(pdf_path, pages='all'):
    """Extract transactions from PDF tables"""
    transactions = []

    try:
        # Extract all tables from PDF
        tables = tabula.read_pdf(
            pdf_path,
            pages=pages,
            multiple_tables=True,
            guess=True,
            pandas_options={'header': None}
        )

        if not tables:
            return []

        for table_idx, df in enumerate(tables):
            if df.empty or len(df.columns) < 2:
                continue

            # Try to identify column structure
            # Common patterns: Date | Description | Amount | Balance
            #                  Date | Description | Debit | Credit | Balance

            num_cols = len(df.columns)

            for row_idx, row in df.iterrows():
                row_values = [str(v).strip() if pd.notna(v) else '' for v in row]

                # Skip empty rows
                if all(not v for v in row_values):
                    continue

                # Skip header-like rows
                if any(kw in ' '.join(row_values).upper() for kw in ['DATE', 'DESCRIPTION', 'AMOUNT', 'BALANCE', 'TRANSACTION']):
                    continue

                # Try to find date, description, and amount
                date = None
                description = None
                amount = None
                amount_col_name = None

                for col_idx, value in enumerate(row_values):
                    # Try to parse as date
                    if not date:
                        parsed_date = parse_date(value)
                        if parsed_date:
                            date = parsed_date
                            continue

                    # Try to parse as amount
                    parsed_amount = parse_amount(value)
                    if parsed_amount and parsed_amount > 0:
                        if not amount:  # Take first non-zero amount
                            amount = parsed_amount
                            # Try to get column name for classification
                            if col_idx < len(df.columns):
                                amount_col_name = str(df.columns[col_idx])
                        continue

                    # Otherwise treat as description
                    if value and len(value) > 2 and not description:
                        description = value

                # Skip if missing essential fields
                if not date or not amount:
                    continue

                # Skip balance lines
                if description and is_balance_line(description):
                    continue

                # Classify transaction
                txn_type, confidence = classify_transaction(description, amount_col_name)

                transactions.append({
                    'date': date,
                    'description': description or 'Unknown',
                    'amount': round(amount, 2),
                    'type': txn_type,
                    'confidence': confidence,
                    'source': 'table_extraction'
                })

        return transactions

    except Exception as e:
        return []

# Main execution
pdf_path = "{$escapedPath}"
pages = "{$pages}"

transactions = extract_transactions_from_tables(pdf_path, pages)

# Output as JSON
print(json.dumps({
    'success': True,
    'transactions': transactions,
    'count': len(transactions)
}))
PYTHON;
    }

    /**
     * Merge table-extracted transactions with AI-extracted transactions
     * Uses voting/confidence to resolve conflicts
     *
     * @param array $aiTransactions Transactions from AI extraction
     * @param array $tableTransactions Transactions from table extraction
     * @return array Merged transactions with confidence scores
     */
    public function mergeTransactions(array $aiTransactions, array $tableTransactions): array
    {
        if (empty($tableTransactions)) {
            return $aiTransactions;
        }

        if (empty($aiTransactions)) {
            return $tableTransactions;
        }

        $merged = [];
        $matchedTableIndices = [];

        foreach ($aiTransactions as $aiTxn) {
            $bestMatch = null;
            $bestMatchIdx = null;
            $bestScore = 0;

            // Find matching table transaction
            foreach ($tableTransactions as $idx => $tableTxn) {
                if (isset($matchedTableIndices[$idx])) {
                    continue;
                }

                $score = $this->calculateMatchScore($aiTxn, $tableTxn);
                if ($score > $bestScore && $score >= 0.7) {
                    $bestScore = $score;
                    $bestMatch = $tableTxn;
                    $bestMatchIdx = $idx;
                }
            }

            if ($bestMatch) {
                // Merge the two sources
                $matchedTableIndices[$bestMatchIdx] = true;
                $merged[] = $this->mergeSingleTransaction($aiTxn, $bestMatch);
            } else {
                // No table match, keep AI version
                $merged[] = $aiTxn;
            }
        }

        // Add any unmatched table transactions
        foreach ($tableTransactions as $idx => $tableTxn) {
            if (!isset($matchedTableIndices[$idx])) {
                $tableTxn['confidence'] = $tableTxn['confidence'] ?? 'medium';
                $tableTxn['source'] = 'table_only';
                $merged[] = $tableTxn;
            }
        }

        Log::info("TableExtraction: Merged " . count($merged) . " total transactions (AI: " . count($aiTransactions) . ", Table: " . count($tableTransactions) . ")");

        return $merged;
    }

    /**
     * Calculate match score between two transactions
     */
    private function calculateMatchScore(array $txn1, array $txn2): float
    {
        $score = 0.0;

        // Date match (40% weight)
        if (isset($txn1['date'], $txn2['date']) && $txn1['date'] === $txn2['date']) {
            $score += 0.4;
        }

        // Amount match (40% weight)
        if (isset($txn1['amount'], $txn2['amount'])) {
            $diff = abs($txn1['amount'] - $txn2['amount']);
            if ($diff < 0.01) {
                $score += 0.4;
            } elseif ($diff < 1.00) {
                $score += 0.2;
            }
        }

        // Description similarity (20% weight)
        if (isset($txn1['description'], $txn2['description'])) {
            $desc1 = strtolower(preg_replace('/[^a-z0-9]/', '', $txn1['description']));
            $desc2 = strtolower(preg_replace('/[^a-z0-9]/', '', $txn2['description']));
            similar_text($desc1, $desc2, $percent);
            $score += ($percent / 100) * 0.2;
        }

        return $score;
    }

    /**
     * Merge a single transaction from two sources
     */
    private function mergeSingleTransaction(array $aiTxn, array $tableTxn): array
    {
        // If types agree, high confidence
        if ($aiTxn['type'] === $tableTxn['type']) {
            return [
                'date' => $aiTxn['date'],
                'description' => $aiTxn['description'],
                'amount' => $aiTxn['amount'],
                'type' => $aiTxn['type'],
                'confidence' => 'high',
                'source' => 'ai_table_match',
                'verified' => true,
            ];
        }

        // Types disagree - use AI confidence to decide
        $aiConfidence = $aiTxn['confidence'] ?? 'medium';
        $tableConfidence = $tableTxn['confidence'] ?? 'medium';

        $confidenceRank = ['high' => 3, 'medium' => 2, 'low' => 1];

        if (($confidenceRank[$aiConfidence] ?? 2) >= ($confidenceRank[$tableConfidence] ?? 2)) {
            return array_merge($aiTxn, [
                'confidence' => 'medium',
                'source' => 'ai_preferred',
                'table_type' => $tableTxn['type'],
            ]);
        }

        return array_merge($tableTxn, [
            'confidence' => 'medium',
            'source' => 'table_preferred',
            'ai_type' => $aiTxn['type'],
        ]);
    }
}
