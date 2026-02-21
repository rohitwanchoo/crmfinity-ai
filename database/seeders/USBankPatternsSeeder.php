<?php

namespace Database\Seeders;

use App\Models\BankLayoutPattern;
use Illuminate\Database\Seeder;

class USBankPatternsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates patterns for all major US banks
     */
    public function run(): void
    {
        $banks = $this->getUSBankPatterns();

        foreach ($banks as $bankData) {
            BankLayoutPattern::updateOrCreate(
                [
                    'bank_name' => $bankData['bank_name'],
                    'layout_version' => $bankData['layout_version'] ?? 'default',
                ],
                $bankData
            );
        }

        $this->command->info('Seeded '.count($banks).' US bank patterns.');
    }

    /**
     * Get all US bank pattern definitions
     */
    private function getUSBankPatterns(): array
    {
        return [
            // ========== TOP 10 LARGEST US BANKS ==========

            // 1. JPMorgan Chase
            [
                'bank_name' => 'Chase',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'JPMorgan Chase Bank',
                    'CHASE',
                    'chase.com',
                    'Chase Bank',
                    'J.P. Morgan',
                    'Chase Business',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'JPMorgan Chase Bank, N.A.',
                ],
                'transaction_markers' => [
                    'CHASE CREDIT CRD',
                    'CHASE DEBIT CRD',
                    'CHASE TRANSFER',
                    'CHASE QUICKPAY',
                    'CHASE ATM',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['Purchase', 'Payment', 'ATM Withdrawal', 'Wire Transfer', 'Chase QuickPay Sent'],
                    'credit_indicators' => ['Deposit', 'Direct Dep', 'Chase QuickPay Received', 'Interest Payment', 'Refund'],
                    'check_format' => 'Check # followed by amount',
                    'notes' => 'Chase uses negative amounts for debits, positive for credits',
                ],
            ],

            // 2. Bank of America
            [
                'bank_name' => 'Bank of America',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YY',
                'header_patterns' => [
                    'Bank of America',
                    'BANK OF AMERICA',
                    'BofA',
                    'bankofamerica.com',
                    'Bank of America, N.A.',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Bank of America Corporation',
                ],
                'transaction_markers' => [
                    'CHECKCARD',
                    'POS PURCHASE',
                    'ACH DEBIT',
                    'ACH CREDIT',
                    'ONLINE TRANSFER',
                    'KEEP THE CHANGE',
                    'MOBILE DEPOSIT',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'withdrawals' => 'right',
                    'deposits' => 'far_right',
                    'balance' => 'separate_column',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['CHECKCARD', 'POS PURCHASE', 'ACH DEBIT', 'ATM WITHDRAWAL', 'ONLINE TRANSFER TO', 'CHECK'],
                    'credit_indicators' => ['ACH CREDIT', 'DIRECT DEPOSIT', 'ONLINE TRANSFER FROM', 'MOBILE DEPOSIT', 'INTEREST EARNED'],
                    'check_format' => 'CHECK followed by check number',
                    'notes' => 'BofA uses separate columns for withdrawals and deposits',
                ],
            ],

            // 3. Wells Fargo
            [
                'bank_name' => 'Wells Fargo',
                'layout_version' => 'default',
                'date_format' => 'MM/DD',
                'header_patterns' => [
                    'Wells Fargo',
                    'WELLS FARGO',
                    'wellsfargo.com',
                    'Wells Fargo Bank',
                    'Wells Fargo Bank, N.A.',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Wells Fargo & Company',
                ],
                'transaction_markers' => [
                    'PURCHASE AUTHORIZED ON',
                    'RECURRING TRANSFER',
                    'ONLINE TRANSFER',
                    'BILL PAY',
                    'ZELLE PAYMENT',
                    'DIRECT DEPOSIT',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'check_number' => 'optional',
                    'description' => 'center',
                    'additions' => 'right',
                    'subtractions' => 'far_right',
                    'daily_balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['PURCHASE AUTHORIZED ON', 'BILL PAY', 'ATM WITHDRAWAL', 'ONLINE TRANSFER TO', 'ZELLE PAYMENT TO', 'FEE'],
                    'credit_indicators' => ['DIRECT DEPOSIT', 'ZELLE PAYMENT FROM', 'ONLINE TRANSFER FROM', 'INTEREST PAYMENT', 'DEPOSIT'],
                    'check_format' => 'Check number in separate column',
                    'notes' => 'Wells Fargo uses Additions and Subtractions columns',
                ],
            ],

            // 4. Citibank
            [
                'bank_name' => 'Citibank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Citibank',
                    'CITIBANK',
                    'Citi',
                    'citibank.com',
                    'Citibank, N.A.',
                    'Citigroup',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Citibank, N.A.',
                ],
                'transaction_markers' => [
                    'CITI MOBILE',
                    'CITIBANK ATM',
                    'CITIDIRECT',
                    'CITI CHECKING',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'credits' => 'right',
                    'debits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['PURCHASE', 'WITHDRAWAL', 'PAYMENT', 'TRANSFER OUT', 'FEE', 'CHECK'],
                    'credit_indicators' => ['DEPOSIT', 'TRANSFER IN', 'INTEREST', 'REFUND', 'CREDIT'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Citi uses separate Credits and Debits columns',
                ],
            ],

            // 5. US Bank
            [
                'bank_name' => 'US Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YY',
                'header_patterns' => [
                    'U.S. Bank',
                    'US BANK',
                    'U.S. BANK',
                    'usbank.com',
                    'U.S. Bancorp',
                    'USB',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'U.S. Bank National Association',
                ],
                'transaction_markers' => [
                    'VISA DEB',
                    'US BANK',
                    'USB VISA',
                    'MOBILE DEPOSIT',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['VISA DEB', 'CHECK', 'WITHDRAWAL', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'CREDIT', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'US Bank uses VISA DEB prefix for debit card purchases',
                ],
            ],

            // 6. PNC Bank
            [
                'bank_name' => 'PNC Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'PNC Bank',
                    'PNC BANK',
                    'pnc.com',
                    'The PNC Financial Services Group',
                    'PNC',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'PNC Bank, National Association',
                ],
                'transaction_markers' => [
                    'PNC MOBILE',
                    'PNC ATM',
                    'VIRTUAL WALLET',
                    'PNC TRANSFER',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'withdrawals' => 'right',
                    'deposits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'CHECK', 'PAYMENT', 'TRANSFER OUT', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER IN', 'INTEREST', 'REFUND'],
                    'check_format' => 'Check number column',
                    'notes' => 'PNC uses Withdrawals and Deposits columns',
                ],
            ],

            // 7. Truist (BB&T + SunTrust)
            [
                'bank_name' => 'Truist',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Truist',
                    'TRUIST',
                    'truist.com',
                    'Truist Bank',
                    'Truist Financial',
                    'BB&T',
                    'SunTrust',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Truist Bank',
                ],
                'transaction_markers' => [
                    'TRUIST MOBILE',
                    'TRUIST ATM',
                    'TRUIST TRANSFER',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'Truist merged from BB&T and SunTrust - may see old branding',
                ],
            ],

            // 8. Capital One
            [
                'bank_name' => 'Capital One',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Capital One',
                    'CAPITAL ONE',
                    'capitalone.com',
                    'Capital One Bank',
                    'Capital One, N.A.',
                    '360 Checking',
                    '360 Savings',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Capital One, N.A.',
                ],
                'transaction_markers' => [
                    'CAPITAL ONE',
                    'CAP ONE',
                    '360 TRANSFER',
                    'CAPITAL ONE MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['PURCHASE', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE', 'DEBIT'],
                    'credit_indicators' => ['DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'CREDIT', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'Capital One 360 accounts may have different format',
                ],
            ],

            // 9. TD Bank
            [
                'bank_name' => 'TD Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'TD Bank',
                    'TD BANK',
                    'tdbank.com',
                    'TD Bank, N.A.',
                    'TD Bank America',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'TD Bank, N.A.',
                ],
                'transaction_markers' => [
                    'TD BANK',
                    'TD ATM',
                    'TD MOBILE',
                    'TD CONVENIENCE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'withdrawals' => 'right',
                    'deposits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'Check number in description',
                    'notes' => 'TD Bank uses Withdrawals and Deposits columns',
                ],
            ],

            // 9b. Firstrust Bank
            [
                'bank_name' => 'Firstrust Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YY',
                'header_patterns' => [
                    'Firstrust Bank',
                    'FIRSTRUST BANK',
                    'firstrust.com',
                    'Firstrust Savings Bank',
                    'Firstrust',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Firstrust Bank',
                ],
                'transaction_markers' => [
                    'PMT Debit',
                    'PMT To 3rdPrty',
                    'ATM Deposit',
                    'BUSINESS MOBILE DEPOSIT',
                    'Check Number',
                    'Seq#',
                    'PAYMENT TO LOAN',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'withdrawals' => 'right',
                    'deposits' => 'far_right',
                    'balance' => 'separate_column',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['PMT Debit', 'PMT To 3rdPrty', 'PAYMENT TO LOAN', 'Check Number', 'WITHDRAWAL', 'FEE'],
                    'credit_indicators' => ['ATM Deposit', 'BUSINESS MOBILE DEPOSIT', 'FDMS-SETTLEMENT DEPOSIT', 'DEPOSIT', 'CREDIT', 'INTEREST'],
                    'check_format' => 'Check Number followed by check number',
                    'notes' => 'Firstrust uses PMT Debit/PMT To 3rdPrty for debits with Seq# and Date/Time suffix. ATM Deposit and BUSINESS MOBILE DEPOSIT for credits. Philadelphia-based bank.',
                ],
            ],

            // 9c. Citizens Bank
            [
                'bank_name' => 'Citizens Bank, N.A.',
                'layout_version' => 'default',
                'date_format' => 'MM/DD',
                'header_patterns' => [
                    'citizensbank.com',
                    'Citizens Bank, N.A.',
                    'Citizens Bank',
                    'CITIZENS BANK',
                    'Analysis Business Checking',
                ],
                'footer_patterns' => [
                    'Citizens Bank, N.A.',
                    'Thank you for banking with Citizens',
                ],
                'transaction_markers' => [
                    'Debits**',
                    'Deposits&Credits',
                    'ATM/Purchases',
                    'OtherDebits',
                    'Check#',
                    'DailyBalance',
                ],
                'column_structure' => [
                    'date'               => 'left',
                    'description'        => 'center',
                    'amount'             => 'right',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators'  => ['Debits**', 'ATM/Purchases', 'OtherDebits', 'Checks'],
                    'credit_indicators' => ['Deposits&Credits'],
                    'check_format'      => 'Check# Amount MM/DD pairs (up to 2 per line)',
                    'notes'             => 'Citizens Bank Analysis Business Checking. Sections: Checks (debit), Debits** (ATM/Purchases + OtherDebits), Deposits&Credits. Stop at DailyBalance. Multi-line descriptions. Providence RI based.',
                ],
            ],

            // 10. Fifth Third Bank
            [
                'bank_name' => 'Fifth Third Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Fifth Third Bank',
                    'FIFTH THIRD',
                    '53.com',
                    'Fifth Third Bancorp',
                    '5/3 Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Fifth Third Bank',
                ],
                'transaction_markers' => [
                    'FIFTH THIRD',
                    '5/3 ATM',
                    '5/3 MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'Fifth Third may show as 5/3 in some transactions',
                ],
            ],

            // ========== REGIONAL & ONLINE BANKS ==========

            // Citizens Bank
            [
                'bank_name' => 'Citizens Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Citizens Bank',
                    'CITIZENS BANK',
                    'citizensbank.com',
                    'Citizens Bank, N.A.',
                    'Citizens Financial Group',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Citizens Bank, N.A.',
                ],
                'transaction_markers' => [
                    'CITIZENS BANK',
                    'CITIZENS ATM',
                    'CITIZENS MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'withdrawals' => 'right',
                    'deposits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Citizens Bank uses Withdrawals and Deposits columns',
                ],
            ],

            // KeyBank
            [
                'bank_name' => 'KeyBank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'KeyBank',
                    'KEYBANK',
                    'key.com',
                    'KeyBank National Association',
                    'KeyCorp',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'KeyBank National Association',
                ],
                'transaction_markers' => [
                    'KEYBANK',
                    'KEY ATM',
                    'KEY MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'KeyBank uses separate Debits and Credits columns',
                ],
            ],

            // Regions Bank
            [
                'bank_name' => 'Regions Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Regions Bank',
                    'REGIONS BANK',
                    'regions.com',
                    'Regions Financial Corporation',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Regions Bank',
                ],
                'transaction_markers' => [
                    'REGIONS BANK',
                    'REGIONS ATM',
                    'REGIONS MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Regions Bank operates primarily in the South and Midwest',
                ],
            ],

            // M&T Bank
            [
                'bank_name' => 'M&T Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'M&T Bank',
                    'M&T BANK',
                    'mtb.com',
                    'M&T Bank Corporation',
                    'Manufacturers and Traders',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'M&T Bank',
                ],
                'transaction_markers' => [
                    'M&T BANK',
                    'M&T ATM',
                    'M&T MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'withdrawals' => 'right',
                    'deposits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'M&T Bank operates primarily in the Northeast',
                ],
            ],

            // Huntington Bank
            [
                'bank_name' => 'Huntington Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Huntington Bank',
                    'HUNTINGTON BANK',
                    'huntington.com',
                    'Huntington National Bank',
                    'Huntington Bancshares',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Huntington National Bank',
                ],
                'transaction_markers' => [
                    'HUNTINGTON',
                    'HUNTINGTON ATM',
                    'HUNTINGTON MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Huntington operates primarily in the Midwest',
                ],
            ],

            // BMO Harris
            [
                'bank_name' => 'BMO Harris',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'BMO Harris',
                    'BMO HARRIS',
                    'bmoharris.com',
                    'BMO Harris Bank',
                    'BMO Bank',
                    'BMO',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'BMO Harris Bank N.A.',
                ],
                'transaction_markers' => [
                    'BMO HARRIS',
                    'BMO ATM',
                    'BMO MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'BMO Harris is Canadian-owned, operates in Midwest US',
                ],
            ],

            // HSBC US
            [
                'bank_name' => 'HSBC',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'HSBC',
                    'HSBC Bank',
                    'hsbc.com',
                    'HSBC Bank USA',
                    'HSBC USA',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'HSBC Bank USA, N.A.',
                ],
                'transaction_markers' => [
                    'HSBC',
                    'HSBC ATM',
                    'HSBC MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'HSBC US operations focus on international banking',
                ],
            ],

            // ========== ONLINE-ONLY BANKS ==========

            // Ally Bank
            [
                'bank_name' => 'Ally Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Ally Bank',
                    'ALLY BANK',
                    'ally.com',
                    'Ally Financial',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Ally Bank',
                ],
                'transaction_markers' => [
                    'ALLY BANK',
                    'ALLY TRANSFER',
                    'ALLY MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'PAYMENT', 'TRANSFER TO', 'FEE', 'CHECK'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Ally is online-only, uses negative amounts for debits',
                ],
            ],

            // Discover Bank
            [
                'bank_name' => 'Discover Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Discover Bank',
                    'DISCOVER BANK',
                    'discover.com',
                    'Discover Financial Services',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Discover Bank',
                ],
                'transaction_markers' => [
                    'DISCOVER BANK',
                    'DISCOVER TRANSFER',
                    'DISCOVER MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'REFUND', 'CASHBACK'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Discover offers cashback checking accounts',
                ],
            ],

            // Marcus by Goldman Sachs
            [
                'bank_name' => 'Marcus by Goldman Sachs',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Marcus by Goldman Sachs',
                    'MARCUS',
                    'marcus.com',
                    'Goldman Sachs Bank',
                    'GS Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Goldman Sachs Bank USA',
                ],
                'transaction_markers' => [
                    'MARCUS',
                    'GS BANK',
                    'GOLDMAN SACHS',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'TRANSFER TO', 'PAYMENT'],
                    'credit_indicators' => ['DEPOSIT', 'TRANSFER FROM', 'INTEREST'],
                    'check_format' => 'No checks - savings only',
                    'notes' => 'Marcus is primarily savings accounts',
                ],
            ],

            // Synchrony Bank
            [
                'bank_name' => 'Synchrony Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Synchrony Bank',
                    'SYNCHRONY',
                    'synchronybank.com',
                    'Synchrony Financial',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Synchrony Bank',
                ],
                'transaction_markers' => [
                    'SYNCHRONY',
                    'SYNCHRONY BANK',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'TRANSFER TO', 'PAYMENT'],
                    'credit_indicators' => ['DEPOSIT', 'TRANSFER FROM', 'INTEREST'],
                    'check_format' => 'No checks typically',
                    'notes' => 'Synchrony focuses on savings and CDs',
                ],
            ],

            // American Express (Banking)
            [
                'bank_name' => 'American Express',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'American Express',
                    'AMERICAN EXPRESS',
                    'americanexpress.com',
                    'AMEX',
                    'American Express National Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'American Express National Bank',
                ],
                'transaction_markers' => [
                    'AMEX',
                    'AMERICAN EXPRESS',
                    'AMEX SAVINGS',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'TRANSFER TO', 'PAYMENT'],
                    'credit_indicators' => ['DEPOSIT', 'TRANSFER FROM', 'INTEREST'],
                    'check_format' => 'No checks - savings accounts',
                    'notes' => 'Amex banking is primarily high-yield savings',
                ],
            ],

            // ========== CREDIT UNIONS ==========

            // Navy Federal Credit Union
            [
                'bank_name' => 'Navy Federal Credit Union',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Navy Federal',
                    'NAVY FEDERAL',
                    'navyfederal.org',
                    'Navy Federal Credit Union',
                    'NFCU',
                ],
                'footer_patterns' => [
                    'Federally Insured by NCUA',
                    'Navy Federal Credit Union',
                ],
                'transaction_markers' => [
                    'NAVY FEDERAL',
                    'NFCU',
                    'NF ATM',
                    'NAVY FED',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'DIVIDEND', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'Navy Federal uses DIVIDEND instead of INTEREST for savings',
                ],
            ],

            // Pentagon Federal Credit Union
            [
                'bank_name' => 'PenFed Credit Union',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'PenFed',
                    'PENFED',
                    'penfed.org',
                    'Pentagon Federal Credit Union',
                ],
                'footer_patterns' => [
                    'Federally Insured by NCUA',
                    'Pentagon Federal Credit Union',
                ],
                'transaction_markers' => [
                    'PENFED',
                    'PENTAGON FED',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'DIVIDEND', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'PenFed uses DIVIDEND for interest payments',
                ],
            ],

            // USAA
            [
                'bank_name' => 'USAA',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'USAA',
                    'USAA Federal Savings Bank',
                    'usaa.com',
                    'United Services Automobile Association',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'USAA Federal Savings Bank',
                ],
                'transaction_markers' => [
                    'USAA',
                    'USAA ATM',
                    'USAA MOBILE',
                    'USAA TRANSFER',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK # followed by number',
                    'notes' => 'USAA serves military members and families',
                ],
            ],

            // State Employees Credit Union
            [
                'bank_name' => 'SECU',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'State Employees Credit Union',
                    'SECU',
                    'ncsecu.org',
                ],
                'footer_patterns' => [
                    'Federally Insured by NCUA',
                    'State Employees Credit Union',
                ],
                'transaction_markers' => [
                    'SECU',
                    'STATE EMPLOYEES',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'DIVIDEND', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'SECU is the second-largest credit union in the US',
                ],
            ],

            // ========== BROKERAGE BANKS ==========

            // Charles Schwab
            [
                'bank_name' => 'Charles Schwab',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Charles Schwab',
                    'SCHWAB',
                    'schwab.com',
                    'Charles Schwab Bank',
                    'Schwab Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Charles Schwab Bank, SSB',
                ],
                'transaction_markers' => [
                    'SCHWAB',
                    'CHARLES SCHWAB',
                    'SCHWAB ATM',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'withdrawals' => 'right',
                    'deposits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'REFUND', 'BROKERAGE LINK'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Schwab checking often linked to brokerage accounts',
                ],
            ],

            // Fidelity
            [
                'bank_name' => 'Fidelity',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Fidelity',
                    'FIDELITY',
                    'fidelity.com',
                    'Fidelity Investments',
                    'Fidelity Brokerage',
                ],
                'footer_patterns' => [
                    'SIPC',
                    'Fidelity Investments',
                ],
                'transaction_markers' => [
                    'FIDELITY',
                    'FID BKG SVC',
                    'FIDELITY ATM',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'DIVIDEND', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Fidelity Cash Management Account (CMA) is a brokerage product',
                ],
            ],

            // E*TRADE
            [
                'bank_name' => 'E*TRADE',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'E*TRADE',
                    'ETRADE',
                    'etrade.com',
                    'E*TRADE Bank',
                    'E*TRADE Financial',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'E*TRADE Bank',
                ],
                'transaction_markers' => [
                    'ETRADE',
                    'E*TRADE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'DIVIDEND', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'E*TRADE is now part of Morgan Stanley',
                ],
            ],

            // ========== NEOBANKS / FINTECHS ==========

            // Chime
            [
                'bank_name' => 'Chime',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Chime',
                    'CHIME',
                    'chime.com',
                    'Chime Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Bancorp Bank',
                    'Stride Bank',
                ],
                'transaction_markers' => [
                    'CHIME',
                    'CHIME CHECKING',
                    'CHIME SAVINGS',
                    'SPOT ME',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'PAYMENT', 'TRANSFER TO', 'FEE', 'SPOT ME'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'ROUND UP', 'REFUND'],
                    'check_format' => 'No checks typically',
                    'notes' => 'Chime is a fintech - uses partner banks for FDIC',
                ],
            ],

            // Current
            [
                'bank_name' => 'Current',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Current',
                    'CURRENT',
                    'current.com',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Choice Financial Group',
                ],
                'transaction_markers' => [
                    'CURRENT',
                    'CURRENT MOBILE',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'REFUND'],
                    'check_format' => 'No checks',
                    'notes' => 'Current is mobile-first banking',
                ],
            ],

            // Varo Bank
            [
                'bank_name' => 'Varo Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Varo',
                    'VARO',
                    'varomoney.com',
                    'Varo Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Varo Bank, N.A.',
                ],
                'transaction_markers' => [
                    'VARO',
                    'VARO BANK',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'REFUND'],
                    'check_format' => 'No checks',
                    'notes' => 'Varo is the first all-digital nationally chartered bank',
                ],
            ],

            // SoFi
            [
                'bank_name' => 'SoFi',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'SoFi',
                    'SOFI',
                    'sofi.com',
                    'SoFi Bank',
                    'Social Finance',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'SoFi Bank, N.A.',
                ],
                'transaction_markers' => [
                    'SOFI',
                    'SOFI BANK',
                    'SOFI MONEY',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'DEBIT', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['DEPOSIT', 'CREDIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'No checks typically',
                    'notes' => 'SoFi offers high-yield checking and savings',
                ],
            ],

            // ========== PAYMENT PLATFORMS ==========

            // PayPal
            [
                'bank_name' => 'PayPal',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'PayPal',
                    'PAYPAL',
                    'paypal.com',
                    'PayPal Holdings',
                ],
                'footer_patterns' => [
                    'PayPal, Inc.',
                    'PayPal Holdings',
                ],
                'transaction_markers' => [
                    'PAYPAL',
                    'PP*',
                    'PAYPAL TRANSFER',
                    'PAYPAL INST XFER',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['WITHDRAWAL', 'PAYMENT', 'TRANSFER TO', 'PURCHASE', 'SENT'],
                    'credit_indicators' => ['DEPOSIT', 'TRANSFER FROM', 'RECEIVED', 'REFUND', 'MONEY RECEIVED'],
                    'check_format' => 'No checks',
                    'notes' => 'PayPal transactions often start with PP*',
                ],
            ],

            // Venmo
            [
                'bank_name' => 'Venmo',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Venmo',
                    'VENMO',
                    'venmo.com',
                ],
                'footer_patterns' => [
                    'PayPal, Inc.',
                ],
                'transaction_markers' => [
                    'VENMO',
                    'VENMO PAYMENT',
                    'VENMO CASHOUT',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['PAYMENT', 'SENT', 'TRANSFER TO', 'PURCHASE'],
                    'credit_indicators' => ['RECEIVED', 'TRANSFER FROM', 'CASHOUT', 'REFUND'],
                    'check_format' => 'No checks',
                    'notes' => 'Venmo is owned by PayPal',
                ],
            ],

            // Cash App
            [
                'bank_name' => 'Cash App',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Cash App',
                    'CASH APP',
                    'cash.app',
                    'Square Cash',
                ],
                'footer_patterns' => [
                    'Block, Inc.',
                    'Square, Inc.',
                ],
                'transaction_markers' => [
                    'CASH APP',
                    'SQ *CASH',
                    'SQUARE CASH',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['SENT', 'PAYMENT', 'TRANSFER TO', 'CASH OUT'],
                    'credit_indicators' => ['RECEIVED', 'TRANSFER FROM', 'DEPOSIT', 'REFUND'],
                    'check_format' => 'No checks',
                    'notes' => 'Cash App transactions often appear as SQ *CASH',
                ],
            ],

            // Zelle
            [
                'bank_name' => 'Zelle',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Zelle',
                    'ZELLE',
                    'zellepay.com',
                ],
                'footer_patterns' => [
                    'Early Warning Services',
                ],
                'transaction_markers' => [
                    'ZELLE',
                    'ZELLE PAYMENT',
                    'ZELLE TO',
                    'ZELLE FROM',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'amount' => 'right',
                    'balance' => 'far_right',
                    'separate_debit_credit' => false,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['ZELLE TO', 'ZELLE PAYMENT TO', 'SENT'],
                    'credit_indicators' => ['ZELLE FROM', 'ZELLE PAYMENT FROM', 'RECEIVED'],
                    'check_format' => 'No checks',
                    'notes' => 'Zelle is integrated into many bank apps',
                ],
            ],

            // ========== ADDITIONAL REGIONAL BANKS ==========

            // Comerica
            [
                'bank_name' => 'Comerica',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Comerica',
                    'COMERICA',
                    'comerica.com',
                    'Comerica Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Comerica Bank',
                ],
                'transaction_markers' => [
                    'COMERICA',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Comerica is based in Texas and Michigan',
                ],
            ],

            // Zions Bank
            [
                'bank_name' => 'Zions Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Zions Bank',
                    'ZIONS BANK',
                    'zionsbank.com',
                    'Zions Bancorporation',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Zions Bancorporation',
                ],
                'transaction_markers' => [
                    'ZIONS',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Zions operates primarily in the Western US',
                ],
            ],

            // First Republic Bank
            [
                'bank_name' => 'First Republic Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'First Republic',
                    'FIRST REPUBLIC',
                    'firstrepublic.com',
                    'First Republic Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'First Republic Bank',
                ],
                'transaction_markers' => [
                    'FIRST REPUBLIC',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'First Republic was acquired by JPMorgan Chase in 2023',
                ],
            ],

            // Silicon Valley Bank
            [
                'bank_name' => 'Silicon Valley Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Silicon Valley Bank',
                    'SVB',
                    'svb.com',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Silicon Valley Bank',
                ],
                'transaction_markers' => [
                    'SVB',
                    'SILICON VALLEY',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE', 'WIRE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND', 'WIRE IN'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'SVB operations now under First Citizens Bank',
                ],
            ],

            // Webster Bank
            [
                'bank_name' => 'Webster Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Webster Bank',
                    'WEBSTER BANK',
                    'websterbank.com',
                    'Webster Financial',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Webster Bank, N.A.',
                ],
                'transaction_markers' => [
                    'WEBSTER',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Webster operates primarily in the Northeast',
                ],
            ],

            // Popular Bank
            [
                'bank_name' => 'Popular Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Popular Bank',
                    'POPULAR',
                    'popular.com',
                    'Banco Popular',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Popular, Inc.',
                ],
                'transaction_markers' => [
                    'POPULAR',
                    'BANCO POPULAR',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Popular Bank operates in Puerto Rico, NY, NJ, FL',
                ],
            ],

            // First Citizens Bank
            [
                'bank_name' => 'First Citizens Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'First Citizens',
                    'FIRST CITIZENS',
                    'firstcitizens.com',
                    'First Citizens Bank',
                    'First-Citizens Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'First-Citizens Bank & Trust',
                ],
                'transaction_markers' => [
                    'FIRST CITIZENS',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'First Citizens acquired SVB operations in 2023',
                ],
            ],

            // New York Community Bank
            [
                'bank_name' => 'New York Community Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'New York Community Bank',
                    'NYCB',
                    'mynycb.com',
                    'Flagstar Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'New York Community Bank',
                ],
                'transaction_markers' => [
                    'NYCB',
                    'FLAGSTAR',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'NYCB merged with Flagstar Bank in 2022',
                ],
            ],

            // East West Bank
            [
                'bank_name' => 'East West Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'East West Bank',
                    'EAST WEST',
                    'eastwestbank.com',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'East West Bank',
                ],
                'transaction_markers' => [
                    'EAST WEST',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'East West Bank focuses on US-Asia banking',
                ],
            ],

            // Valley National Bank
            [
                'bank_name' => 'Valley National Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Valley National Bank',
                    'VALLEY NATIONAL',
                    'valley.com',
                    'Valley Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Valley National Bank',
                ],
                'transaction_markers' => [
                    'VALLEY',
                    'VALLEY NATIONAL',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Valley operates in NJ, NY, FL, AL',
                ],
            ],

            // Wintrust Bank
            [
                'bank_name' => 'Wintrust Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Wintrust',
                    'WINTRUST',
                    'wintrust.com',
                    'Wintrust Bank',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Wintrust Financial',
                ],
                'transaction_markers' => [
                    'WINTRUST',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Wintrust operates primarily in Chicago area',
                ],
            ],

            // Glacier Bank
            [
                'bank_name' => 'Glacier Bank',
                'layout_version' => 'default',
                'date_format' => 'MM/DD/YYYY',
                'header_patterns' => [
                    'Glacier Bank',
                    'GLACIER',
                    'glacierbank.com',
                    'Glacier Bancorp',
                ],
                'footer_patterns' => [
                    'Member FDIC',
                    'Glacier Bancorp',
                ],
                'transaction_markers' => [
                    'GLACIER',
                ],
                'column_structure' => [
                    'date' => 'left',
                    'description' => 'center',
                    'debits' => 'right',
                    'credits' => 'far_right',
                    'balance' => 'separate',
                    'separate_debit_credit' => true,
                ],
                'extraction_rules' => [
                    'debit_indicators' => ['DEBIT', 'WITHDRAWAL', 'CHECK', 'PAYMENT', 'TRANSFER TO', 'FEE'],
                    'credit_indicators' => ['CREDIT', 'DEPOSIT', 'TRANSFER FROM', 'INTEREST', 'REFUND'],
                    'check_format' => 'CHECK followed by number',
                    'notes' => 'Glacier operates in Montana and surrounding states',
                ],
            ],
        ];
    }
}
