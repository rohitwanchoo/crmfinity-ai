<x-app-layout>
    <style>
        /* Category color indicators for modal */
        .category-color-purple { background-color: rgb(147, 51, 234); }
        .category-color-blue { background-color: rgb(59, 130, 246); }
        .category-color-indigo { background-color: rgb(99, 102, 241); }
        .category-color-green { background-color: rgb(34, 197, 94); }
        .category-color-cyan { background-color: rgb(6, 182, 212); }
        .category-color-teal { background-color: rgb(20, 184, 166); }
        .category-color-gray { background-color: rgb(107, 114, 128); }
        .category-color-amber { background-color: rgb(245, 158, 11); }
        .category-color-orange { background-color: rgb(249, 115, 22); }
        .category-color-red { background-color: rgb(239, 68, 68); }
        .category-color-pink { background-color: rgb(236, 72, 153); }
        .category-color-yellow { background-color: rgb(234, 179, 8); }
        .category-color-emerald { background-color: rgb(16, 185, 129); }
        .category-color-lime { background-color: rgb(132, 204, 22); }

        /* Real-time update animation for transaction rows */
        tr[class*="txn-row-"] {
            transition: background-color 0.3s ease-in-out;
        }

        /* Smooth category badge updates */
        .category-badge {
            transition: all 0.2s ease-in-out;
        }

        /* MCA filter indicator animation */
        @keyframes slide-in-right {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .animate-slide-in-right {
            animation: slide-in-right 0.3s ease-out;
        }

        /* Smooth transitions for MCA transaction filtering */
        tr[data-transaction-id] {
            transition: opacity 0.3s ease-in-out, background-color 0.3s ease-in-out;
        }

        /* MCA view button hover effects */
        .mca-view-btn {
            transition: all 0.2s ease-in-out;
        }
    </style>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Analysis Results
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="w-full px-2">
            <div class="mb-6">
                <a href="{{ route('bankstatement.index') }}" class="inline-flex items-center text-sm text-green-600 dark:text-green-400 hover:underline">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Bank Statement Analyzer
                </a>
            </div>

            @php
                // Collect all monthly data across all statements for combined view
                $allMonthlyData = [];
                $allSuccessfulResults = collect($results)->where('success', true);
                // Collect all session IDs for linking back to full analysis
                $allSessionIds = $allSuccessfulResults->pluck('session_id')->toArray();

                // Aggregate all months from all results
                $combinedMonths = [];
                $combinedMca = [
                    'total_mca_count' => 0,
                    'total_mca_payments' => 0,
                    'total_mca_amount' => 0,
                    'total_funding_count' => 0,
                    'total_funding_amount' => 0,
                    'lenders' => [],
                ];
                $combinedTotals = [
                    'deposits' => 0,
                    'adjustments' => 0,
                    'true_revenue' => 0,
                    'debits' => 0,
                    'deposit_count' => 0,
                    'debit_count' => 0,
                    'nsf_count' => 0,
                    'negative_days' => 0,
                    'transactions' => 0,
                    'api_cost' => 0,
                ];

                foreach ($allSuccessfulResults as $res) {
                    // Combine monthly data
                    if (isset($res['monthly_data']['months'])) {
                        foreach ($res['monthly_data']['months'] as $month) {
                            $key = $month['month_key'];
                            if (!isset($combinedMonths[$key])) {
                                $combinedMonths[$key] = $month;
                                $combinedMonths[$key]['session_id'] = $res['session_id'];
                            } else {
                                // Merge data if same month appears in multiple files
                                $combinedMonths[$key]['deposits'] += $month['deposits'];
                                $combinedMonths[$key]['adjustments'] += $month['adjustments'];
                                $combinedMonths[$key]['true_revenue'] += $month['true_revenue'];
                                $combinedMonths[$key]['debits'] += $month['debits'];
                                $combinedMonths[$key]['deposit_count'] += $month['deposit_count'];
                                $combinedMonths[$key]['debit_count'] += $month['debit_count'];
                                $combinedMonths[$key]['nsf_count'] += $month['nsf_count'];
                                $combinedMonths[$key]['transactions'] = array_merge($combinedMonths[$key]['transactions'], $month['transactions']);
                                $combinedMonths[$key]['adjustment_items'] = array_merge($combinedMonths[$key]['adjustment_items'], $month['adjustment_items']);
                            }
                        }
                    }

                    // Combine totals
                    $combinedTotals['deposits'] += $res['monthly_data']['totals']['deposits'] ?? 0;
                    $combinedTotals['adjustments'] += $res['monthly_data']['totals']['adjustments'] ?? 0;
                    $combinedTotals['true_revenue'] += $res['monthly_data']['totals']['true_revenue'] ?? 0;
                    $combinedTotals['debits'] += $res['monthly_data']['totals']['debits'] ?? 0;
                    // Use summary counts for consistency with individual session display
                    $combinedTotals['deposit_count'] += $res['summary']['credit_count'] ?? 0;
                    $combinedTotals['debit_count'] += $res['summary']['debit_count'] ?? 0;
                    $combinedTotals['nsf_count'] += $res['monthly_data']['totals']['nsf_count'] ?? 0;
                    $combinedTotals['transactions'] += $res['summary']['total_transactions'] ?? 0;
                    $combinedTotals['api_cost'] += $res['api_cost']['total_cost'] ?? 0;

                    // Combine MCA data
                    if (isset($res['mca_analysis'])) {
                        foreach ($res['mca_analysis']['lenders'] ?? [] as $lender) {
                            $lid = $lender['lender_id'];
                            if (!isset($combinedMca['lenders'][$lid])) {
                                $combinedMca['lenders'][$lid] = $lender;
                                // Initialize funding fields if not present
                                $combinedMca['lenders'][$lid]['funding_count'] = $lender['funding_count'] ?? 0;
                                $combinedMca['lenders'][$lid]['total_funding'] = $lender['total_funding'] ?? 0;
                                $combinedMca['lenders'][$lid]['has_funding'] = $lender['has_funding'] ?? false;
                            } else {
                                $combinedMca['lenders'][$lid]['payment_count'] += $lender['payment_count'];
                                $combinedMca['lenders'][$lid]['total_amount'] += $lender['total_amount'];
                                $combinedMca['lenders'][$lid]['funding_count'] = ($combinedMca['lenders'][$lid]['funding_count'] ?? 0) + ($lender['funding_count'] ?? 0);
                                $combinedMca['lenders'][$lid]['total_funding'] = ($combinedMca['lenders'][$lid]['total_funding'] ?? 0) + ($lender['total_funding'] ?? 0);
                                if ($lender['has_funding'] ?? false) {
                                    $combinedMca['lenders'][$lid]['has_funding'] = true;
                                }
                                $combinedMca['lenders'][$lid]['unique_amounts'] = array_unique(array_merge(
                                    $combinedMca['lenders'][$lid]['unique_amounts'] ?? [],
                                    $lender['unique_amounts'] ?? []
                                ));
                            }
                        }
                    }
                }

                // Sort months chronologically
                ksort($combinedMonths);
                $monthCount = count($combinedMonths);

                // Calculate negative days for each month
                foreach ($combinedMonths as $monthKey => &$month) {
                    $negativeDays = 0;
                    $dailyBalances = [];
                    $hasActualBalances = false;

                    // Group transactions by date
                    if (isset($month['transactions']) && is_array($month['transactions'])) {
                        // First check if we have actual balance data
                        foreach ($month['transactions'] as $txn) {
                            if (isset($txn['ending_balance']) && $txn['ending_balance'] !== null) {
                                $hasActualBalances = true;
                                break;
                            }
                        }

                        if ($hasActualBalances) {
                            // Use actual ending balances from bank statement
                            foreach ($month['transactions'] as $txn) {
                                if (!is_array($txn)) continue;

                                $date = $txn['date'] ?? null;
                                if (!$date) continue;

                                // Convert date to string if it's an object
                                $dateStr = is_string($date) ? $date : (string) $date;
                                if (!$dateStr) continue;

                                $endingBalance = $txn['ending_balance'] ?? null;
                                if ($endingBalance === null) continue;

                                // Store the ending balance for this date (use last transaction if multiple on same day)
                                $dailyBalances[$dateStr] = (float) $endingBalance;
                            }

                            // Sort dates chronologically and count negative days
                            ksort($dailyBalances);
                            foreach ($dailyBalances as $date => $balance) {
                                if ($balance < 0) {
                                    $negativeDays++;
                                }
                            }
                        } else {
                            // Fallback: Calculate running balance from transactions
                            foreach ($month['transactions'] as $txn) {
                                if (!is_array($txn)) continue;

                                $date = $txn['date'] ?? null;
                                if (!$date) continue;

                                // Convert date to string if it's an object
                                $dateStr = is_string($date) ? $date : (string) $date;
                                if (!$dateStr) continue;

                                if (!isset($dailyBalances[$dateStr])) {
                                    $dailyBalances[$dateStr] = 0;
                                }

                                $amount = (float) ($txn['amount'] ?? 0);
                                $type = $txn['type'] ?? 'debit';

                                // Add credits, subtract debits
                                if ($type === 'credit') {
                                    $dailyBalances[$dateStr] += $amount;
                                } else {
                                    $dailyBalances[$dateStr] -= $amount;
                                }
                            }

                            // Sort dates chronologically
                            ksort($dailyBalances);

                            // Calculate running balance and count negative days
                            // Use beginning_balance from this month's statement if available
                            $runningBalance = isset($month['beginning_balance']) ? (float) $month['beginning_balance'] : 0;
                            foreach ($dailyBalances as $date => $netFlow) {
                                $runningBalance += $netFlow;
                                if ($runningBalance < 0) {
                                    $negativeDays++;
                                }
                            }
                        }
                    }

                    $month['negative_days'] = $negativeDays;
                    $combinedTotals['negative_days'] += $negativeDays;
                }
                unset($month); // Unset reference

                // Detect other accounts from transfer transactions
                $otherAccounts = [];
                $transferPatterns = [
                    'transfer from', 'xfer from', 'tfr from', 'trnsfr from',
                    'transfer to', 'xfer to', 'tfr to', 'trnsfr to',
                    'online transfer', 'wire transfer', 'ach transfer',
                    'internal transfer', 'account transfer', 'funds transfer'
                ];

                $accountTypePatterns = [
                    'checking' => ['checking', 'chequing', 'chk', 'check acct'],
                    'savings' => ['savings', 'save', 'sav', 'svgs'],
                    'loan' => ['loan', 'lending', 'credit', 'line of credit', 'loc'],
                    'money_market' => ['money market', 'mm', 'mma'],
                    'credit_card' => ['credit card', 'cc', 'card'],
                ];

                foreach ($combinedMonths as $monthKey => $month) {
                    if (!isset($month['transactions']) || !is_array($month['transactions'])) continue;

                    foreach ($month['transactions'] as $txn) {
                        if (!is_array($txn)) continue;

                        $description = strtolower($txn['description'] ?? '');
                        $isTransfer = false;

                        // Check if this is a transfer transaction
                        foreach ($transferPatterns as $pattern) {
                            if (stripos($description, $pattern) !== false) {
                                $isTransfer = true;
                                break;
                            }
                        }

                        if (!$isTransfer) continue;

                        // Extract account number
                        $accountNumber = null;
                        $originalDesc = $txn['description'] ?? '';

                        // Pattern 1: TRANSFER FROM/TO 1234567
                        if (preg_match('/(?:FROM\/TO|TO\/FROM)\s+(\d{4,})/i', $originalDesc, $matches)) {
                            $accountNumber = $matches[1];
                        }
                        // Pattern 2: ACCT ****1234, ACCOUNT XXXX1234
                        elseif (preg_match('/(?:ACCT|ACCOUNT|A\/C)[\s#:]*([X*]+)?(\d{4,})/i', $originalDesc, $matches)) {
                            $accountNumber = $matches[2];
                        }
                        // Pattern 3: ending in 1234
                        elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $originalDesc, $matches)) {
                            $accountNumber = $matches[1];
                        }
                        // Pattern 4: ****1234 or XXXX1234
                        elseif (preg_match('/[X*]{4,}(\d{4,})/', $originalDesc, $matches)) {
                            $accountNumber = $matches[1];
                        }

                        if (!$accountNumber) continue;

                        // Detect account type
                        $accountType = 'unknown';
                        // Special case: LOAN PAYMENT prefix
                        if (stripos($originalDesc, 'LOAN PAYMENT') !== false || stripos($originalDesc, 'LOAN PMT') !== false) {
                            $accountType = 'loan';
                        } else {
                            foreach ($accountTypePatterns as $type => $patterns) {
                                foreach ($patterns as $pattern) {
                                    if (stripos($description, $pattern) !== false) {
                                        $accountType = $type;
                                        break 2;
                                    }
                                }
                            }
                        }

                        // Determine transfer direction
                        // For FROM/TO format: if it's a debit, money is going TO the other account
                        // if it's a credit, money is coming FROM the other account
                        $direction = 'unknown';
                        $txnType = $txn['type'] ?? 'debit';

                        if (stripos($originalDesc, 'FROM/TO') !== false || stripos($originalDesc, 'TO/FROM') !== false) {
                            // Bi-directional transfer notation
                            // If debit: money going TO other account
                            // If credit: money coming FROM other account
                            $direction = ($txnType === 'debit') ? 'to' : 'from';
                        } elseif (preg_match('/\bFROM\b/i', $originalDesc) && !preg_match('/\bTO\b/i', $originalDesc)) {
                            $direction = 'from';
                        } elseif (preg_match('/\bTO\b/i', $originalDesc) && !preg_match('/\bFROM\b/i', $originalDesc)) {
                            $direction = 'to';
                        } else {
                            // Fallback: use transaction type
                            $direction = ($txnType === 'debit') ? 'to' : 'from';
                        }

                        // Group by account number
                        if (!isset($otherAccounts[$accountNumber])) {
                            $otherAccounts[$accountNumber] = [
                                'account_number' => $accountNumber,
                                'account_type' => $accountType,
                                'transfer_count' => 0,
                                'total_from' => 0,
                                'total_to' => 0,
                                'count_from' => 0,
                                'count_to' => 0,
                                'first_seen' => $txn['date'] ?? null,
                                'last_seen' => $txn['date'] ?? null,
                                'sample_descriptions' => [],
                            ];
                        }

                        $account = &$otherAccounts[$accountNumber];
                        $account['transfer_count']++;

                        $amount = (float) ($txn['amount'] ?? 0);

                        if ($direction === 'from') {
                            $account['total_from'] += $amount;
                            $account['count_from']++;
                        } elseif ($direction === 'to') {
                            $account['total_to'] += $amount;
                            $account['count_to']++;
                        }

                        // Update date range
                        $txnDate = $txn['date'] ?? null;
                        if ($txnDate) {
                            if (!$account['first_seen'] || $txnDate < $account['first_seen']) {
                                $account['first_seen'] = $txnDate;
                            }
                            if (!$account['last_seen'] || $txnDate > $account['last_seen']) {
                                $account['last_seen'] = $txnDate;
                            }
                        }

                        // Keep sample descriptions (max 3)
                        if (count($account['sample_descriptions']) < 3) {
                            $account['sample_descriptions'][] = $txn['description'] ?? '';
                        }

                        unset($account);
                    }
                }

                // Sort accounts by transfer count (most active first)
                usort($otherAccounts, function($a, $b) {
                    return $b['transfer_count'] - $a['transfer_count'];
                });

                // Calculate averages
                $combinedAverages = [
                    'deposits' => $monthCount > 0 ? $combinedTotals['deposits'] / $monthCount : 0,
                    'adjustments' => $monthCount > 0 ? $combinedTotals['adjustments'] / $monthCount : 0,
                    'true_revenue' => $monthCount > 0 ? $combinedTotals['true_revenue'] / $monthCount : 0,
                    'debits' => $monthCount > 0 ? $combinedTotals['debits'] / $monthCount : 0,
                    'deposit_count' => $monthCount > 0 ? $combinedTotals['deposit_count'] / $monthCount : 0,
                    'negative_days' => $monthCount > 0 ? $combinedTotals['negative_days'] / $monthCount : 0,
                    'average_daily' => $monthCount > 0 ? $combinedTotals['true_revenue'] / ($monthCount * 30) : 0,
                ];

                // Finalize MCA totals
                $combinedMca['total_mca_count'] = count($combinedMca['lenders']);
                foreach ($combinedMca['lenders'] as $lender) {
                    $combinedMca['total_mca_payments'] += $lender['payment_count'] ?? 0;
                    $combinedMca['total_mca_amount'] += $lender['total_amount'] ?? 0;
                    $combinedMca['total_funding_count'] += $lender['funding_count'] ?? 0;
                    $combinedMca['total_funding_amount'] += $lender['total_funding'] ?? 0;
                }
                $combinedMca['lenders'] = array_values($combinedMca['lenders']);
            @endphp

            @if(count($allSuccessfulResults) > 0)
            <!-- Combined Analysis Section -->
            <div class="mb-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-2 border-green-500">
                <div class="p-2">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Combined Analysis Summary</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($allSuccessfulResults) }} {{ Str::plural('file', count($allSuccessfulResults)) }} analyzed • {{ $monthCount }} {{ Str::plural('month', $monthCount) }} of data</p>
                            </div>
                        </div>
                        <!-- View Mode Toggle -->
                        <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                            <button onclick="toggleDashboardView('credit')" id="credit-view-btn" class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 bg-white dark:bg-gray-800 text-green-600 dark:text-green-400 shadow-sm">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                    </svg>
                                    Credit View
                                </div>
                            </button>
                            <button onclick="toggleDashboardView('debit')" id="debit-view-btn" class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                    </svg>
                                    Debit View
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Combined Summary Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                        <div class="metric-card bg-gray-50 dark:bg-gray-700 p-4 rounded-lg transition-all duration-300" data-metric-type="neutral">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Transactions</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($combinedTotals['transactions']) }}</p>
                        </div>
                        <div class="metric-card bg-gray-50 dark:bg-gray-700 p-4 rounded-lg transition-all duration-300" data-metric-type="credit">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Deposits</p>
                            <p class="metric-value combined-metric-deposits text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($combinedTotals['deposits'], 2) }}</p>
                            <p class="metric-subtext text-xs text-gray-500">{{ $combinedTotals['deposit_count'] }} transactions</p>
                        </div>
                        <div class="metric-card bg-gray-50 dark:bg-gray-700 p-4 rounded-lg transition-all duration-300" data-metric-type="neutral">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Adjustments</p>
                            <p class="text-2xl font-bold text-orange-600 dark:text-orange-400 combined-metric-adj">${{ number_format($combinedTotals['adjustments'], 2) }}</p>
                        </div>
                        <div class="metric-card bg-green-50 dark:bg-green-900/30 p-4 rounded-lg border-2 border-green-300 dark:border-green-700 transition-all duration-300" data-metric-type="credit">
                            <p class="text-sm text-green-700 dark:text-green-300 font-medium">True Revenue</p>
                            <p class="metric-value combined-metric-rev text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($combinedTotals['true_revenue'], 2) }}</p>
                            <p class="metric-subtext text-xs text-green-600">Avg: ${{ number_format($combinedAverages['true_revenue'], 2) }}/mo</p>
                        </div>
                        <div class="metric-card bg-gray-50 dark:bg-gray-700 p-4 rounded-lg transition-all duration-300" data-metric-type="debit">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Debits</p>
                            <p class="metric-value combined-metric-debits text-2xl font-bold text-red-600 dark:text-red-400">${{ number_format($combinedTotals['debits'], 2) }}</p>
                            <p class="metric-subtext text-xs text-gray-500">{{ $combinedTotals['debit_count'] }} transactions</p>
                        </div>
                        <div class="metric-card bg-gray-50 dark:bg-gray-700 p-4 rounded-lg transition-all duration-300" data-metric-type="debit">
                            <p class="text-sm text-gray-500 dark:text-gray-400">NSF/OD Fees</p>
                            <p class="metric-value text-2xl font-bold {{ $combinedTotals['nsf_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $combinedTotals['nsf_count'] }}</p>
                        </div>
                    </div>

                    <!-- Combined Monthly Breakdown Table -->
                    @if($monthCount > 0)
                    <div class="mb-6 border dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="bg-green-600 px-4 py-3">
                            <h4 class="font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                Monthly Bank Details (All Statements Combined)
                            </h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Month</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Monthly Deposits</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Adjustments</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-green-600 dark:text-green-400 uppercase">True Revenue</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Avg Daily</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">NSF</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase"># Deposits</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-red-600 dark:text-red-400 uppercase">Negative Days</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Total Debits</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($combinedMonths as $month)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $month['month_name'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">${{ number_format($month['deposits'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-orange-600 dark:text-orange-400">${{ number_format($month['adjustments'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-green-600 dark:text-green-400">${{ number_format($month['true_revenue'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">${{ number_format($month['average_daily'] ?? ($month['true_revenue'] / 30), 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-center {{ $month['nsf_count'] > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">{{ $month['nsf_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $month['deposit_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center {{ ($month['negative_days'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">{{ $month['negative_days'] ?? 0 }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400">${{ number_format($month['debits'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100 dark:bg-gray-700">
                                    <tr class="font-semibold">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">${{ number_format($combinedTotals['deposits'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-orange-600 dark:text-orange-400">${{ number_format($combinedTotals['adjustments'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-green-600 dark:text-green-400">${{ number_format($combinedTotals['true_revenue'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">${{ number_format($combinedAverages['average_daily'] ?? $combinedAverages['average_daily_revenue'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-center {{ $combinedTotals['nsf_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $combinedTotals['nsf_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">{{ $combinedTotals['deposit_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center {{ $combinedTotals['negative_days'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $combinedTotals['negative_days'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400">${{ number_format($combinedTotals['debits'], 2) }}</td>
                                    </tr>
                                    <tr class="text-gray-600 dark:text-gray-400">
                                        <td class="px-4 py-3 text-sm">Monthly Average</td>
                                        <td class="px-4 py-3 text-sm text-right">${{ number_format($combinedAverages['deposits'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">${{ number_format($combinedAverages['adjustments'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">${{ number_format($combinedAverages['true_revenue'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">${{ number_format($combinedAverages['average_daily'] ?? $combinedAverages['average_daily_revenue'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-center">-</td>
                                        <td class="px-4 py-3 text-sm text-center">{{ number_format($combinedAverages['deposit_count'], 1) }}</td>
                                        <td class="px-4 py-3 text-sm text-center">{{ number_format($combinedAverages['negative_days'], 1) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">${{ number_format($combinedAverages['debits'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    @endif

                    <!-- Other Accounts Detected Section -->
                    @if(count($otherAccounts) > 0)
                    <div class="mb-6 border dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="bg-blue-600 px-4 py-3">
                            <h4 class="font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                Other Accounts Detected from Transfers
                                <span class="ml-2 px-2 py-0.5 bg-white/20 rounded text-sm">{{ count($otherAccounts) }} {{ Str::plural('Account', count($otherAccounts)) }}</span>
                            </h4>
                        </div>

                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border-b dark:border-gray-700">
                            <div class="flex items-start gap-2 text-sm text-blue-800 dark:text-blue-200">
                                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p>The following accounts were detected from transfer transactions. This helps identify other banking relationships and account types.</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Account</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Type</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Transfers</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-green-600 dark:text-green-400 uppercase">From Account</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-red-600 dark:text-red-400 uppercase">To Account</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">First Seen</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Last Seen</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Sample Description</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($otherAccounts as $account)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-mono bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 border border-blue-300 dark:border-blue-700">
                                                <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                </svg>
                                                ****{{ $account['account_number'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @php
                                                $typeLabels = [
                                                    'checking' => ['label' => 'Checking', 'color' => 'blue'],
                                                    'savings' => ['label' => 'Savings', 'color' => 'green'],
                                                    'loan' => ['label' => 'Loan', 'color' => 'red'],
                                                    'money_market' => ['label' => 'Money Market', 'color' => 'purple'],
                                                    'credit_card' => ['label' => 'Credit', 'color' => 'orange'],
                                                    'unknown' => ['label' => 'Unknown', 'color' => 'gray'],
                                                ];
                                                $typeInfo = $typeLabels[$account['account_type']] ?? $typeLabels['unknown'];
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-{{ $typeInfo['color'] }}-100 text-{{ $typeInfo['color'] }}-800 dark:bg-{{ $typeInfo['color'] }}-900 dark:text-{{ $typeInfo['color'] }}-200">
                                                {{ $typeInfo['label'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $account['transfer_count'] }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right">
                                            @if($account['count_from'] > 0)
                                                <div class="text-green-600 dark:text-green-400 font-semibold">
                                                    ${{ number_format($account['total_from'], 2) }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $account['count_from'] }} {{ Str::plural('transfer', $account['count_from']) }}
                                                </div>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right">
                                            @if($account['count_to'] > 0)
                                                <div class="text-red-600 dark:text-red-400 font-semibold">
                                                    ${{ number_format($account['total_to'], 2) }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $account['count_to'] }} {{ Str::plural('transfer', $account['count_to']) }}
                                                </div>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">
                                            {{ is_string($account['first_seen']) ? date('M d, Y', strtotime($account['first_seen'])) : ($account['first_seen'] ? $account['first_seen']->format('M d, Y') : '-') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">
                                            {{ is_string($account['last_seen']) ? date('M d, Y', strtotime($account['last_seen'])) : ($account['last_seen'] ? $account['last_seen']->format('M d, Y') : '-') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 max-w-xs">
                                            <div class="truncate" title="{{ $account['sample_descriptions'][0] ?? '' }}">
                                                {{ $account['sample_descriptions'][0] ?? '-' }}
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="p-4 bg-gray-50 dark:bg-gray-800 border-t dark:border-gray-700">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <p class="font-semibold mb-2">Understanding This Data:</p>
                                <ul class="list-disc list-inside space-y-1 ml-2">
                                    <li><strong>From Account:</strong> Money transferred FROM this account INTO the analyzed account (inbound transfers)</li>
                                    <li><strong>To Account:</strong> Money transferred TO this account FROM the analyzed account (outbound transfers)</li>
                                    <li><strong>Account Type:</strong> Detected from transaction descriptions (checking, savings, loan, etc.)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Combined MCA Detection Section -->
                    <div id="combined-mca-section" class="mb-6 border dark:border-gray-700 rounded-lg overflow-hidden {{ $combinedMca['total_mca_count'] > 0 ? '' : 'hidden' }}">
                        <div class="bg-red-600 px-4 py-3">
                            <h4 class="font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                Existing MCA Obligations
                                <span id="combined-mca-lender-count" class="ml-2 px-2 py-0.5 bg-white/20 rounded text-sm">{{ $combinedMca['total_mca_count'] }} {{ Str::plural('Lender', $combinedMca['total_mca_count']) }}</span>
                            </h4>
                        </div>

                        <div class="p-4 bg-red-50 dark:bg-red-900/20 border-b dark:border-gray-700">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Active MCAs</p>
                                    <p id="combined-mca-active-count" class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $combinedMca['total_mca_count'] }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-purple-200 dark:border-purple-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Funding Received</p>
                                    <p id="combined-mca-funding-amount" class="text-2xl font-bold text-purple-600 dark:text-purple-400">${{ number_format($combinedMca['total_funding_amount'], 2) }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Payments Made</p>
                                    <p id="combined-mca-payment-count" class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $combinedMca['total_mca_payments'] }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Amount Paid</p>
                                    <p id="combined-mca-total-amount" class="text-2xl font-bold text-red-600 dark:text-red-400">${{ number_format($combinedMca['total_mca_amount'], 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- MCA Debt-to-Revenue Ratio - Key Underwriting Metric -->
                        @php
                            $debtToRevenueRatio = $combinedTotals['true_revenue'] > 0
                                ? ($combinedMca['total_funding_amount'] / $combinedTotals['true_revenue']) * 100
                                : 0;
                            $paymentToRevenueRatio = $combinedTotals['true_revenue'] > 0
                                ? ($combinedMca['total_mca_amount'] / $combinedTotals['true_revenue']) * 100
                                : 0;

                            // Determine risk level for debt-to-revenue
                            if ($debtToRevenueRatio < 25) {
                                $debtRiskLevel = 'low';
                                $debtRiskColor = 'green';
                                $debtRiskLabel = 'Low Risk';
                                $debtRiskDescription = 'Good position for new MCA';
                            } elseif ($debtToRevenueRatio < 50) {
                                $debtRiskLevel = 'moderate';
                                $debtRiskColor = 'yellow';
                                $debtRiskLabel = 'Moderate Risk';
                                $debtRiskDescription = 'Caution - existing debt load';
                            } elseif ($debtToRevenueRatio < 75) {
                                $debtRiskLevel = 'high';
                                $debtRiskColor = 'orange';
                                $debtRiskLabel = 'High Risk';
                                $debtRiskDescription = 'Significant existing debt';
                            } else {
                                $debtRiskLevel = 'very_high';
                                $debtRiskColor = 'red';
                                $debtRiskLabel = 'Very High Risk';
                                $debtRiskDescription = 'Heavily leveraged merchant';
                            }

                            // Determine risk level for payment burden
                            if ($paymentToRevenueRatio < 10) {
                                $paymentRiskColor = 'green';
                                $paymentRiskLabel = 'Manageable';
                            } elseif ($paymentToRevenueRatio < 20) {
                                $paymentRiskColor = 'yellow';
                                $paymentRiskLabel = 'Moderate Burden';
                            } elseif ($paymentToRevenueRatio < 30) {
                                $paymentRiskColor = 'orange';
                                $paymentRiskLabel = 'Heavy Burden';
                            } else {
                                $paymentRiskColor = 'red';
                                $paymentRiskLabel = 'Severe Burden';
                            }
                        @endphp

                        <div class="p-4 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 border-b dark:border-gray-700">
                            <div class="flex items-center mb-3">
                                <svg class="w-5 h-5 text-gray-700 dark:text-gray-300 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <h5 class="font-semibold text-gray-800 dark:text-gray-200">Underwriting Metrics</h5>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Debt-to-Revenue Ratio Card -->
                                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 border-{{ $debtRiskColor }}-400 dark:border-{{ $debtRiskColor }}-600 shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">MCA Debt-to-Revenue Ratio</span>
                                        <span class="px-2 py-1 rounded-full text-xs font-bold
                                            @if($debtRiskColor === 'green') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif($debtRiskColor === 'yellow') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @elseif($debtRiskColor === 'orange') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                            @endif">
                                            {{ $debtRiskLabel }}
                                        </span>
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <span id="combined-debt-ratio" class="text-4xl font-bold
                                            @if($debtRiskColor === 'green') text-green-600 dark:text-green-400
                                            @elseif($debtRiskColor === 'yellow') text-yellow-600 dark:text-yellow-400
                                            @elseif($debtRiskColor === 'orange') text-orange-600 dark:text-orange-400
                                            @else text-red-600 dark:text-red-400
                                            @endif">
                                            {{ number_format($debtToRevenueRatio, 1) }}%
                                        </span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400 mb-1">of revenue</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $debtRiskDescription }}</p>
                                    <div class="mt-3 bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500
                                            @if($debtRiskColor === 'green') bg-green-500
                                            @elseif($debtRiskColor === 'yellow') bg-yellow-500
                                            @elseif($debtRiskColor === 'orange') bg-orange-500
                                            @else bg-red-500
                                            @endif"
                                            style="width: {{ min($debtToRevenueRatio, 100) }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                                        <span>0%</span>
                                        <span>25%</span>
                                        <span>50%</span>
                                        <span>75%</span>
                                        <span>100%+</span>
                                    </div>
                                </div>

                                <!-- Payment Burden Card -->
                                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 border-{{ $paymentRiskColor }}-400 dark:border-{{ $paymentRiskColor }}-600 shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">MCA Payment Burden</span>
                                        <span class="px-2 py-1 rounded-full text-xs font-bold
                                            @if($paymentRiskColor === 'green') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif($paymentRiskColor === 'yellow') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @elseif($paymentRiskColor === 'orange') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                            @endif">
                                            {{ $paymentRiskLabel }}
                                        </span>
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <span id="combined-payment-ratio" class="text-4xl font-bold
                                            @if($paymentRiskColor === 'green') text-green-600 dark:text-green-400
                                            @elseif($paymentRiskColor === 'yellow') text-yellow-600 dark:text-yellow-400
                                            @elseif($paymentRiskColor === 'orange') text-orange-600 dark:text-orange-400
                                            @else text-red-600 dark:text-red-400
                                            @endif">
                                            {{ number_format($paymentToRevenueRatio, 1) }}%
                                        </span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400 mb-1">of revenue to MCA</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        ${{ number_format($combinedMca['total_mca_amount'], 0) }} paid out of ${{ number_format($combinedTotals['true_revenue'], 0) }} revenue
                                    </p>
                                    <div class="mt-3 bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500
                                            @if($paymentRiskColor === 'green') bg-green-500
                                            @elseif($paymentRiskColor === 'yellow') bg-yellow-500
                                            @elseif($paymentRiskColor === 'orange') bg-orange-500
                                            @else bg-red-500
                                            @endif"
                                            style="width: {{ min($paymentToRevenueRatio * 2, 100) }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                                        <span>0%</span>
                                        <span>10%</span>
                                        <span>20%</span>
                                        <span>30%</span>
                                        <span>50%+</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Decision Guide -->
                            <div class="mt-4 p-3 rounded-lg
                                @if($debtRiskLevel === 'low') bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800
                                @elseif($debtRiskLevel === 'moderate') bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800
                                @elseif($debtRiskLevel === 'high') bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800
                                @else bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800
                                @endif">
                                <div class="flex items-start">
                                    @if($debtRiskLevel === 'low')
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-green-800 dark:text-green-200">Recommended for New MCA</p>
                                            <p class="text-sm text-green-700 dark:text-green-300">Low existing debt load. Merchant has capacity for additional funding.</p>
                                        </div>
                                    @elseif($debtRiskLevel === 'moderate')
                                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-yellow-800 dark:text-yellow-200">Proceed with Caution</p>
                                            <p class="text-sm text-yellow-700 dark:text-yellow-300">Moderate debt level. Consider smaller funding amount or verify existing MCA payoff dates.</p>
                                        </div>
                                    @elseif($debtRiskLevel === 'high')
                                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-orange-800 dark:text-orange-200">High Risk - Additional Review Required</p>
                                            <p class="text-sm text-orange-700 dark:text-orange-300">Significant existing MCA debt. Requires manager approval and careful underwriting.</p>
                                        </div>
                                    @else
                                        <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-red-800 dark:text-red-200">Not Recommended</p>
                                            <p class="text-sm text-red-700 dark:text-red-300">Merchant is heavily stacked with MCA debt. High risk of default. Consider decline or consolidation offer only.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Lender</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase">Funding Received</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Frequency</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase"># Payments</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Avg Payment</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-red-600 dark:text-red-400 uppercase">Total Paid</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="combined-mca-lenders-tbody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($combinedMca['lenders'] as $lender)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 mca-lender-row" data-lender-id="{{ $lender['lender_id'] }}">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mr-3">
                                                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <p class="font-medium text-gray-900 dark:text-gray-100 lender-name">{{ $lender['lender_name'] }}</p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold lender-total-funding {{ ($lender['total_funding'] ?? 0) > 0 ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400 dark:text-gray-500' }}">
                                            @if(($lender['total_funding'] ?? 0) > 0)
                                                ${{ number_format($lender['total_funding'], 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @php
                                                $freqColors = [
                                                    'daily' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'every_other_day' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'twice_weekly' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                                    'weekly' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    'bi_weekly' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    'monthly' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'single_payment' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                    'no_payments' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                    'irregular' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                ];
                                                $freqColor = $freqColors[$lender['frequency'] ?? 'irregular'] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $freqColor }}">
                                                {{ $lender['frequency_label'] ?? 'Unknown' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm font-medium text-gray-900 dark:text-gray-100 lender-payment-count">{{ $lender['payment_count'] ?? 0 }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300 lender-avg-payment">${{ number_format(($lender['payment_count'] ?? 0) > 0 ? ($lender['total_amount'] ?? 0) / $lender['payment_count'] : 0, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold text-red-600 dark:text-red-400 lender-total-amount">${{ number_format($lender['total_amount'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                onclick="toggleMcaTransactions('{{ $lender['lender_id'] }}', '{{ addslashes($lender['lender_name']) }}')"
                                                class="mca-view-btn inline-flex items-center justify-center p-2 rounded-lg text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30 transition-colors"
                                                title="View transactions for {{ $lender['lender_name'] }}"
                                                data-lender-id="{{ $lender['lender_id'] }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100 dark:bg-gray-700">
                                    <tr class="font-semibold">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                                        <td id="combined-mca-footer-funding" class="px-4 py-3 text-right text-sm text-purple-600 dark:text-purple-400">${{ number_format($combinedMca['total_funding_amount'], 2) }}</td>
                                        <td class="px-4 py-3"></td>
                                        <td id="combined-mca-footer-payments" class="px-4 py-3 text-center text-sm text-gray-900 dark:text-gray-100">{{ $combinedMca['total_mca_payments'] }}</td>
                                        <td class="px-4 py-3"></td>
                                        <td id="combined-mca-footer-amount" class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400">${{ number_format($combinedMca['total_mca_amount'], 2) }}</td>
                                        <td class="px-4 py-3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border-t dark:border-gray-700">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">MCA Obligations Summary</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                        This merchant has {{ $combinedMca['total_mca_count'] }} existing MCA {{ Str::plural('obligation', $combinedMca['total_mca_count']) }}.
                                        @if($combinedMca['total_funding_amount'] > 0)
                                            Total funding received: <strong>${{ number_format($combinedMca['total_funding_amount'], 2) }}</strong>.
                                        @endif
                                        Total payments made: <strong>${{ number_format($combinedMca['total_mca_amount'], 2) }}</strong>.
                                        @if($combinedMca['total_mca_count'] >= 3)
                                            <span class="text-red-700 dark:text-red-300"><strong>High stacking risk detected.</strong></span> Consider the impact on cash flow before approving additional funding.
                                        @elseif($combinedMca['total_mca_count'] >= 2)
                                            <span class="text-orange-700 dark:text-orange-300"><strong>Moderate stacking detected.</strong></span> Review payment schedules to assess cash flow capacity.
                                        @else
                                            Review the payment frequency and amount to assess remaining cash flow capacity.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MCA Offer Calculator Section -->
            <div class="mb-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-2 border-blue-500">
                <div class="p-2">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">MCA Offer Calculator</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Calculate funding offers based on True Revenue analysis</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="loadSavedOffers()" class="inline-flex items-center px-3 py-1.5 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                Load Saved
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Input Section -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 pb-2">Revenue & Withhold Settings</h4>

                            <!-- True Revenue (Read-Only from Analysis) -->
                            <div class="bg-green-50 dark:bg-green-900/30 p-4 rounded-lg border border-green-200 dark:border-green-800">
                                <label class="block text-sm font-medium text-green-700 dark:text-green-300 mb-1">
                                    True Revenue (Monthly Average)
                                    <span class="text-xs font-normal text-green-600 dark:text-green-400 ml-1">- from analysis</span>
                                </label>
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400" id="mca-calc-true-revenue">
                                    ${{ number_format($combinedAverages['true_revenue'], 2) }}
                                </div>
                                <input type="hidden" id="mca-calc-true-revenue-value" value="{{ $combinedAverages['true_revenue'] }}">

                                <!-- Manual Override Toggle -->
                                <div class="mt-3 flex items-center">
                                    <input type="checkbox" id="mca-calc-override-toggle" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" onchange="toggleRevenueOverride()">
                                    <label for="mca-calc-override-toggle" class="ml-2 text-sm text-gray-600 dark:text-gray-400">Use manual override</label>
                                </div>
                                <div id="mca-calc-override-input" class="mt-2 hidden">
                                    <input type="number" id="mca-calc-override-value" class="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2" placeholder="Enter manual revenue" step="0.01" min="0" onchange="recalculateOffer()">
                                </div>
                            </div>

                            <!-- Existing MCA Payment -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Existing MCA Payment (Monthly)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">$</span>
                                    <input type="number" id="mca-calc-existing-payment" class="w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg" value="{{ $combinedMca['total_mca_amount'] > 0 ? number_format($combinedMca['total_mca_amount'] / max(1, $monthCount), 2, '.', '') : '0' }}" step="0.01" min="0" onchange="recalculateOffer()">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Current monthly MCA obligations detected from statements</p>
                            </div>

                            <!-- Withhold Percentage -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Withhold Percentage</label>
                                <div class="flex items-center gap-2" id="withhold-slider-container">
                                    <input type="range" id="mca-calc-withhold-slider" min="5" max="25" value="20" class="flex-1" onchange="updateWithholdPercent(this.value)">
                                    <div class="flex items-center">
                                        <input type="number" id="mca-calc-withhold-percent" class="w-16 text-center border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1" value="20" step="1" min="5" max="25" onchange="updateWithholdSlider(this.value)">
                                        <span class="ml-1 text-gray-600 dark:text-gray-400">%</span>
                                    </div>
                                </div>

                                <!-- Manual Override Toggle -->
                                <div class="mt-3 flex items-center">
                                    <input type="checkbox" id="mca-calc-withhold-override-toggle" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" onchange="toggleWithholdOverride()">
                                    <label for="mca-calc-withhold-override-toggle" class="ml-2 text-sm text-gray-600 dark:text-gray-400">Use custom withhold percentage</label>
                                </div>
                                <div id="mca-calc-withhold-override-input" class="mt-2 hidden">
                                    <div class="flex items-center gap-2">
                                        <input type="number" id="mca-calc-withhold-override-value" class="flex-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2" placeholder="Enter custom %" step="0.1" min="0" max="100" onchange="recalculateWithWithholdOverride()">
                                        <span class="text-gray-600 dark:text-gray-400">%</span>
                                    </div>
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">Enter any percentage (0-100%) to override standard range</p>
                                </div>
                            </div>

                            <!-- Calculated Cap Amount -->
                            <div class="bg-blue-50 dark:bg-blue-900/30 p-3 rounded-lg border border-blue-200 dark:border-blue-800">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-700 dark:text-blue-300">Cap Amount (Revenue × Withhold%)</span>
                                    <span class="font-bold text-blue-600 dark:text-blue-400" id="mca-calc-cap-amount">$0.00</span>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-sm text-blue-700 dark:text-blue-300">New Payment Available</span>
                                    <span class="font-bold text-blue-600 dark:text-blue-400" id="mca-calc-new-payment">$0.00</span>
                                </div>
                            </div>

                            <h4 class="font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 pb-2 pt-4">Offer Terms</h4>

                            <!-- Factor Rate -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Factor Rate</label>
                                <div class="flex items-center gap-2">
                                    <input type="range" id="mca-calc-factor-slider" min="1.10" max="1.60" step="0.01" value="1.30" class="flex-1" onchange="updateFactorRate(this.value)">
                                    <input type="number" id="mca-calc-factor-rate" class="w-20 text-center border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1" value="1.30" step="0.01" min="1.10" max="1.60" onchange="updateFactorSlider(this.value)">
                                </div>
                            </div>

                            <!-- Term Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Frequency</label>

                                <!-- Radio Buttons for Payment Frequency -->
                                <div class="grid grid-cols-2 gap-2 mb-3">
                                    <label class="flex items-center p-2 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <input type="radio" name="term-type" value="daily" class="mr-2 text-blue-600 focus:ring-blue-500" onchange="switchTermType('daily')">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Daily</span>
                                    </label>
                                    <label class="flex items-center p-2 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <input type="radio" name="term-type" value="weekly" class="mr-2 text-blue-600 focus:ring-blue-500" onchange="switchTermType('weekly')">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Weekly</span>
                                    </label>
                                    <label class="flex items-center p-2 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <input type="radio" name="term-type" value="biweekly" class="mr-2 text-blue-600 focus:ring-blue-500" onchange="switchTermType('biweekly')">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Bi-Weekly</span>
                                    </label>
                                    <label class="flex items-center p-2 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition bg-blue-50 dark:bg-blue-900/30 border-blue-500">
                                        <input type="radio" name="term-type" value="monthly" class="mr-2 text-blue-600 focus:ring-blue-500" onchange="switchTermType('monthly')" checked>
                                        <span class="text-sm font-medium text-blue-700 dark:text-blue-300">Monthly</span>
                                    </label>
                                </div>

                                <!-- Dropdown for Daily -->
                                <div id="term-dropdown-daily" class="hidden">
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Number of Daily Payments</label>
                                    <select id="mca-calc-term-daily" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2" onchange="recalculateOffer()">
                                        <option value="60">60 Payments (~3 months)</option>
                                        <option value="90">90 Payments (~4 months)</option>
                                        <option value="120">120 Payments (~6 months)</option>
                                        <option value="150">150 Payments (~7 months)</option>
                                        <option value="180" selected>180 Payments (~9 months)</option>
                                        <option value="195">195 Payments (~9.5 months)</option>
                                        <option value="210">210 Payments (~10 months)</option>
                                        <option value="240">240 Payments (~12 months)</option>
                                        <option value="260">260 Payments (1 year)</option>
                                    </select>
                                </div>

                                <!-- Dropdown for Weekly -->
                                <div id="term-dropdown-weekly" class="hidden">
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Number of Weekly Payments</label>
                                    <select id="mca-calc-term-weekly" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2" onchange="recalculateOffer()">
                                        <option value="12">12 Payments (~3 months)</option>
                                        <option value="16">16 Payments (~4 months)</option>
                                        <option value="20">20 Payments (~5 months)</option>
                                        <option value="24">24 Payments (~6 months)</option>
                                        <option value="28">28 Payments (~7 months)</option>
                                        <option value="32">32 Payments (~8 months)</option>
                                        <option value="36" selected>36 Payments (~9 months)</option>
                                        <option value="40">40 Payments (~10 months)</option>
                                        <option value="44">44 Payments (~11 months)</option>
                                        <option value="48">48 Payments (~12 months)</option>
                                        <option value="52">52 Payments (1 year)</option>
                                    </select>
                                </div>

                                <!-- Dropdown for Bi-Weekly -->
                                <div id="term-dropdown-biweekly" class="hidden">
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Number of Bi-Weekly Payments</label>
                                    <select id="mca-calc-term-biweekly" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2" onchange="recalculateOffer()">
                                        <option value="6">6 Payments (~3 months)</option>
                                        <option value="8">8 Payments (~4 months)</option>
                                        <option value="10">10 Payments (~5 months)</option>
                                        <option value="12">12 Payments (~6 months)</option>
                                        <option value="14">14 Payments (~7 months)</option>
                                        <option value="16">16 Payments (~8 months)</option>
                                        <option value="18" selected>18 Payments (~9 months)</option>
                                        <option value="20">20 Payments (~10 months)</option>
                                        <option value="22">22 Payments (~11 months)</option>
                                        <option value="24">24 Payments (~12 months)</option>
                                        <option value="26">26 Payments (1 year)</option>
                                    </select>
                                </div>

                                <!-- Dropdown for Monthly -->
                                <div id="term-dropdown-monthly">
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Number of Monthly Payments</label>
                                    <select id="mca-calc-term-monthly" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2" onchange="recalculateOffer()">
                                        @for($i = 1; $i <= 24; $i++)
                                        <option value="{{ $i }}" {{ $i === 9 ? 'selected' : '' }}>{{ $i }} {{ $i === 1 ? 'Payment' : 'Payments' }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Results Section -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 pb-2">Offer Summary</h4>

                            <!-- Funded Amount (based on withhold constraint) -->
                            <div class="bg-purple-50 dark:bg-purple-900/30 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                                <label class="block text-sm font-medium text-purple-700 dark:text-purple-300 mb-1">Funded Amount</label>
                                <p class="text-xs text-purple-600 dark:text-purple-400 mb-2" id="funded-amount-subtitle">Based on withhold constraint</p>
                                <div class="text-3xl font-bold text-purple-600 dark:text-purple-400" id="mca-calc-funded-amount">$0.00</div>

                                <!-- Manual Override Toggle -->
                                <div class="mt-3 flex items-center">
                                    <input type="checkbox" id="mca-calc-funded-override-toggle" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500" onchange="toggleFundedOverride()">
                                    <label for="mca-calc-funded-override-toggle" class="ml-2 text-sm text-purple-700 dark:text-purple-300">Manual override</label>
                                </div>
                                <div id="mca-calc-funded-override-input" class="mt-2 hidden">
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" id="mca-calc-funded-override-value" class="w-full pl-8 pr-3 py-2 text-sm border border-purple-300 dark:border-purple-600 dark:bg-gray-700 dark:text-white rounded" placeholder="Enter funded amount" step="0.01" min="0" onchange="recalculateWithFundedOverride()">
                                    </div>
                                    <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">Enter custom funded amount to recalculate utilization</p>
                                </div>
                            </div>

                            <!-- Calculated Results -->
                            <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Factor Rate</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100" id="mca-calc-result-factor">1.30</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Term</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100" id="mca-calc-result-term">9 months</span>
                                </div>
                                <div class="flex justify-between items-center border-t border-gray-200 dark:border-gray-600 pt-3">
                                    <span class="text-gray-600 dark:text-gray-400">Total Payback</span>
                                    <span class="font-bold text-lg text-gray-900 dark:text-gray-100" id="mca-calc-result-payback">$0.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Monthly Payment</span>
                                    <span class="font-bold text-lg text-blue-600 dark:text-blue-400" id="mca-calc-result-monthly">$0.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Weekly Payment</span>
                                    <span class="font-semibold text-green-600 dark:text-green-400" id="mca-calc-result-weekly">$0.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Daily Payment</span>
                                    <span class="font-semibold text-purple-600 dark:text-purple-400" id="mca-calc-result-daily">$0.00</span>
                                </div>
                            </div>

                            <!-- Payment Warning -->
                            <div id="mca-calc-warning" class="hidden bg-yellow-50 dark:bg-yellow-900/30 p-3 rounded-lg border border-yellow-200 dark:border-yellow-800">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300" id="mca-calc-warning-text"></p>
                                </div>
                            </div>

                            <!-- Withhold Utilization -->
                            <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Withhold Utilization</span>
                                    <span class="font-semibold" id="mca-calc-utilization-text">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-3 overflow-hidden">
                                    <div id="mca-calc-utilization-bar" class="h-full bg-green-500 rounded-full transition-all duration-300" style="width: 0%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-400 mt-1">
                                    <span>0%</span>
                                    <span>50%</span>
                                    <span>100%</span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-4">
                                <button onclick="saveCurrentOffer()" class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                                    </svg>
                                    Save Offer
                                </button>
                                <button onclick="resetCalculator()" class="px-4 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Saved Offers Section -->
                    <div id="mca-saved-offers-section" class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6 hidden">
                        <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                            </svg>
                            Saved Offers
                            <span class="ml-2 px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 text-xs rounded-full" id="saved-offers-count">0</span>
                        </h4>
                        <div id="mca-saved-offers-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Saved offers will be populated here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Distribution Chart Section -->
            <div class="mb-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-2 border-purple-500">
                <div class="p-2">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Category Distribution</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Breakdown of transactions by category across all statements</p>
                            </div>
                        </div>
                        <!-- View Mode Toggle -->
                        <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                            <button onclick="toggleCategoryView('all')" id="category-all-btn" class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 bg-white dark:bg-gray-800 text-purple-600 dark:text-purple-400 shadow-sm">
                                All
                            </button>
                            <button onclick="toggleCategoryView('credit')" id="category-credit-btn" class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                    </svg>
                                    Credits
                                </div>
                            </button>
                            <button onclick="toggleCategoryView('debit')" id="category-debit-btn" class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                    </svg>
                                    Debits
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Pie Chart -->
                        <div class="flex items-center justify-center">
                            <div class="w-full max-w-md">
                                <canvas id="categoryPieChart"></canvas>
                            </div>
                        </div>

                        <!-- Category Stats Table -->
                        <div class="overflow-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Category</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Count</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">%</th>
                                    </tr>
                                </thead>
                                <tbody id="categoryStatsTable" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <!-- Stats will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Individual Files Section Header -->
            <div class="mb-4 mt-8">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Individual File Details
                    <span class="ml-2 text-sm font-normal text-gray-500">(Click "View Details" to edit transactions)</span>
                </h3>
            </div>
            @endif

            @foreach($results as $result)
            <div class="mb-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-2">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            @if($result['success'])
                            <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            @else
                            <div class="p-2 bg-red-100 dark:bg-red-900 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            @endif
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $result['filename'] }}</h3>
                                @if($result['success'])
                                <p class="text-sm text-gray-500 dark:text-gray-400">Successfully analyzed</p>
                                @else
                                <p class="text-sm text-red-500">Failed: {{ $result['error'] ?? 'Unknown error' }}</p>
                                @endif
                            </div>
                        </div>
                        @if($result['success'])
                        <div class="flex items-center gap-4">
                            <a href="{{ route('bankstatement.session', ['sessionId' => $result['session_id'], 'related' => implode(',', $allSessionIds)]) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                View Details
                            </a>
                            <a href="{{ route('bankstatement.download', $result['session_id']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                Download CSV
                            </a>
                        </div>
                        @endif
                    </div>

                    @if($result['success'])
                    <!-- Monthly Breakdown Table (FCS Style) -->
                    @if(isset($result['monthly_data']) && count($result['monthly_data']['months']) > 0)
                    <div class="mb-6 border dark:border-gray-700 rounded-lg overflow-hidden" id="monthly-table-{{ $result['session_id'] }}">
                        <div class="bg-green-600 px-4 py-3">
                            <h4 class="font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                Monthly Bank Details
                                <span class="ml-2 text-xs font-normal opacity-75">(Click on revenue classifications to train AI)</span>
                            </h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Month</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Monthly Deposits</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Adjustments</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-green-600 dark:text-green-400 uppercase">True Revenue</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Avg Daily</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">NSF</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Negative Days</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase"># Deposits</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Total Debits</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($result['monthly_data']['months'] as $month)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700" id="month-row-{{ $result['session_id'] }}-{{ $month['month_key'] }}">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $month['month_name'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">${{ number_format($month['deposits'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-orange-600 dark:text-orange-400 month-adj-{{ $result['session_id'] }}-{{ $month['month_key'] }}">
                                            $<span class="adj-value">{{ number_format($month['adjustments'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-green-600 dark:text-green-400 month-rev-{{ $result['session_id'] }}-{{ $month['month_key'] }}">
                                            $<span class="rev-value">{{ number_format($month['true_revenue'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300 month-avg-{{ $result['session_id'] }}-{{ $month['month_key'] }}">
                                            $<span class="avg-value">{{ number_format($month['average_daily'] ?? $month['average_daily_revenue'] ?? 0, 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center {{ $month['nsf_count'] > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">{{ $month['nsf_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center {{ ($month['negative_days'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">{{ $month['negative_days'] ?? 0 }}</td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $month['deposit_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400">${{ number_format($month['debits'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100 dark:bg-gray-700">
                                    <tr class="font-semibold" id="total-row-{{ $result['session_id'] }}">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">${{ number_format($result['monthly_data']['totals']['deposits'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-orange-600 dark:text-orange-400 total-adj-{{ $result['session_id'] }}">
                                            $<span class="adj-value">{{ number_format($result['monthly_data']['totals']['adjustments'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-green-600 dark:text-green-400 total-rev-{{ $result['session_id'] }}">
                                            $<span class="rev-value">{{ number_format($result['monthly_data']['totals']['true_revenue'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100 total-avg-{{ $result['session_id'] }}">
                                            $<span class="avg-value">{{ number_format($result['monthly_data']['totals']['average_daily'] ?? $result['monthly_data']['totals']['average_daily_revenue'] ?? 0, 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center {{ $result['monthly_data']['totals']['nsf_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $result['monthly_data']['totals']['nsf_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center {{ ($result['monthly_data']['totals']['negative_days'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $result['monthly_data']['totals']['negative_days'] ?? 0 }}</td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">{{ $result['monthly_data']['totals']['deposit_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400">${{ number_format($result['monthly_data']['totals']['debits'], 2) }}</td>
                                    </tr>
                                    <tr class="text-gray-600 dark:text-gray-400" id="avg-row-{{ $result['session_id'] }}">
                                        <td class="px-4 py-3 text-sm">Average</td>
                                        <td class="px-4 py-3 text-sm text-right">${{ number_format($result['monthly_data']['averages']['deposits'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right avg-adj-{{ $result['session_id'] }}">
                                            $<span class="adj-value">{{ number_format($result['monthly_data']['averages']['adjustments'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right avg-rev-{{ $result['session_id'] }}">
                                            $<span class="rev-value">{{ number_format($result['monthly_data']['averages']['true_revenue'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right avg-avg-{{ $result['session_id'] }}">
                                            $<span class="avg-value">{{ number_format($result['monthly_data']['averages']['average_daily'] ?? $result['monthly_data']['averages']['average_daily_revenue'] ?? 0, 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center">-</td>
                                        <td class="px-4 py-3 text-sm text-center">-</td>
                                        <td class="px-4 py-3 text-sm text-center">{{ number_format($result['monthly_data']['averages']['deposit_count'], 1) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">${{ number_format($result['monthly_data']['averages']['debits'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    @endif

                    <!-- MCA Detection Section -->
                    @if(isset($result['mca_analysis']) && $result['mca_analysis']['total_mca_count'] > 0)
                    <div class="mb-6 border dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="bg-red-600 px-4 py-3">
                            <h4 class="font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                Existing MCA Obligations Detected
                                <span class="ml-2 px-2 py-0.5 bg-white/20 rounded text-sm">{{ $result['mca_analysis']['total_mca_count'] }} {{ Str::plural('Lender', $result['mca_analysis']['total_mca_count']) }}</span>
                            </h4>
                        </div>

                        <!-- MCA Summary Cards -->
                        <div class="p-4 bg-red-50 dark:bg-red-900/20 border-b dark:border-gray-700">
                            <div class="grid grid-cols-3 gap-4">
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Active MCAs</p>
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $result['mca_analysis']['total_mca_count'] }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Payments</p>
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $result['mca_analysis']['total_mca_payments'] }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total MCA Amount</p>
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">${{ number_format($result['mca_analysis']['total_mca_amount'], 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- MCA Lenders Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Lender</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Frequency</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase"># Payments</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Avg Payment</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Total Paid</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Date Range</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">View</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($result['mca_analysis']['lenders'] as $lenderIndex => $lender)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mr-3">
                                                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $lender['lender_name'] }}</p>
                                                    @if(count($lender['unique_amounts']) > 1)
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Varying amounts: ${{ implode(', $', array_map(fn($a) => number_format($a, 2), array_slice($lender['unique_amounts'], 0, 3))) }}{{ count($lender['unique_amounts']) > 3 ? '...' : '' }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @php
                                                $freqColors = [
                                                    'daily' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'every_other_day' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'twice_weekly' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                                    'weekly' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    'bi_weekly' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    'monthly' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'single_payment' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                    'irregular' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                ];
                                                $freqColor = $freqColors[$lender['frequency']] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $freqColor }}">
                                                {{ $lender['frequency_label'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm font-medium text-gray-900 dark:text-gray-100">{{ $lender['payment_count'] }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">${{ number_format($lender['average_payment'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold text-red-600 dark:text-red-400">${{ number_format($lender['total_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">
                                            @if($lender['first_payment'] && $lender['last_payment'])
                                                @if($lender['first_payment']['date'] === $lender['last_payment']['date'])
                                                    {{ $lender['first_payment']['date'] }}
                                                @else
                                                    {{ $lender['first_payment']['date'] }} to {{ $lender['last_payment']['date'] }}
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button onclick="toggleMcaPaymentRow('individual-{{ $result['session_id'] }}-{{ $lenderIndex }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                    @if(isset($lender['payments']) && count($lender['payments']) > 0)
                                    <tr id="individual-{{ $result['session_id'] }}-{{ $lenderIndex }}" class="hidden bg-gray-50 dark:bg-gray-900">
                                        <td colspan="7" class="px-4 py-3">
                                            <div class="max-h-96 overflow-y-auto">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                    <thead class="bg-gray-100 dark:bg-gray-800">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Date</th>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Description</th>
                                                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-300">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                        @foreach($lender['payments'] as $payment)
                                                        <tr>
                                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $payment['date'] }}</td>
                                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $payment['description'] }}</td>
                                                            <td class="px-3 py-2 text-sm text-right text-red-600 dark:text-red-400">${{ number_format($payment['amount'], 2) }}</td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100 dark:bg-gray-700">
                                    <tr class="font-semibold">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                                        <td class="px-4 py-3"></td>
                                        <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-gray-100">{{ $result['mca_analysis']['total_mca_payments'] }}</td>
                                        <td class="px-4 py-3"></td>
                                        <td class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400">${{ number_format($result['mca_analysis']['total_mca_amount'], 2) }}</td>
                                        <td class="px-4 py-3"></td>
                                        <td class="px-4 py-3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- MCA Risk Warning -->
                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border-t dark:border-gray-700">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">MCA Stacking Alert</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                        This merchant has {{ $result['mca_analysis']['total_mca_count'] }} existing MCA {{ Str::plural('obligation', $result['mca_analysis']['total_mca_count']) }}.
                                        @if($result['mca_analysis']['total_mca_count'] >= 3)
                                            <strong>High stacking risk detected.</strong> Consider the impact on cash flow before approving additional funding.
                                        @elseif($result['mca_analysis']['total_mca_count'] >= 2)
                                            <strong>Moderate stacking detected.</strong> Review payment schedules to assess cash flow capacity.
                                        @else
                                            Review the payment frequency and amount to assess remaining cash flow capacity.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Validation Warnings -->
                    @if(isset($result['validation']) && $result['validation']['has_warnings'])
                    <div class="mb-6 border border-orange-300 dark:border-orange-700 rounded-lg overflow-hidden">
                        <div class="bg-orange-50 dark:bg-orange-900/30 px-4 py-3 border-b border-orange-200 dark:border-orange-800">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <h4 class="font-semibold text-orange-900 dark:text-orange-200">Validation Warnings</h4>
                                <span class="ml-auto text-xs text-orange-700 dark:text-orange-300">
                                    {{ count($result['validation']['warnings']) }} {{ Str::plural('issue', count($result['validation']['warnings'])) }} detected
                                </span>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-4 space-y-3">
                            @foreach($result['validation']['warnings'] as $warning)
                            <div class="flex items-start gap-3 p-3 rounded-lg {{ $warning['severity'] === 'error' ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' : 'bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800' }}">
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($warning['severity'] === 'error')
                                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    @else
                                    <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium {{ $warning['severity'] === 'error' ? 'text-red-900 dark:text-red-200' : 'text-orange-900 dark:text-orange-200' }}">
                                        {{ $warning['message'] }}
                                    </p>
                                    <div class="mt-2 grid grid-cols-3 gap-3 text-xs">
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">Expected:</span>
                                            <span class="ml-1 font-semibold {{ $warning['severity'] === 'error' ? 'text-red-700 dark:text-red-300' : 'text-orange-700 dark:text-orange-300' }}">
                                                ${{ number_format($warning['expected'], 2) }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">Extracted:</span>
                                            <span class="ml-1 font-semibold {{ $warning['severity'] === 'error' ? 'text-red-700 dark:text-red-300' : 'text-orange-700 dark:text-orange-300' }}">
                                                ${{ number_format($warning['extracted'], 2) }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">Difference:</span>
                                            <span class="ml-1 font-semibold {{ $warning['severity'] === 'error' ? 'text-red-700 dark:text-red-300' : 'text-orange-700 dark:text-orange-300' }}">
                                                ${{ number_format($warning['difference'], 2) }}
                                            </span>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs {{ $warning['severity'] === 'error' ? 'text-red-700 dark:text-red-300' : 'text-orange-700 dark:text-orange-300' }}">
                                        💡 {{ $warning['suggestion'] }}
                                    </p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Summary Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6" data-session-id="{{ $result['session_id'] }}">
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Transactions</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $result['summary']['total_transactions'] }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg {{ isset($result['validation']['warnings']) && collect($result['validation']['warnings'])->contains('type', 'credit_mismatch') ? 'ring-2 ring-orange-400 dark:ring-orange-600' : '' }}">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Deposits</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400" data-total="credits">${{ number_format($result['summary']['credit_total'], 2) }}</p>
                            <p class="text-xs text-gray-500">{{ $result['summary']['credit_count'] }} transactions</p>
                            @if(isset($result['validation']['expected_credits']) && $result['validation']['expected_credits'])
                                @php
                                    $creditDiff = abs($result['validation']['expected_credits'] - $result['summary']['credit_total']);
                                @endphp
                                @if($creditDiff > 1.00)
                                <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                                    Expected: ${{ number_format($result['validation']['expected_credits'], 2) }}
                                </p>
                                @else
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Validated
                                </p>
                                @endif
                            @endif
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 p-4 rounded-lg border-2 border-green-200 dark:border-green-800">
                            <p class="text-sm text-green-700 dark:text-green-300 font-medium">True Revenue</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400 summary-rev-{{ $result['session_id'] }}">
                                ${{ number_format($result['monthly_data']['totals']['true_revenue'] ?? $result['summary']['credit_total'], 2) }}
                            </p>
                            <p class="text-xs text-orange-600 dark:text-orange-400 summary-adj-{{ $result['session_id'] }}">
                                @if(isset($result['monthly_data']['totals']['adjustments']) && $result['monthly_data']['totals']['adjustments'] > 0)
                                -${{ number_format($result['monthly_data']['totals']['adjustments'], 2) }} adjustments
                                @endif
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg {{ isset($result['validation']['warnings']) && collect($result['validation']['warnings'])->contains('type', 'debit_mismatch') ? 'ring-2 ring-orange-400 dark:ring-orange-600' : '' }}">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Debits</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400" data-total="debits">${{ number_format($result['summary']['debit_total'], 2) }}</p>
                            <p class="text-xs text-gray-500">{{ $result['summary']['debit_count'] }} transactions</p>
                            @if(isset($result['validation']['expected_debits']) && $result['validation']['expected_debits'])
                                @php
                                    $debitDiff = abs($result['validation']['expected_debits'] - $result['summary']['debit_total']);
                                @endphp
                                @if($debitDiff > 1.00)
                                <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                                    Expected: ${{ number_format($result['validation']['expected_debits'], 2) }}
                                </p>
                                @else
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Validated
                                </p>
                                @endif
                            @endif
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400">API Cost</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($result['api_cost']['total_cost'], 4) }}</p>
                            <p class="text-xs text-gray-500">{{ number_format($result['api_cost']['total_tokens']) }} tokens</p>
                        </div>
                    </div>

                    <!-- Monthly Transaction Details (Collapsible) -->
                    @if(isset($result['monthly_data']) && count($result['monthly_data']['months']) > 0)
                    <div class="border dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b dark:border-gray-600">
                            <h4 class="font-medium text-gray-900 dark:text-gray-100">Transactions by Month</h4>
                        </div>

                        @foreach($result['monthly_data']['months'] as $monthIndex => $month)
                        <div x-data="{ open: {{ $monthIndex === 0 ? 'true' : 'false' }} }" class="border-b dark:border-gray-700 last:border-b-0">
                            <button @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                <div class="flex items-center gap-4">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $month['month_name'] }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">({{ count($month['transactions']) }} transactions)</span>
                                </div>
                                <div class="flex items-center gap-6">
                                    <div class="text-right">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">True Revenue: </span>
                                        <span class="text-sm font-semibold text-green-600 dark:text-green-400 header-rev-{{ $result['session_id'] }}-{{ $month['month_key'] }}">${{ number_format($month['true_revenue'], 2) }}</span>
                                    </div>
                                    <svg :class="{ 'rotate-180': open }" class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </button>

                            <div x-show="open" x-transition class="bg-white dark:bg-gray-800">
                                <!-- Month Summary Cards -->
                                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-750 grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div class="bg-white dark:bg-gray-700 p-3 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Deposits</p>
                                        <p class="text-lg font-semibold text-green-600 dark:text-green-400">${{ number_format($month['deposits'], 2) }}</p>
                                    </div>
                                    <div class="bg-white dark:bg-gray-700 p-3 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Adjustments</p>
                                        <p class="text-lg font-semibold text-orange-600 dark:text-orange-400 card-adj-{{ $result['session_id'] }}-{{ $month['month_key'] }}">${{ number_format($month['adjustments'], 2) }}</p>
                                    </div>
                                    <div class="bg-white dark:bg-gray-700 p-3 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">True Revenue</p>
                                        <p class="text-lg font-semibold text-green-600 dark:text-green-400 card-rev-{{ $result['session_id'] }}-{{ $month['month_key'] }}">${{ number_format($month['true_revenue'], 2) }}</p>
                                    </div>
                                    <div class="bg-white dark:bg-gray-700 p-3 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Debits</p>
                                        <p class="text-lg font-semibold text-red-600 dark:text-red-400">${{ number_format($month['debits'], 2) }}</p>
                                    </div>
                                </div>

                                <!-- Adjustment Items (if any) -->
                                <div id="adjustments-container-{{ $result['session_id'] }}-{{ $month['month_key'] }}" class="px-4 py-2 bg-orange-50 dark:bg-orange-900/20 border-t border-b border-orange-200 dark:border-orange-800 {{ count($month['adjustment_items']) > 0 ? '' : 'hidden' }}">
                                    <p class="text-xs font-medium text-orange-700 dark:text-orange-300 mb-2">Adjustments (excluded from True Revenue):</p>
                                    <div id="adjustments-list-{{ $result['session_id'] }}-{{ $month['month_key'] }}" class="space-y-1">
                                        @foreach($month['adjustment_items'] as $adj)
                                        <div class="flex justify-between text-xs adj-item gap-4" data-description="{{ $adj['description'] }}">
                                            <span class="text-orange-600 dark:text-orange-400 flex-1">{{ $adj['description'] }}</span>
                                            <span class="text-orange-700 dark:text-orange-300 font-medium whitespace-nowrap">${{ number_format($adj['amount'], 2) }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                @php
                                    // Group transactions by account number
                                    $accountGroups = ['unknown' => []];
                                    $accountSummaries = ['unknown' => ['credits' => 0, 'debits' => 0, 'credit_count' => 0, 'debit_count' => 0, 'true_revenue' => 0, 'adjustments' => 0]];

                                    foreach($month['transactions'] as $idx => $t) {
                                        $description = $t['description'];
                                        $acctNum = null;

                                        // Extract account number from description
                                        if (preg_match('/(?:ACCT|ACCOUNT|A\/C)[\s#:]*([X*]+)?(\d{4,})/i', $description, $m)) {
                                            $acctNum = $m[2];
                                        } elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $description, $m)) {
                                            $acctNum = $m[1];
                                        } elseif (preg_match('/[X*]{4,}(\d{4,})/', $description, $m)) {
                                            $acctNum = $m[1];
                                        }

                                        $key = $acctNum ?? 'unknown';

                                        if (!isset($accountGroups[$key])) {
                                            $accountGroups[$key] = [];
                                            $accountSummaries[$key] = ['credits' => 0, 'debits' => 0, 'credit_count' => 0, 'debit_count' => 0, 'true_revenue' => 0, 'adjustments' => 0];
                                        }

                                        $accountGroups[$key][] = $t;

                                        // Calculate summaries
                                        $amount = $t['amount'];
                                        if ($t['type'] === 'credit') {
                                            $accountSummaries[$key]['credits'] += $amount;
                                            $accountSummaries[$key]['credit_count']++;
                                            if (!($t['is_adjustment'] ?? false)) {
                                                $accountSummaries[$key]['true_revenue'] += $amount;
                                            } else {
                                                $accountSummaries[$key]['adjustments'] += $amount;
                                            }
                                        } else {
                                            $accountSummaries[$key]['debits'] += $amount;
                                            $accountSummaries[$key]['debit_count']++;
                                        }
                                    }

                                    $hasMultipleAccounts = count($accountGroups) > 1;
                                @endphp

                                <!-- Account Filter (if multiple accounts detected) -->
                                @if($hasMultipleAccounts)
                                <div class="px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border-t border-b border-blue-200 dark:border-blue-800">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                            </svg>
                                            <span class="text-sm font-medium text-blue-900 dark:text-blue-200">Multiple Accounts Detected ({{ count($accountGroups) }})</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button onclick="filterAccount_{{ $result['session_id'] }}_{{ $month['month_key'] }}('all')"
                                                class="account-filter-btn-{{ $result['session_id'] }}-{{ $month['month_key'] }} px-3 py-1.5 rounded-md text-sm font-medium transition bg-blue-600 text-white"
                                                data-account="all">
                                            All Accounts ({{ count($month['transactions']) }})
                                        </button>
                                        @foreach(array_keys($accountGroups) as $acct)
                                        @php
                                            $summary = $accountSummaries[$acct];
                                        @endphp
                                        <button onclick="filterAccount_{{ $result['session_id'] }}_{{ $month['month_key'] }}('{{ $acct }}')"
                                                class="account-filter-btn-{{ $result['session_id'] }}-{{ $month['month_key'] }} px-3 py-1.5 rounded-md text-sm font-medium transition bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-blue-300 dark:border-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/30"
                                                data-account="{{ $acct }}">
                                            @if($acct === 'unknown')
                                                <span>No Account # ({{ count($accountGroups[$acct]) }})</span>
                                            @else
                                                <span class="font-mono">****{{ $acct }}</span>
                                                <span class="ml-1 text-xs opacity-75">({{ count($accountGroups[$acct]) }})</span>
                                            @endif
                                        </button>
                                        @endforeach
                                    </div>

                                    <!-- Per-Account Summaries -->
                                    <div class="mt-3 space-y-2">
                                        @foreach(array_keys($accountGroups) as $acct)
                                        @if($acct !== 'unknown')
                                        @php
                                            $summary = $accountSummaries[$acct];
                                        @endphp
                                        <div class="account-summary-{{ $result['session_id'] }}-{{ $month['month_key'] }}-{{ $acct }} hidden bg-white dark:bg-gray-800 rounded-lg p-3 border border-blue-200 dark:border-blue-700">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-xs font-semibold text-blue-900 dark:text-blue-200">Account ****{{ $acct }}</span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $summary['credit_count'] + $summary['debit_count'] }} transactions</span>
                                            </div>
                                            <div class="grid grid-cols-4 gap-3 text-xs">
                                                <div>
                                                    <span class="text-gray-500 dark:text-gray-400">Credits:</span>
                                                    <p class="font-semibold text-green-600 dark:text-green-400">${{ number_format($summary['credits'], 2) }}</p>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 dark:text-gray-400">Debits:</span>
                                                    <p class="font-semibold text-red-600 dark:text-red-400">${{ number_format($summary['debits'], 2) }}</p>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 dark:text-gray-400">True Revenue:</span>
                                                    <p class="font-semibold text-green-600 dark:text-green-400">${{ number_format($summary['true_revenue'], 2) }}</p>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 dark:text-gray-400">Net:</span>
                                                    <p class="font-semibold {{ ($summary['credits'] - $summary['debits']) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">${{ number_format($summary['credits'] - $summary['debits'], 2) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                <!-- Transaction List -->
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Category</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Classification</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($month['transactions'] as $txnIndex => $txn)
                                            @php
                                                // Extract account number for filtering
                                                $txnAccountNum = null;
                                                $description = $txn['description'];
                                                if (preg_match('/(?:ACCT|ACCOUNT|A\/C)[\s#:]*([X*]+)?(\d{4,})/i', $description, $m)) {
                                                    $txnAccountNum = $m[2];
                                                } elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $description, $m)) {
                                                    $txnAccountNum = $m[1];
                                                } elseif (preg_match('/[X*]{4,}(\d{4,})/', $description, $m)) {
                                                    $txnAccountNum = $m[1];
                                                }
                                                $txnAccountKey = $txnAccountNum ?? 'unknown';
                                            @endphp
                                            @php
                                                $isAdjustment = $txn['is_adjustment'] ?? false;
                                                $isMcaPayment = $txn['is_mca'] ?? false;
                                                $mcaLender = $txn['mca_lender'] ?? null;
                                                $mcaLenderId = $txn['mca_lender_id'] ?? null;
                                                // MCA funding info for credit transactions
                                                $isMcaFunding = $txn['is_mca_funding'] ?? false;
                                                $mcaFundingLenderName = $txn['mca_funding_lender_name'] ?? null;
                                                $uniqueId = $result['session_id'] . '_' . $month['month_key'] . '_' . $txnIndex;
                                            @endphp
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 txn-row-{{ $uniqueId }} txn-account-{{ $result['session_id'] }}-{{ $month['month_key'] }}" data-month="{{ $month['month_key'] }}" data-session="{{ $result['session_id'] }}" data-transaction-id="{{ $txn['id'] ?? $uniqueId }}" data-account="{{ $txnAccountKey }}">
                                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $txn['date'] }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 max-w-md">{{ $txn['description'] }}</td>
                                                <td class="px-4 py-2 text-center" id="category-cell-{{ $uniqueId }}">
                                                    @php
                                                        // Extract account number from description
                                                        $accountNumber = null;
                                                        $description = $txn['description'];

                                                        // Match patterns like: ACCT ****1234, ACCT XXXX1234, Account ending in 1234, etc.
                                                        if (preg_match('/(?:ACCT|ACCOUNT|A\/C)[\s#:]*([X*]+)?(\d{4,})/i', $description, $matches)) {
                                                            $accountNumber = $matches[2];
                                                        } elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $description, $matches)) {
                                                            $accountNumber = $matches[1];
                                                        } elseif (preg_match('/[X*]{4,}(\d{4,})/', $description, $matches)) {
                                                            $accountNumber = $matches[1];
                                                        }

                                                        // Determine if this is a transfer category
                                                        $txnCategory = $txn['category'] ?? null;
                                                        $isTransfer = in_array($txnCategory, ['internal_transfer', 'wire_transfer', 'ach_transfer']);
                                                        $transferDirection = null;
                                                        if ($isTransfer) {
                                                            if (preg_match('/(?:FROM|IN|INCOMING|RECEIVED|DEPOSIT)/i', $description)) {
                                                                $transferDirection = 'in';
                                                            } elseif (preg_match('/(?:TO|OUT|OUTGOING|SENT|PAYMENT)/i', $description)) {
                                                                $transferDirection = 'out';
                                                            }
                                                        }

                                                        // Get category color classes
                                                        $categoryColorClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                                        if ($txnCategory) {
                                                            $categories = \App\Models\TransactionCategory::getStandardCategories();
                                                            if (isset($categories[$txnCategory])) {
                                                                $color = $categories[$txnCategory]['color'];
                                                                $colorMap = [
                                                                    'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                                                                    'red' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                                    'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                                                    'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                                                    'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                                    'lime' => 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
                                                                    'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                                    'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                                                    'teal' => 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
                                                                    'cyan' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
                                                                    'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                                    'indigo' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                                                                    'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                                    'pink' => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200'
                                                                ];
                                                                $categoryColorClass = $colorMap[$color] ?? $colorMap['gray'];
                                                            }
                                                        }
                                                    @endphp
                                                    @if(isset($txn['category']) && $txn['category'])
                                                        <div class="flex flex-col items-center gap-1">
                                                            <button onclick="openCategoryModalResults('{{ $uniqueId }}', '{{ addslashes($txn['description']) }}', {{ $txn['amount'] }}, '{{ $txn['type'] }}', {{ $txn['id'] ?? 'null' }})"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium category-badge {{ $categoryColorClass }} {{ $isTransfer ? 'ring-2 ring-offset-1 ring-blue-400 dark:ring-blue-500' : '' }} hover:opacity-80 transition cursor-pointer" data-category="{{ $txn['category'] }}" title="Click to change category">
                                                                @if($isTransfer && $transferDirection)
                                                                    @if($transferDirection === 'in')
                                                                        <svg class="w-3 h-3 mr-1 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                                                        </svg>
                                                                    @else
                                                                        <svg class="w-3 h-3 mr-1 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                                                        </svg>
                                                                    @endif
                                                                @endif
                                                                {{ ucwords(str_replace('_', ' ', $txn['category'])) }}
                                                                <svg class="w-3 h-3 ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                                </svg>
                                                            </button>
                                                            @if($accountNumber)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                                    </svg>
                                                                    ****{{ $accountNumber }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <button onclick="openCategoryModalResults('{{ $uniqueId }}', '{{ addslashes($txn['description']) }}', {{ $txn['amount'] }}, '{{ $txn['type'] }}', {{ $txn['id'] ?? 'null' }})"
                                                                class="inline-flex items-center px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                                            </svg>
                                                            Classify
                                                        </button>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right font-medium {{ $txn['type'] === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                    ${{ number_format($txn['amount'], 2) }}
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <button
                                                        onclick="toggleTransactionType('{{ $uniqueId }}', {{ $txn['id'] ?? 'null' }}, '{{ $txn['type'] }}', '{{ addslashes($txn['description']) }}', {{ $txn['amount'] }}, '{{ $month['month_key'] }}', '{{ $result['session_id'] }}')"
                                                        class="type-toggle-btn-{{ $uniqueId }} inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-all cursor-pointer hover:opacity-80 {{ $txn['type'] === 'credit' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}"
                                                        title="Click to toggle between Credit/Debit"
                                                    >
                                                        <span class="type-text">{{ ucfirst($txn['type']) }}</span>
                                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                                                        </svg>
                                                    </button>
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    @if($txn['type'] === 'credit')
                                                    {{-- Revenue classification toggle for credits --}}
                                                    @php
                                                        // Determine button styling based on classification
                                                        if (!$isAdjustment) {
                                                            $btnClasses = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 hover:bg-green-200';
                                                            $btnText = 'True Revenue';
                                                        } elseif ($isMcaFunding) {
                                                            $btnClasses = 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 hover:bg-purple-200';
                                                            $btnText = 'MCA: ' . ($mcaFundingLenderName ?? 'Unknown');
                                                        } else {
                                                            $btnClasses = 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 hover:bg-orange-200';
                                                            $btnText = 'Adjustment';
                                                        }
                                                    @endphp
                                                    <button
                                                        onclick="toggleRevenueClass('{{ $uniqueId }}', '{{ addslashes($txn['description']) }}', {{ $txn['amount'] }}, '{{ $isAdjustment ? 'adjustment' : 'true_revenue' }}', '{{ $month['month_key'] }}', '{{ $result['session_id'] }}')"
                                                        class="revenue-toggle-btn-{{ $uniqueId }} inline-flex items-center px-2 py-1 rounded text-xs font-medium transition-colors cursor-pointer {{ $btnClasses }}"
                                                        title="Click to toggle classification"
                                                    >
                                                        <span class="classification-text">{{ $btnText }}</span>
                                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                                                        </svg>
                                                    </button>
                                                    @else
                                                    {{-- MCA toggle for debits --}}
                                                    <div class="relative mca-toggle-container-{{ $uniqueId }}">
                                                        <button
                                                            onclick="showMcaDropdown('{{ $uniqueId }}', '{{ addslashes($txn['description']) }}', {{ $txn['amount'] }}, {{ $isMcaPayment ? 'true' : 'false' }}, '{{ $mcaLender ?? '' }}', '{{ $mcaLenderId ?? '' }}', '{{ $month['month_key'] }}', '{{ $result['session_id'] }}', {{ isset($txn['id']) ? $txn['id'] : 'null' }})"
                                                            class="mca-toggle-btn-{{ $uniqueId }} inline-flex items-center px-2 py-1 rounded text-xs font-medium transition-colors cursor-pointer {{ $isMcaPayment ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 hover:bg-red-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200' }}"
                                                            title="Click to mark as MCA payment"
                                                        >
                                                            <span class="mca-text">{{ $isMcaPayment ? ($mcaLender ?? 'MCA') : 'Mark MCA' }}</span>
                                                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    @endif
                </div>
            </div>
            @endforeach

            <!-- Combined Summary for Multiple Statements -->
            @if(count($allSuccessfulResults) > 1)
            @php
                $combinedMonthly = [];
                $combinedTotals = [
                    'deposits' => 0,
                    'adjustments' => 0,
                    'true_revenue' => 0,
                    'debits' => 0,
                    'deposit_count' => 0,
                    'nsf_count' => 0,
                ];

                // Aggregate MCA data across all statements
                $combinedMca = [
                    'total_mca_count' => 0,
                    'total_mca_payments' => 0,
                    'total_mca_amount' => 0,
                    'lenders' => [],
                ];
                $mcaLendersMap = [];

                foreach($allSuccessfulResults as $result) {
                    if(isset($result['monthly_data']['months'])) {
                        foreach($result['monthly_data']['months'] as $month) {
                            $key = $month['month_key'];
                            if(!isset($combinedMonthly[$key])) {
                                $combinedMonthly[$key] = [
                                    'month_key' => $key,
                                    'month_name' => $month['month_name'],
                                    'deposits' => 0,
                                    'adjustments' => 0,
                                    'true_revenue' => 0,
                                    'debits' => 0,
                                    'deposit_count' => 0,
                                    'nsf_count' => 0,
                                    'average_daily' => 0,
                                    'days_in_month' => $month['days_in_month'],
                                ];
                            }
                            $combinedMonthly[$key]['deposits'] += $month['deposits'];
                            $combinedMonthly[$key]['adjustments'] += $month['adjustments'];
                            $combinedMonthly[$key]['true_revenue'] += $month['true_revenue'];
                            $combinedMonthly[$key]['debits'] += $month['debits'];
                            $combinedMonthly[$key]['deposit_count'] += $month['deposit_count'];
                            $combinedMonthly[$key]['nsf_count'] += $month['nsf_count'];
                        }
                    }
                    if(isset($result['monthly_data']['totals'])) {
                        $combinedTotals['deposits'] += $result['monthly_data']['totals']['deposits'];
                        $combinedTotals['adjustments'] += $result['monthly_data']['totals']['adjustments'];
                        $combinedTotals['true_revenue'] += $result['monthly_data']['totals']['true_revenue'];
                        $combinedTotals['debits'] += $result['monthly_data']['totals']['debits'];
                        // Use summary counts for consistency with individual session display
                        $combinedTotals['deposit_count'] += $result['summary']['credit_count'] ?? 0;
                        $combinedTotals['nsf_count'] += $result['monthly_data']['totals']['nsf_count'];
                    }

                    // Aggregate MCA data
                    if(isset($result['mca_analysis']) && $result['mca_analysis']['total_mca_count'] > 0) {
                        $combinedMca['total_mca_payments'] += $result['mca_analysis']['total_mca_payments'];
                        $combinedMca['total_mca_amount'] += $result['mca_analysis']['total_mca_amount'];

                        foreach($result['mca_analysis']['lenders'] as $lender) {
                            $lid = $lender['lender_id'];
                            if(!isset($mcaLendersMap[$lid])) {
                                $mcaLendersMap[$lid] = $lender;
                            } else {
                                // Merge lender data
                                $mcaLendersMap[$lid]['payment_count'] += $lender['payment_count'];
                                $mcaLendersMap[$lid]['total_amount'] += $lender['total_amount'];
                                $mcaLendersMap[$lid]['unique_amounts'] = array_unique(array_merge(
                                    $mcaLendersMap[$lid]['unique_amounts'],
                                    $lender['unique_amounts']
                                ));
                                // Update date range
                                if($lender['first_payment'] && (!$mcaLendersMap[$lid]['first_payment'] || $lender['first_payment']['date'] < $mcaLendersMap[$lid]['first_payment']['date'])) {
                                    $mcaLendersMap[$lid]['first_payment'] = $lender['first_payment'];
                                }
                                if($lender['last_payment'] && (!$mcaLendersMap[$lid]['last_payment'] || $lender['last_payment']['date'] > $mcaLendersMap[$lid]['last_payment']['date'])) {
                                    $mcaLendersMap[$lid]['last_payment'] = $lender['last_payment'];
                                }
                            }
                        }
                    }
                }

                // Finalize combined MCA
                $combinedMca['lenders'] = array_values($mcaLendersMap);
                $combinedMca['total_mca_count'] = count($mcaLendersMap);
                // Recalculate averages
                foreach($combinedMca['lenders'] as &$lender) {
                    $lender['average_payment'] = $lender['payment_count'] > 0 ? $lender['total_amount'] / $lender['payment_count'] : 0;
                }
                // Sort by total amount
                usort($combinedMca['lenders'], fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

                // Calculate average daily for combined months
                foreach($combinedMonthly as &$m) {
                    $m['average_daily'] = $m['days_in_month'] > 0 ? $m['true_revenue'] / $m['days_in_month'] : 0;
                }
                ksort($combinedMonthly);
            @endphp

            <div class="mb-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-2">
                    <div class="flex items-center mb-4">
                        <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Combined Summary (All Statements)</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($allSuccessfulResults) }} statements analyzed</p>
                        </div>
                    </div>

                    <!-- Combined MCA Detection Section -->
                    @if($combinedMca['total_mca_count'] > 0)
                    <div class="mb-6 border dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="bg-red-600 px-4 py-3">
                            <h4 class="font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                Combined MCA Obligations (All Statements)
                                <span class="ml-2 px-2 py-0.5 bg-white/20 rounded text-sm">{{ $combinedMca['total_mca_count'] }} {{ Str::plural('Lender', $combinedMca['total_mca_count']) }}</span>
                            </h4>
                        </div>

                        <!-- Combined MCA Summary Cards -->
                        <div class="p-4 bg-red-50 dark:bg-red-900/20 border-b dark:border-gray-700">
                            <div class="grid grid-cols-3 gap-4">
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Active MCAs</p>
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $combinedMca['total_mca_count'] }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Payments</p>
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $combinedMca['total_mca_payments'] }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-red-200 dark:border-red-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total MCA Amount</p>
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">${{ number_format($combinedMca['total_mca_amount'], 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Combined MCA Lenders Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Lender</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Frequency</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase"># Payments</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Avg Payment</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Total Paid</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Date Range</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">View</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($combinedMca['lenders'] as $lenderIndex => $lender)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mr-3">
                                                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $lender['lender_name'] }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @php
                                                $freqColors = [
                                                    'daily' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'every_other_day' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'twice_weekly' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                                    'weekly' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    'bi_weekly' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    'monthly' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'single_payment' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                    'irregular' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                ];
                                                $freqColor = $freqColors[$lender['frequency'] ?? 'unknown'] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $freqColor }}">
                                                {{ $lender['frequency_label'] ?? 'Combined' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm font-medium text-gray-900 dark:text-gray-100">{{ $lender['payment_count'] }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">${{ number_format($lender['average_payment'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold text-red-600 dark:text-red-400">${{ number_format($lender['total_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">
                                            @if(isset($lender['first_payment']) && isset($lender['last_payment']))
                                                @if($lender['first_payment']['date'] === $lender['last_payment']['date'])
                                                    {{ $lender['first_payment']['date'] }}
                                                @else
                                                    {{ $lender['first_payment']['date'] }} to {{ $lender['last_payment']['date'] }}
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button onclick="toggleMcaPaymentRow('combined-{{ $lenderIndex }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                    @if(isset($lender['payments']) && count($lender['payments']) > 0)
                                    <tr id="combined-{{ $lenderIndex }}" class="hidden bg-gray-50 dark:bg-gray-900">
                                        <td colspan="7" class="px-4 py-3">
                                            <div class="max-h-96 overflow-y-auto">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                    <thead class="bg-gray-100 dark:bg-gray-800">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Date</th>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Description</th>
                                                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-300">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                        @foreach($lender['payments'] as $payment)
                                                        <tr>
                                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $payment['date'] }}</td>
                                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $payment['description'] }}</td>
                                                            <td class="px-3 py-2 text-sm text-right text-red-600 dark:text-red-400">${{ number_format($payment['amount'], 2) }}</td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100 dark:bg-gray-700">
                                    <tr class="font-semibold">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                                        <td class="px-4 py-3"></td>
                                        <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-gray-100">{{ $combinedMca['total_mca_payments'] }}</td>
                                        <td class="px-4 py-3"></td>
                                        <td class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400">${{ number_format($combinedMca['total_mca_amount'], 2) }}</td>
                                        <td class="px-4 py-3"></td>
                                        <td class="px-4 py-3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Combined MCA Risk Warning -->
                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border-t dark:border-gray-700">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Combined MCA Stacking Analysis</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                        Across all statements, this merchant has {{ $combinedMca['total_mca_count'] }} existing MCA {{ Str::plural('obligation', $combinedMca['total_mca_count']) }}
                                        with a combined total of ${{ number_format($combinedMca['total_mca_amount'], 2) }} in payments.
                                        @if($combinedMca['total_mca_count'] >= 3)
                                            <strong class="text-red-700 dark:text-red-300">High stacking risk detected.</strong>
                                        @elseif($combinedMca['total_mca_count'] >= 2)
                                            <strong>Moderate stacking detected.</strong>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Combined Monthly Table -->
                    <div class="mb-6 border dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="bg-blue-600 px-4 py-3">
                            <h4 class="font-semibold text-white">Combined Monthly Bank Details</h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Month</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Monthly Deposits</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Adjustments</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-green-600 dark:text-green-400 uppercase">True Revenue</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Avg Daily</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">NSF</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase"># Deposits</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Total Debits</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($combinedMonthly as $month)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700" id="combined-row-{{ $month['month_key'] }}">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $month['month_name'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">${{ number_format($month['deposits'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-orange-600 dark:text-orange-400 combined-adj-{{ $month['month_key'] }}">
                                            $<span class="adj-value">{{ number_format($month['adjustments'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-green-600 dark:text-green-400 combined-rev-{{ $month['month_key'] }}">
                                            $<span class="rev-value">{{ number_format($month['true_revenue'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300 combined-avg-{{ $month['month_key'] }}">
                                            $<span class="avg-value">{{ number_format($month['average_daily'] ?? $month['average_daily_revenue'] ?? 0, 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center {{ $month['nsf_count'] > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">{{ $month['nsf_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $month['deposit_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400">${{ number_format($month['debits'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100 dark:bg-gray-700">
                                    <tr class="font-semibold" id="combined-total-row">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">${{ number_format($combinedTotals['deposits'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-orange-600 dark:text-orange-400 combined-total-adj">
                                            $<span class="adj-value">{{ number_format($combinedTotals['adjustments'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-green-600 dark:text-green-400 combined-total-rev">
                                            $<span class="rev-value">{{ number_format($combinedTotals['true_revenue'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">-</td>
                                        <td class="px-4 py-3 text-sm text-center {{ $combinedTotals['nsf_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $combinedTotals['nsf_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">{{ $combinedTotals['deposit_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400">${{ number_format($combinedTotals['debits'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Combined Summary Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Deposits</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 combined-card-deposits">${{ number_format($combinedTotals['deposits'], 2) }}</p>
                        </div>
                        <div class="bg-orange-50 dark:bg-orange-900/30 p-4 rounded-lg">
                            <p class="text-sm text-orange-600 dark:text-orange-400">Total Adjustments</p>
                            <p class="text-2xl font-bold text-orange-600 dark:text-orange-400 combined-card-adj">${{ number_format($combinedTotals['adjustments'], 2) }}</p>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 p-4 rounded-lg border-2 border-green-200 dark:border-green-800">
                            <p class="text-sm text-green-700 dark:text-green-300 font-medium">Total True Revenue</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400 combined-card-rev">${{ number_format($combinedTotals['true_revenue'], 2) }}</p>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/30 p-4 rounded-lg">
                            <p class="text-sm text-red-600 dark:text-red-400">Total Debits</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400 combined-card-debits">${{ number_format($combinedTotals['debits'], 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="flex justify-center gap-4">
                <a href="{{ route('bankstatement.index') }}" class="inline-flex items-center px-6 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 transition">
                    Analyze More Statements
                </a>
                <a href="{{ route('bankstatement.history') }}" class="inline-flex items-center px-6 py-3 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    View History
                </a>
            </div>
        </div>
    </div>

    <script>
        // Track monthly data for each session
        const monthlyData = @json(collect($results)->where('success', true)->mapWithKeys(function($r) {
            return [$r['session_id'] => $r['monthly_data']];
        }));

        // Track adjustment context for popup
        let currentAdjustmentContext = null;

        // Known MCA lenders for dropdown (loaded from database + static list)
        const mcaLenders = @json($mcaLenders ?? []);

        // Category-related variables (hoisted here so openCategoryModalResults is defined before HTML uses it)
        let currentTransactionIdResults = null;
        let currentTransactionDbIdResults = null;
        let currentTransactionTypeResults = null;
        let categoriesDataResults = null;
        let categoriesLoadingPromise = null;

        // Load categories from API
        function loadCategoriesResults() {
            if (categoriesLoadingPromise) {
                return categoriesLoadingPromise; // Return existing promise if already loading
            }

            console.log('Loading categories from API...');
            categoriesLoadingPromise = fetch('{{ route("bankstatement.categories") }}')
                .then(response => {
                    console.log('Categories API response status:', response.status);
                    if (!response.ok) {
                        throw new Error('Failed to load categories: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Categories loaded successfully:', data);
                    categoriesDataResults = data.categories;
                    console.log('Total categories:', Object.keys(categoriesDataResults).length);
                    return categoriesDataResults;
                })
                .catch(error => {
                    console.error('Error loading categories:', error);
                    categoriesLoadingPromise = null; // Reset on error so it can be retried
                    throw error;
                });

            return categoriesLoadingPromise;
        }

        // Load categories on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCategoriesResults();
            if (typeof applyCategoryColorsResults === 'function') {
                applyCategoryColorsResults();
            }
        });

        // Full implementation (moved here to prevent initialization timing issues)
        window.openCategoryModalResults = async function(transactionId, description, amount, type, dbId) {
            console.log('=== openCategoryModalResults called ===');
            console.log('transactionId:', transactionId);
            console.log('description:', description);
            console.log('amount:', amount);
            console.log('type:', type);
            console.log('dbId:', dbId);

            currentTransactionIdResults = transactionId; // uniqueId for finding DOM elements
            currentTransactionDbIdResults = dbId; // database ID for API call
            currentTransactionTypeResults = type;

            const modalDesc = document.getElementById('modal-description-results');
            if (!modalDesc) {
                console.error('modal-description-results element not found!');
                alert('Error: Modal not properly initialized');
                return;
            }
            modalDesc.textContent = description;

            // If categories aren't loaded yet, load them now
            if (!categoriesDataResults) {
                console.warn('Categories not loaded yet, loading now...');
                try {
                    await loadCategoriesResults();
                    console.log('Categories loaded successfully');
                } catch (error) {
                    console.error('Failed to load categories:', error);
                    alert('Failed to load categories. Please refresh the page.');
                    return;
                }
            }
            console.log('Categories loaded:', Object.keys(categoriesDataResults).length);

            // Get the current category if it exists
            const categoryCell = document.getElementById('category-cell-' + transactionId);
            const currentCategoryBadge = categoryCell ? categoryCell.querySelector('.category-badge') : null;
            const currentCategory = currentCategoryBadge ? currentCategoryBadge.dataset.category : null;

            // Filter categories based on transaction type
            const filteredCategories = Object.entries(categoriesDataResults).filter(([key, cat]) =>
                cat.type === 'both' || cat.type === type
            );

            // Build category grid
            const grid = document.getElementById('category-grid-results');
            let gridHTML = '';

            // Add "Clear Category" option if a category is currently set
            if (currentCategory) {
                gridHTML += `
                    <button onclick="clearCategoryResults()"
                            class="flex items-center gap-2 px-3 py-2 text-left text-sm border border-red-300 dark:border-red-700 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 transition category-option">
                        <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span class="text-red-600 dark:text-red-400 font-medium">Clear Category</span>
                    </button>
                `;
            }

            // Add all other categories
            gridHTML += filteredCategories.map(([key, cat]) => {
                const isSelected = key === currentCategory;
                const selectedClasses = isSelected
                    ? 'border-2 border-blue-500 bg-blue-50 dark:bg-blue-900/30'
                    : 'border';
                const checkIcon = isSelected
                    ? '<svg class="w-4 h-4 text-blue-600 dark:text-blue-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
                    : '';

                return `
                    <button onclick="selectCategoryResults('${key}')"
                            class="flex items-center gap-2 px-3 py-2 text-left text-sm ${selectedClasses} rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition category-option"
                            data-category="${key}">
                        <span class="w-3 h-3 rounded-full category-color-${cat.color}"></span>
                        <span class="text-gray-900 dark:text-white">${cat.label}</span>
                        ${checkIcon}
                    </button>
                `;
            }).join('');

            grid.innerHTML = gridHTML;

            document.getElementById('category-modal-results').classList.remove('hidden');
        };

        // ============================================================================
        // CATEGORY CLASSIFICATION FUNCTIONS - Main Implementations
        // ============================================================================

        // Close the category selection modal
        window.closeCategoryModalResults = function() {
            document.getElementById('category-modal-results').classList.add('hidden');
            currentTransactionIdResults = null;
            currentTransactionDbIdResults = null;
            currentTransactionTypeResults = null;
        }

        // Clear category from a transaction
        window.clearCategoryResults = function() {
            console.log('clearCategoryResults called');
            if (!currentTransactionIdResults) return;

            const description = document.getElementById('modal-description-results').textContent;

            // Find row by class name (txn-row-{uniqueId})
            const row = document.querySelector(`.txn-row-${currentTransactionIdResults}`);
            if (!row) {
                console.error('Could not find transaction row');
                showNotificationResults('Error: Could not find transaction row', 'error');
                return;
            }

            const amountText = row.querySelector('td:nth-child(4)').textContent.trim();
            const amount = parseFloat(amountText.replace(/[$,]/g, ''));

            const requestBody = {
                description: description,
                amount: amount,
                type: currentTransactionTypeResults,
                category: null, // Set to null to clear
                subcategory: null
            };

            // Include transaction_id if we have a valid database ID
            if (currentTransactionDbIdResults) {
                requestBody.transaction_id = currentTransactionDbIdResults;
            }

            console.log('Clear category request:', requestBody);

            fetch('{{ route("bankstatement.toggle-category") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Clear response:', data);
                if (data.success) {
                    const escapedDescription = description.replace(/'/g, "\\'");

                    // If multiple transactions were cleared, find and update all matching rows across ALL sessions
                    if (data.updated_count > 1) {
                        console.log(`Clearing category for ${data.updated_count} transactions with matching description across all visible sessions`);

                        // Find all transaction rows with matching description (case-insensitive)
                        const normalizedDescription = description.trim().toLowerCase();
                        let clearedRowsCount = 0;

                        document.querySelectorAll('tr[class*="txn-row-"]').forEach(txnRow => {
                            // Description is in the 2nd column (td:nth-child(2))
                            const descCell = txnRow.querySelector('td:nth-child(2)');
                            if (descCell && descCell.textContent.trim().toLowerCase() === normalizedDescription) {
                                console.log('Found matching row to clear:', descCell.textContent.trim());

                                // Get the unique ID from the row class
                                const rowClasses = txnRow.className.split(' ');
                                const txnRowClass = rowClasses.find(c => c.startsWith('txn-row-'));
                                if (txnRowClass) {
                                    const txnId = txnRowClass.replace('txn-row-', '');
                                    const categoryCell = document.getElementById('category-cell-' + txnId);

                                    if (categoryCell) {
                                        // Get amount from the row (4th column)
                                        const amountCell = txnRow.querySelector('td:nth-child(4)');
                                        const rowAmount = amountCell ? parseFloat(amountCell.textContent.trim().replace(/[$,]/g, '')) : amount;

                                        // Get transaction database ID from data attribute
                                        const txnDbId = txnRow.getAttribute('data-transaction-id');

                                        const escapedDesc = description.replace(/'/g, "\\'");
                                        categoryCell.innerHTML = `
                                            <button onclick="openCategoryModalResults('${txnId}', '${escapedDesc}', ${rowAmount}, '${currentTransactionTypeResults}', ${txnDbId || 'null'})"
                                                    class="inline-flex items-center px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                                </svg>
                                                Classify
                                            </button>
                                        `;
                                        clearedRowsCount++;

                                        // Add a subtle highlight animation to show the update
                                        txnRow.classList.add('bg-orange-50', 'dark:bg-orange-900/20');
                                        setTimeout(() => {
                                            txnRow.classList.remove('bg-orange-50', 'dark:bg-orange-900/20');
                                        }, 2000);
                                    }
                                }
                            }
                        });

                        console.log(`Successfully cleared ${clearedRowsCount} visible transaction categories in the UI`);

                        // Update category statistics
                        updateCategoryStatistics();
                    } else {
                        // Update only the current transaction
                        const categoryCell = document.getElementById('category-cell-' + currentTransactionIdResults);
                        categoryCell.innerHTML = `
                            <button onclick="openCategoryModalResults('${currentTransactionIdResults}', '${escapedDescription}', ${amount}, '${currentTransactionTypeResults}', ${currentTransactionDbIdResults})"
                                    class="inline-flex items-center px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                Classify
                            </button>
                        `;

                        // Update category statistics
                        updateCategoryStatistics();
                    }

                    closeCategoryModalResults();
                    showNotificationResults(data.message || 'Category cleared successfully', 'success');
                } else {
                    showNotificationResults(data.message || 'Failed to clear category', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotificationResults('An error occurred while clearing the category', 'error');
            });
        }

        // Select and assign a category to a transaction
        window.selectCategoryResults = async function(categoryKey) {
            console.log('selectCategoryResults called:', categoryKey);
            console.log('currentTransactionIdResults:', currentTransactionIdResults);
            console.log('currentTransactionDbIdResults:', currentTransactionDbIdResults);

            if (!currentTransactionIdResults) return;

            const description = document.getElementById('modal-description-results').textContent;

            // Find row by class name (txn-row-{uniqueId})
            const row = document.querySelector(`.txn-row-${currentTransactionIdResults}`);

            if (!row) {
                console.error('Could not find transaction row with class:', `txn-row-${currentTransactionIdResults}`);
                showNotificationResults('Error: Could not find transaction row', 'error');
                return;
            }

            const amountText = row.querySelector('td:nth-child(4)').textContent.trim();
            const amount = parseFloat(amountText.replace(/[$,]/g, ''));

            const requestBody = {
                description: description,
                amount: amount,
                type: currentTransactionTypeResults,
                category: categoryKey,
                subcategory: null
            };

            // Include transaction_id if we have a valid database ID
            if (currentTransactionDbIdResults) {
                requestBody.transaction_id = currentTransactionDbIdResults;
            }

            // Always check for similar transactions using normalized pattern matching (API)
            // This finds transactions with similar descriptions ignoring numbers/dates
            closeCategoryModalResults();

            // Get current session IDs from URL
            const urlParams = new URLSearchParams(window.location.search);
            const sessionIds = urlParams.getAll('sessions[]');

            try {
                // Call API to find similar transactions with normalized pattern matching
                const response = await fetch('{{ route("bankstatement.find-similar-transactions") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        description: description,
                        type: currentTransactionTypeResults,
                        session_ids: sessionIds
                    })
                });

                const data = await response.json();
                console.log('Similar transactions API response:', data);

                // Check if this is an MCA category that requires lender selection
                const isMcaCategory = (categoryKey === 'mca_payment' && currentTransactionTypeResults === 'debit') ||
                                     (categoryKey === 'mca_funding' && currentTransactionTypeResults === 'credit');

                // If multiple similar transactions found, show modal for user to select which ones to update
                if (data.success && data.count > 1) {
                    console.log(`Found ${data.count} similar transactions, showing selection modal`);
                    // Store MCA category info for later use
                    window.pendingMcaCategorySelection = isMcaCategory ? {
                        categoryKey: categoryKey,
                        description: description,
                        type: currentTransactionTypeResults,
                        amount: amount,
                        transactionId: currentTransactionIdResults
                    } : null;
                    showSimilarTransactionsForCategory(description, currentTransactionTypeResults, categoryKey, requestBody);
                } else {
                    // Single transaction - update directly
                    console.log('Single transaction or no similar transactions found, updating directly');
                    performCategoryUpdate(requestBody, false);

                    // After category is saved, if it's an MCA category, prompt for lender
                    if (isMcaCategory) {
                        console.log('MCA category detected, will prompt for lender selection after save');
                        window.pendingMcaCategorySelection = {
                            categoryKey: categoryKey,
                            description: description,
                            type: currentTransactionTypeResults,
                            amount: amount,
                            transactionId: currentTransactionIdResults
                        };
                    }
                }
            } catch (error) {
                console.error('Error checking for similar transactions:', error);
                // If error, just update the single transaction
                performCategoryUpdate(requestBody, false);
            }
        }

        // Similar transactions modal - toggle all checkboxes
        window.toggleAllSimilarCategory = function() {
            const selectAll = document.getElementById('select-all-similar-category');
            document.querySelectorAll('.similar-cat-checkbox').forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateSelectedCategoryCount();
        }

        // Update count of selected transactions in similar transactions modal
        window.updateSelectedCategoryCount = function() {
            const selected = document.querySelectorAll('.similar-cat-checkbox:checked').length;
            document.getElementById('similar-category-selected').textContent = selected;
        }

        // Close the similar transactions modal
        window.closeSimilarCategoryModal = function() {
            document.getElementById('similar-category-modal').classList.add('hidden');
            window.pendingCategoryUpdateData = null;
        }

        // Confirm and apply category to selected similar transactions
        window.confirmSimilarCategoryUpdate = async function() {
            if (!window.pendingCategoryUpdateData) return;

            // Get selected transaction IDs
            const selectedIds = Array.from(document.querySelectorAll('.similar-cat-checkbox:checked'))
                .map(cb => parseInt(cb.value));

            if (selectedIds.length === 0) {
                alert('Please select at least one transaction');
                return;
            }

            const { categoryKey, requestBody } = window.pendingCategoryUpdateData;

            // Close modal
            closeSimilarCategoryModal();

            // Update requestBody with selected transaction IDs
            requestBody.transaction_ids = selectedIds;
            requestBody.update_single_only = false;

            // Perform update
            try {
                const response = await fetch('{{ route("bankstatement.toggle-category") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                const data = await response.json();

                if (data.success) {
                    showNotificationResults(`✓ Updated ${selectedIds.length} transaction(s)`, 'success');

                    // Check if MCA category was selected - if so, prompt for lender selection
                    if (window.pendingMcaCategorySelection) {
                        const mcaInfo = window.pendingMcaCategorySelection;
                        console.log('MCA category saved for multiple transactions, prompting for lender selection');

                        setTimeout(() => {
                            if (mcaInfo.type === 'debit') {
                                showToast(`Category saved for ${selectedIds.length} transaction(s)! Now select the MCA lender...`, 'info');
                                setTimeout(() => {
                                    const parts = mcaInfo.transactionId.split('_');
                                    const sessionId = parts[0];
                                    const monthKey = parts[1];
                                    showMcaDropdown(mcaInfo.transactionId, mcaInfo.description, mcaInfo.amount, false, null, null, monthKey, sessionId);
                                }, 500);
                            }
                            window.pendingMcaCategorySelection = null;
                        }, 1000);
                    } else {
                        // Reload page to reflect changes (only if not MCA category)
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    showNotificationResults(data.message || 'Failed to update categories', 'error');
                }
            } catch (error) {
                console.error('Error updating categories:', error);
                showNotificationResults('An error occurred while updating categories', 'error');
            }
        }

        // ============================================================================
        // HELPER FUNCTIONS FOR CATEGORY OPERATIONS
        // ============================================================================

        // Show similar transactions modal for category classification
        async function showSimilarTransactionsForCategory(description, type, categoryKey, requestBody) {
            // Get current session IDs from URL
            const urlParams = new URLSearchParams(window.location.search);
            const sessionIds = urlParams.getAll('sessions[]');

            // Find similar transactions
            const response = await fetch('{{ route("bankstatement.find-similar-transactions") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    description: description,
                    type: type,
                    session_ids: sessionIds
                })
            });

            const data = await response.json();

            if (data.success && data.count > 0) {
                // Store pending update
                window.pendingCategoryUpdateData = {
                    categoryKey: categoryKey,
                    requestBody: requestBody,
                    transactions: data.matching_transactions
                };

                // Get category info
                const categoryInfo = categoriesDataResults[categoryKey];
                const categoryLabel = categoryInfo ? categoryInfo.label : categoryKey;

                // Update modal
                document.getElementById('similar-category-label').textContent = categoryLabel;
                document.getElementById('similar-category-count').textContent = data.count;

                // Populate transaction list
                const listContainer = document.getElementById('similar-category-list');
                listContainer.innerHTML = data.matching_transactions.map((txn) => `
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <input type="checkbox"
                               id="similar-cat-txn-${txn.id}"
                               value="${txn.id}"
                               onchange="updateSelectedCategoryCount()"
                               class="similar-cat-checkbox w-4 h-4 text-blue-600 rounded"
                               checked>
                        <div class="flex-1 text-sm">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                ${txn.date} - $${txn.amount.toFixed(2)}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400 truncate">
                                ${txn.description}
                            </div>
                        </div>
                    </div>
                `).join('');

                // Show modal
                document.getElementById('similar-category-modal').classList.remove('hidden');
                document.getElementById('select-all-similar-category').checked = true;
                updateSelectedCategoryCount();
            }
        }

        // Perform the actual category update via API
        function performCategoryUpdate(requestBody, isBulk) {
            console.log('Performing category update:', requestBody, 'isBulk:', isBulk);

            fetch('{{ route("bankstatement.toggle-category") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    const description = requestBody.description;
                    const categoryKey = requestBody.category;

                    // If multiple transactions were updated (and user confirmed bulk), update all visible rows
                    if (data.updated_count > 1 && isBulk) {
                        console.log(`Updating ${data.updated_count} transactions with matching description`);
                        updateAllMatchingRows(description, categoryKey, requestBody.amount, requestBody.type);
                    } else {
                        // Update only the current transaction
                        updateSingleRow(currentTransactionIdResults, categoryKey, description, requestBody.amount, requestBody.type);
                    }

                    closeCategoryModalResults();
                    const msg = isBulk && data.updated_count > 1
                        ? data.message
                        : `Transaction classified as "${categoriesDataResults[categoryKey].label}"`;
                    showNotificationResults(msg, 'success');

                    // Update category statistics
                    updateCategoryStatistics();

                    // Check if we need to prompt for MCA lender selection
                    if (window.pendingMcaCategorySelection) {
                        const mcaInfo = window.pendingMcaCategorySelection;
                        console.log('MCA category saved, now prompting for lender selection:', mcaInfo);

                        setTimeout(() => {
                            promptMcaLenderAfterCategory(
                                mcaInfo.description,
                                mcaInfo.amount,
                                mcaInfo.type,
                                mcaInfo.transactionId
                            );
                            window.pendingMcaCategorySelection = null;
                        }, 1000);
                    }
                } else {
                    showNotificationResults(data.message || 'Error updating category', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotificationResults('Network error. Please try again.', 'error');
            });
        }

        // Prompt for MCA lender after category is saved
        function promptMcaLenderAfterCategory(description, amount, type, transactionId) {
            console.log('Prompting for MCA lender:', { description, amount, type, transactionId });

            if (type === 'debit') {
                // For debit transactions (MCA Payments), show MCA lender dropdown
                // Extract session ID and month key from transaction ID
                const parts = transactionId.split('_');
                const sessionId = parts[0];
                const monthKey = parts[1];

                showToast('Category saved! Now select the MCA lender...', 'info');

                // Show the MCA lender dropdown (reuse existing function)
                const currentLender = null;
                const currentLenderId = null;

                setTimeout(() => {
                    showMcaDropdown(transactionId, description, amount, false, currentLender, currentLenderId, monthKey, sessionId);
                }, 500);
            } else if (type === 'credit') {
                // For credit transactions (MCA Funding), we could show a similar dialog
                showToast('MCA Funding category saved! Consider also marking related payments.', 'info');
                // Could potentially show a lender selection for funding as well
            }
        }

        // Update all matching transaction rows in the UI
        function updateAllMatchingRows(description, categoryKey, amount, type) {
            const normalizedDescription = description.trim().toLowerCase();
            let updatedRowsCount = 0;

            document.querySelectorAll('tr[class*="txn-row-"]').forEach(txnRow => {
                const descCell = txnRow.querySelector('td:nth-child(2)');
                if (descCell && descCell.textContent.trim().toLowerCase() === normalizedDescription) {
                    const rowClasses = txnRow.className.split(' ');
                    const txnRowClass = rowClasses.find(c => c.startsWith('txn-row-'));
                    if (txnRowClass) {
                        const txnId = txnRowClass.replace('txn-row-', '');
                        const categoryCell = document.getElementById('category-cell-' + txnId);
                        if (categoryCell) {
                            const amountCell = txnRow.querySelector('td:nth-child(4)');
                            const rowAmount = amountCell ? parseFloat(amountCell.textContent.trim().replace(/[$,]/g, '')) : amount;
                            const txnDbId = txnRow.getAttribute('data-transaction-id');
                            const rowType = amountCell && (amountCell.classList.contains('text-green-600') || amountCell.classList.contains('text-green-400')) ? 'credit' : 'debit';

                            updateCategoryCellHTML(categoryCell, txnId, categoryKey, description, rowAmount, rowType, txnDbId);
                            updatedRowsCount++;

                            // Add highlight animation
                            txnRow.classList.add('bg-blue-50', 'dark:bg-blue-900/20');
                            setTimeout(() => txnRow.classList.remove('bg-blue-50', 'dark:bg-blue-900/20'), 2000);
                        }
                    }
                }
            });
            console.log(`Successfully updated ${updatedRowsCount} visible transaction rows`);
        }

        // Update a single transaction row in the UI
        function updateSingleRow(txnId, categoryKey, description, amount, type) {
            const categoryCell = document.getElementById('category-cell-' + txnId);
            if (categoryCell) {
                updateCategoryCellHTML(categoryCell, txnId, categoryKey, description, amount, type, currentTransactionDbIdResults);
            }
        }

        // Helper function to update category cell HTML
        function updateCategoryCellHTML(categoryCell, txnId, categoryKey, description, amount, txnType, txnDbId) {
            const categoryInfo = categoriesDataResults[categoryKey];

            // Check if this is a transfer category
            const isTransfer = ['internal_transfer', 'wire_transfer', 'ach_transfer'].includes(categoryKey);
            const accountNumber = extractAccountNumberResults(description);
            const transferDirection = isTransfer ? getTransferDirectionResults(description) : null;

            // Escape description for onclick handler
            const escapedDescription = description.replace(/'/g, "\\'");

            // Build the category display with clickable button
            let categoryHTML = '<div class="flex flex-col items-center gap-1">';

            // Get color classes based on category color
            const colorClasses = {
                'gray': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                'red': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'orange': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                'amber': 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                'yellow': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                'lime': 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
                'green': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'emerald': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                'teal': 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
                'cyan': 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
                'blue': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                'indigo': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                'purple': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                'pink': 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200'
            };
            const categoryColorClass = colorClasses[categoryInfo.color] || colorClasses['gray'];

            // Clickable category badge with optional transfer ring
            categoryHTML += `<button onclick="openCategoryModalResults('${txnId}', '${escapedDescription}', ${amount}, '${txnType}', ${txnDbId})" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium category-badge ${isTransfer ? 'ring-2 ring-offset-1 ring-blue-400 dark:ring-blue-500' : ''} hover:opacity-80 transition cursor-pointer ${categoryColorClass}" data-category="${categoryKey}" title="Click to change category">`;

            // Transfer direction icon
            if (isTransfer && transferDirection) {
                if (transferDirection === 'in') {
                    categoryHTML += `<svg class="w-3 h-3 mr-1 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                    </svg>`;
                } else {
                    categoryHTML += `<svg class="w-3 h-3 mr-1 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                    </svg>`;
                }
            }

            categoryHTML += `${categoryInfo.label}`;

            // Add edit icon
            categoryHTML += `<svg class="w-3 h-3 ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
            </svg>`;

            categoryHTML += `</button>`;

            // Account number badge if present
            if (accountNumber) {
                categoryHTML += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    ****${accountNumber}
                </span>`;
            }

            categoryHTML += '</div>';
            categoryCell.innerHTML = categoryHTML;
        }

        // Helper function to extract account numbers from descriptions
        function extractAccountNumberResults(description) {
            // Match patterns like: ACCT ****1234, ACCT XXXX1234, Account ending in 1234, etc.
            let match;
            if (match = description.match(/(?:ACCT|ACCOUNT|A\/C)[\s#:]*([X*]+)?(\d{4,})/i)) {
                return match[2];
            } else if (match = description.match(/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i)) {
                return match[1];
            } else if (match = description.match(/[X*]{4,}(\d{4,})/)) {
                return match[1];
            }
            return null;
        }

        // Helper function to determine transfer direction from description
        function getTransferDirectionResults(description) {
            if (/(?:FROM|IN|INCOMING|RECEIVED|DEPOSIT)/i.test(description)) {
                return 'in';
            } else if (/(?:TO|OUT|OUTGOING|SENT|PAYMENT)/i.test(description)) {
                return 'out';
            }
            return null;
        }

        // Helper function to update category statistics across the dashboard
        function updateCategoryStatistics() {
            // Count transactions by category for each visible session
            const categoryStats = {};

            document.querySelectorAll('.category-badge').forEach(badge => {
                const category = badge.dataset.category;
                if (category) {
                    if (!categoryStats[category]) {
                        categoryStats[category] = 0;
                    }
                    categoryStats[category]++;
                }
            });

            console.log('Updated category statistics:', categoryStats);

            // You can add code here to display these statistics in a summary panel
            // For now, this provides the data structure for future enhancements
        }

        // Helper function to show notifications
        function showNotificationResults(message, type) {
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Store pending category update data (used in bulk update confirmation flow)
        let pendingCategoryUpdate = null;

        // Show bulk update confirmation modal
        function showBulkUpdateConfirmation(count, description, categoryKey) {
            const categoryInfo = categoriesDataResults[categoryKey];
            document.getElementById('bulk-count').textContent = count;
            document.getElementById('bulk-count-button').textContent = `(${count})`;
            document.getElementById('bulk-description').textContent = description;
            document.getElementById('bulk-category-name').textContent = categoryInfo.label;
            document.getElementById('bulk-category-name').className = `font-semibold text-${categoryInfo.color}-600 dark:text-${categoryInfo.color}-400`;

            // Hide category modal and show confirmation modal
            document.getElementById('category-modal-results').classList.add('hidden');
            document.getElementById('bulk-update-confirmation-modal').classList.remove('hidden');
        }

        // Confirm bulk update (user wants to update all matching transactions)
        window.confirmBulkUpdate = function() {
            console.log('User confirmed bulk update');
            document.getElementById('bulk-update-confirmation-modal').classList.add('hidden');
            if (pendingCategoryUpdate) {
                performCategoryUpdate(pendingCategoryUpdate, true);
                pendingCategoryUpdate = null;
            }
        }

        // Cancel bulk update (user wants to update only current transaction)
        window.cancelBulkUpdate = function() {
            console.log('User cancelled bulk update - updating only current transaction');
            document.getElementById('bulk-update-confirmation-modal').classList.add('hidden');
            if (pendingCategoryUpdate) {
                // Update only the current transaction by adding a flag
                pendingCategoryUpdate.update_single_only = true;
                performCategoryUpdate(pendingCategoryUpdate, false);
                pendingCategoryUpdate = null;
            }
        }

        // Track current MCA filter state
        let currentMcaFilter = null;

        // Toggle MCA payment row (for individual session dropdown)
        function toggleMcaPaymentRow(rowId) {
            const row = document.getElementById(rowId);
            if (row) {
                row.classList.toggle('hidden');
            }
        }

        // Filter/highlight MCA transactions in historical data
        function toggleMcaTransactions(lenderId, lenderName) {
            console.log('toggleMcaTransactions called:', {lenderId, lenderName});

            // If already filtering this lender, clear the filter
            if (currentMcaFilter === lenderId) {
                clearMcaTransactionFilter();
                return;
            }

            // Set new filter
            currentMcaFilter = lenderId;

            // Update button states
            const buttons = document.querySelectorAll('.mca-view-btn');
            console.log('Found', buttons.length, 'MCA view buttons');

            buttons.forEach(btn => {
                const btnLenderId = btn.getAttribute('data-lender-id');
                if (btnLenderId === lenderId) {
                    btn.classList.add('bg-blue-100', 'dark:bg-blue-900');
                    btn.classList.remove('hover:bg-blue-50', 'dark:hover:bg-blue-900/30');
                } else {
                    btn.classList.remove('bg-blue-100', 'dark:bg-blue-900');
                    btn.classList.add('hover:bg-blue-50', 'dark:hover:bg-blue-900/30');
                }
            });

            // Find all transaction rows
            const allRows = document.querySelectorAll('tr[data-transaction-id]');
            console.log('Found', allRows.length, 'transaction rows');

            let visibleCount = 0;
            let firstVisibleRow = null;

            // Extract key words from lender name for better matching
            const lenderNameLower = lenderName.toLowerCase();
            const lenderWords = lenderNameLower.split(' ').filter(w => w.length > 3);
            console.log('Searching for lender words:', lenderWords);

            allRows.forEach(row => {
                // Get transaction description from the row
                const descriptionCell = row.querySelector('td:nth-child(2)');
                if (!descriptionCell) return;

                const description = descriptionCell.textContent.trim();
                const descriptionLower = description.toLowerCase();

                // Check if transaction matches the lender
                // Try exact match first, then word matching
                let isMcaForLender = descriptionLower.includes(lenderNameLower);

                // If no exact match, try matching key words
                if (!isMcaForLender && lenderWords.length > 0) {
                    const matchedWords = lenderWords.filter(word => descriptionLower.includes(word));
                    isMcaForLender = matchedWords.length >= Math.min(2, lenderWords.length);
                }

                if (isMcaForLender) {
                    console.log('Matched transaction:', description.substring(0, 50));
                    // Show and highlight this row
                    row.classList.remove('hidden');
                    row.classList.add('bg-yellow-50', 'dark:bg-yellow-900/20', 'ring-2', 'ring-yellow-400', 'dark:ring-yellow-600');
                    visibleCount++;
                    if (!firstVisibleRow) {
                        firstVisibleRow = row;
                    }
                } else {
                    // Dim this row
                    row.classList.add('opacity-30');
                    row.classList.remove('bg-yellow-50', 'dark:bg-yellow-900/20', 'ring-2', 'ring-yellow-400', 'dark:ring-yellow-600');
                }
            });

            console.log('Visible count:', visibleCount);

            // Show filter indicator
            showMcaFilterIndicator(lenderName, visibleCount);

            // Scroll to first visible transaction
            if (firstVisibleRow) {
                setTimeout(() => {
                    firstVisibleRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            } else {
                console.warn('No matching transactions found for', lenderName);
            }
        }

        function clearMcaTransactionFilter() {
            currentMcaFilter = null;

            // Reset button states
            document.querySelectorAll('.mca-view-btn').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'dark:bg-blue-900');
                btn.classList.add('hover:bg-blue-50', 'dark:hover:bg-blue-900/30');
            });

            // Reset all transaction rows
            const allRows = document.querySelectorAll('tr[data-transaction-id]');
            allRows.forEach(row => {
                row.classList.remove('opacity-30', 'bg-yellow-50', 'dark:bg-yellow-900/20', 'ring-2', 'ring-yellow-400', 'dark:ring-yellow-600');
            });

            // Hide filter indicator
            hideMcaFilterIndicator();
        }

        function showMcaFilterIndicator(lenderName, count) {
            // Remove existing indicator if any
            hideMcaFilterIndicator();

            // Create filter indicator
            const indicator = document.createElement('div');
            indicator.id = 'mca-filter-indicator';
            indicator.className = 'fixed top-20 right-4 z-50 bg-blue-600 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 animate-slide-in-right';
            indicator.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <div>
                    <div class="font-semibold text-sm">Filtering: ${lenderName}</div>
                    <div class="text-xs opacity-90">${count} transaction(s) found</div>
                </div>
                <button onclick="clearMcaTransactionFilter()" class="ml-2 hover:bg-blue-700 rounded p-1" title="Clear filter">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            `;

            document.body.appendChild(indicator);
        }

        function hideMcaFilterIndicator() {
            const indicator = document.getElementById('mca-filter-indicator');
            if (indicator) {
                indicator.remove();
            }
        }

        // Pattern normalization function (mirrors PHP RevenueClassification::normalizePattern)
        function normalizePattern(description) {
            let normalized = description;
            // Remove dates (MM/DD or MM/DD/YYYY)
            normalized = normalized.replace(/\d{1,2}\/\d{1,2}(\/\d{2,4})?/g, '');
            // Replace long numbers with placeholder (account/reference numbers)
            normalized = normalized.replace(/\d{6,}/g, '#ID#');
            // Remove dollar amounts
            normalized = normalized.replace(/\$[\d,]+\.?\d*/g, '');
            // Clean up whitespace
            normalized = normalized.replace(/\s+/g, ' ').trim();
            return normalized;
        }

        // Check if two patterns are similar
        function checkPatternSimilarity(pattern1, pattern2, words1) {
            // Exact match
            if (pattern1 === pattern2) return true;

            // Containment (one pattern contains the other)
            if (pattern1.includes(pattern2) || pattern2.includes(pattern1)) return true;

            // Word matching (60% threshold)
            const words2 = pattern2.split(' ').filter(w => w.length > 3);
            if (words1.length >= 2) {
                let matchCount = 0;
                words1.forEach(word => {
                    if (pattern2.includes(word)) matchCount++;
                });
                if (matchCount >= Math.ceil(words1.length * 0.6)) return true;
            }

            return false;
        }

        // Find similar transactions across all sessions/months
        function findSimilarTransactions(sourceDescription, sourceLenderId, sourceLenderName, sourceUniqueId) {
            const sourcePattern = normalizePattern(sourceDescription).toLowerCase();
            const sourceWords = sourcePattern.split(' ').filter(w => w.length > 3);
            const similarTransactions = { credits: [], debits: [] };

            // Iterate through all sessions and months
            Object.entries(monthlyData).forEach(([sessionId, sessionData]) => {
                if (!sessionData.months) return;

                sessionData.months.forEach((month, monthIndex) => {
                    if (!month.transactions) return;

                    month.transactions.forEach((txn, txnIndex) => {
                        // Generate unique ID for this transaction
                        const txnUniqueId = `${sessionId}_${month.month_key}_${txnIndex}`;

                        // Skip the source transaction itself
                        if (txnUniqueId === sourceUniqueId) return;

                        const txnPattern = normalizePattern(txn.description).toLowerCase();

                        // Skip if already marked as this lender's MCA funding/payment
                        if (txn.type === 'credit') {
                            if (txn.is_mca_funding && txn.mca_funding_lender_id === sourceLenderId) return;
                            // Also skip if already marked as adjustment (non-MCA)
                            if (txn.is_adjustment && !txn.is_mca_funding) return;
                        }
                        if (txn.type === 'debit') {
                            if (txn.is_mca && txn.mca_lender_id === sourceLenderId) return;
                        }

                        // Check similarity
                        const isSimilar = checkPatternSimilarity(sourcePattern, txnPattern, sourceWords);

                        if (isSimilar) {
                            const txnData = {
                                ...txn,
                                uniqueId: txnUniqueId,
                                sessionId: sessionId,
                                monthKey: month.month_key,
                                monthIndex: monthIndex,
                                txnIndex: txnIndex
                            };

                            if (txn.type === 'credit') {
                                similarTransactions.credits.push(txnData);
                            } else {
                                similarTransactions.debits.push(txnData);
                            }
                        }
                    });
                });
            });

            console.log('Found similar transactions:', similarTransactions.credits.length, 'credits,', similarTransactions.debits.length, 'debits');
            return similarTransactions;
        }

        // Similar transactions modal context
        let currentSimilarContext = null;

        // Show modal with similar transactions
        function showSimilarTransactionsModal(sourceDescription, lenderId, lenderName, sourceAmount, sourceUniqueId) {
            currentSimilarContext = { sourceDescription, lenderId, lenderName, sourceAmount, sourceUniqueId };

            const similar = findSimilarTransactions(sourceDescription, lenderId, lenderName, sourceUniqueId);
            const modal = document.getElementById('similar-transactions-modal');
            const subtitle = document.getElementById('similar-modal-subtitle');
            const creditsList = document.getElementById('similar-credits-list');
            const debitsList = document.getElementById('similar-debits-list');
            const creditsSection = document.getElementById('similar-credits-section');
            const debitsSection = document.getElementById('similar-debits-section');
            const noFound = document.getElementById('no-similar-found');

            subtitle.textContent = `Lender: ${lenderName}`;

            // Clear previous content
            creditsList.innerHTML = '';
            debitsList.innerHTML = '';
            document.getElementById('select-all-credits').checked = false;
            document.getElementById('select-all-debits').checked = false;

            // Render credits
            if (similar.credits.length > 0) {
                creditsSection.classList.remove('hidden');
                similar.credits.forEach(txn => {
                    creditsList.innerHTML += renderSimilarTransaction(txn, 'credit');
                });
            } else {
                creditsSection.classList.add('hidden');
            }

            // Render debits
            if (similar.debits.length > 0) {
                debitsSection.classList.remove('hidden');
                similar.debits.forEach(txn => {
                    debitsList.innerHTML += renderSimilarTransaction(txn, 'debit');
                });
            } else {
                debitsSection.classList.add('hidden');
            }

            // Show "no results" if both empty
            if (similar.credits.length === 0 && similar.debits.length === 0) {
                noFound.classList.remove('hidden');
            } else {
                noFound.classList.add('hidden');
            }

            updateSimilarSelectedCount();
            modal.classList.remove('hidden');
        }

        // Render a single similar transaction row
        function renderSimilarTransaction(txn, type) {
            const amountColor = type === 'credit' ? 'text-green-600' : 'text-red-600';
            const amountPrefix = type === 'credit' ? '+' : '-';
            const currentStatus = type === 'credit'
                ? (txn.is_adjustment ? 'Adjustment' : 'True Revenue')
                : (txn.is_mca ? txn.mca_lender : 'Not MCA');

            return `
                <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <input type="checkbox"
                        class="similar-txn-checkbox w-4 h-4 mr-3 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                        data-unique-id="${txn.uniqueId}"
                        data-session-id="${txn.sessionId}"
                        data-month-key="${txn.monthKey}"
                        data-month-index="${txn.monthIndex}"
                        data-txn-index="${txn.txnIndex}"
                        data-description="${txn.description.replace(/"/g, '&quot;')}"
                        data-amount="${txn.amount}"
                        data-type="${type}"
                        onchange="updateSimilarSelectedCount()">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" title="${txn.description}">${txn.description}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${txn.date} | Current: ${currentStatus}</p>
                    </div>
                    <span class="ml-3 font-semibold ${amountColor}">${amountPrefix}$${parseFloat(txn.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                </label>
            `;
        }

        // Close the similar transactions modal
        function closeSimilarTransactionsModal() {
            document.getElementById('similar-transactions-modal').classList.add('hidden');
            currentSimilarContext = null;
        }

        // Toggle all credits checkboxes
        function toggleAllCredits() {
            const checked = document.getElementById('select-all-credits').checked;
            document.querySelectorAll('#similar-credits-list .similar-txn-checkbox').forEach(cb => {
                cb.checked = checked;
            });
            updateSimilarSelectedCount();
        }

        // Toggle all debits checkboxes
        function toggleAllDebits() {
            const checked = document.getElementById('select-all-debits').checked;
            document.querySelectorAll('#similar-debits-list .similar-txn-checkbox').forEach(cb => {
                cb.checked = checked;
            });
            updateSimilarSelectedCount();
        }

        // Update selected count display
        function updateSimilarSelectedCount() {
            const count = document.querySelectorAll('.similar-txn-checkbox:checked').length;
            document.getElementById('similar-selected-count').textContent = count;
            document.getElementById('batch-mark-btn').disabled = count === 0;
        }

        // Batch mark all selected similar transactions
        async function batchMarkSimilarTransactions() {
            if (!currentSimilarContext) return;

            const { lenderId, lenderName } = currentSimilarContext;
            const selectedCheckboxes = document.querySelectorAll('.similar-txn-checkbox:checked');

            if (selectedCheckboxes.length === 0) {
                showToast('No transactions selected', 'error');
                return;
            }

            // Collect selected transactions
            const transactions = [];
            selectedCheckboxes.forEach(cb => {
                transactions.push({
                    uniqueId: cb.dataset.uniqueId,
                    sessionId: cb.dataset.sessionId,
                    monthKey: cb.dataset.monthKey,
                    monthIndex: parseInt(cb.dataset.monthIndex),
                    txnIndex: parseInt(cb.dataset.txnIndex),
                    description: cb.dataset.description,
                    amount: parseFloat(cb.dataset.amount),
                    type: cb.dataset.type
                });
            });

            // Disable button and show loading state
            const btn = document.getElementById('batch-mark-btn');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Processing...';

            try {
                const response = await fetch('{{ route("bankstatement.batch-classify") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        transactions: transactions,
                        mca_lender_id: lenderId,
                        mca_lender_name: lenderName
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Update UI for each processed transaction
                    data.results.forEach(result => {
                        updateTransactionUIAfterBatch(result);
                    });

                    showToast(`Successfully marked ${data.processed_count} transactions for ${lenderName}`, 'success');
                    closeSimilarTransactionsModal();
                } else {
                    showToast(data.message || 'Failed to process batch', 'error');
                }
            } catch (error) {
                console.error('Batch mark error:', error);
                showToast('Error processing batch request', 'error');
            }

            btn.disabled = false;
            btn.textContent = originalText;
        }

        // Update a single transaction's UI after batch marking
        function updateTransactionUIAfterBatch(result) {
            const { uniqueId, type, sessionId, monthKey, description, amount, mca_lender_id, mca_lender_name, monthIndex, txnIndex } = result;

            if (type === 'credit') {
                // Update credit row button to show MCA Funding
                const btn = document.querySelector(`.revenue-toggle-btn-${uniqueId}`);
                if (btn) {
                    const textSpan = btn.querySelector('.classification-text');
                    if (textSpan) {
                        textSpan.textContent = `MCA: ${mca_lender_name}`;
                    }

                    btn.classList.remove('bg-green-100', 'text-green-800', 'dark:bg-green-900', 'dark:text-green-200', 'hover:bg-green-200');
                    btn.classList.remove('bg-orange-100', 'text-orange-800', 'dark:bg-orange-900', 'dark:text-orange-200', 'hover:bg-orange-200');
                    btn.classList.add('bg-purple-100', 'text-purple-800', 'dark:bg-purple-900', 'dark:text-purple-200', 'hover:bg-purple-200');

                    // Update onclick to toggle back
                    const escapedDesc = description.replace(/'/g, "\\'").replace(/"/g, '\\"');
                    btn.setAttribute('onclick', `toggleRevenueClass('${uniqueId}', '${escapedDesc}', ${amount}, 'adjustment', '${monthKey}', '${sessionId}')`);
                }

                // Update monthly totals
                updateMonthlySummary(sessionId, monthKey, amount, 'true_revenue', 'adjustment');
                updateMcaFundingSection(mca_lender_id, mca_lender_name, amount, true);

            } else {
                // Update debit row button to show MCA Payment
                const btn = document.querySelector(`.mca-toggle-btn-${uniqueId}`);
                if (btn) {
                    const textSpan = btn.querySelector('.mca-text');
                    if (textSpan) {
                        textSpan.textContent = mca_lender_name;
                    }

                    btn.classList.remove('bg-gray-100', 'text-gray-600', 'dark:bg-gray-700', 'dark:text-gray-400', 'hover:bg-gray-200');
                    btn.classList.add('bg-red-100', 'text-red-800', 'dark:bg-red-900', 'dark:text-red-200', 'hover:bg-red-200');
                }

                // Update MCA payment tracking
                updateMcaSummary(sessionId, true, mca_lender_id, mca_lender_name, amount, description);
            }

            // Update monthlyData object to reflect changes
            updateMonthlyDataObjectAfterBatch(result);
        }

        // Update the monthlyData JavaScript object after batch marking
        function updateMonthlyDataObjectAfterBatch(result) {
            const { sessionId, monthKey, txnIndex, type, mca_lender_id, mca_lender_name, amount } = result;

            if (monthlyData[sessionId]?.months) {
                const month = monthlyData[sessionId].months.find(m => m.month_key === monthKey);
                if (month && month.transactions && month.transactions[txnIndex]) {
                    const txn = month.transactions[txnIndex];
                    if (type === 'credit') {
                        txn.is_adjustment = true;
                        txn.is_mca_funding = true;
                        txn.mca_funding_lender_id = mca_lender_id;
                        txn.mca_funding_lender_name = mca_lender_name;
                        // Update month totals
                        month.adjustments = (month.adjustments || 0) + parseFloat(amount);
                        month.true_revenue = (month.true_revenue || 0) - parseFloat(amount);
                    } else {
                        txn.is_mca = true;
                        txn.mca_lender_id = mca_lender_id;
                        txn.mca_lender = mca_lender_name;
                    }
                }
            }
        }

        async function toggleRevenueClass(uniqueId, description, amount, currentClass, monthKey, sessionId) {
            // If currently True Revenue and about to mark as Adjustment, show popup to ask what type
            if (currentClass === 'true_revenue') {
                showAdjustmentTypePopup(uniqueId, description, amount, currentClass, monthKey, sessionId);
                return;
            }

            // If currently Adjustment, directly toggle back to True Revenue
            await submitRevenueClassification(uniqueId, description, amount, currentClass, monthKey, sessionId, false, null, null);
        }

        function showAdjustmentTypePopup(uniqueId, description, amount, currentClass, monthKey, sessionId) {
            // Debug: Log current state of mcaLenders
            console.log('Opening popup - mcaLenders has', Object.keys(mcaLenders).length, 'lenders:', Object.keys(mcaLenders));

            // Remove any existing popup
            const existingPopup = document.getElementById('adjustment-type-popup');
            if (existingPopup) existingPopup.remove();

            currentAdjustmentContext = { uniqueId, description, amount, currentClass, monthKey, sessionId };

            const btn = document.querySelector('.revenue-toggle-btn-' + uniqueId);
            const rect = btn.getBoundingClientRect();

            // Create popup
            const popup = document.createElement('div');
            popup.id = 'adjustment-type-popup';
            popup.className = 'fixed z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl p-4 w-72';
            popup.style.top = (rect.bottom + 5) + 'px';
            popup.style.left = Math.min(rect.left, window.innerWidth - 300) + 'px';

            // Build lender options
            let lenderOptionsHtml = '';
            for (const [id, name] of Object.entries(mcaLenders)) {
                lenderOptionsHtml += `<option value="${id}">${name}</option>`;
            }

            popup.innerHTML = `
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">What type of adjustment is this?</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 truncate" title="${description}">${description.substring(0, 50)}${description.length > 50 ? '...' : ''}</p>
                </div>
                <div class="space-y-2 mb-4">
                    <label class="flex items-center p-2 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                        <input type="radio" name="adjustment_type" value="regular" class="w-4 h-4 text-orange-600 focus:ring-orange-500" checked>
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Regular Adjustment</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Transfer, refund, or other non-revenue deposit</p>
                        </div>
                    </label>
                    <label class="flex items-center p-2 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                        <input type="radio" name="adjustment_type" value="mca_funding" class="w-4 h-4 text-purple-600 focus:ring-purple-500">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">MCA Funding</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Loan/advance from an MCA lender</p>
                        </div>
                    </label>
                </div>
                <div id="mca-funding-lender-section" class="mb-4 hidden">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Select MCA Lender</label>
                    <select id="adjustment-mca-lender-select" class="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5">
                        <option value="">-- Select Lender --</option>
                        ${lenderOptionsHtml}
                        <option value="custom">+ Add Custom Lender</option>
                    </select>
                    <div id="adjustment-custom-lender-input" class="mt-2 hidden">
                        <input type="text" id="adjustment-custom-lender-name" placeholder="Enter lender name" class="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="saveAdjustmentType()" class="flex-1 px-3 py-2 bg-orange-600 text-white text-sm font-medium rounded hover:bg-orange-700 transition-colors">
                        Mark as Adjustment
                    </button>
                    <button onclick="closeAdjustmentTypePopup()" class="px-3 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                </div>
            `;

            document.body.appendChild(popup);

            // Handle radio button change to show/hide lender selection
            popup.querySelectorAll('input[name="adjustment_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const lenderSection = document.getElementById('mca-funding-lender-section');
                    if (this.value === 'mca_funding') {
                        lenderSection.classList.remove('hidden');
                    } else {
                        lenderSection.classList.add('hidden');
                    }
                });
            });

            // Handle custom lender selection
            document.getElementById('adjustment-mca-lender-select').addEventListener('change', function() {
                const customInput = document.getElementById('adjustment-custom-lender-input');
                if (this.value === 'custom') {
                    customInput.classList.remove('hidden');
                } else {
                    customInput.classList.add('hidden');
                }
            });

            // Close on outside click
            setTimeout(() => {
                document.addEventListener('click', closeAdjustmentPopupOnOutsideClick);
            }, 100);
        }

        function closeAdjustmentPopupOnOutsideClick(e) {
            const popup = document.getElementById('adjustment-type-popup');
            if (popup && !popup.contains(e.target) && !e.target.classList.contains('revenue-toggle-btn-' + currentAdjustmentContext?.uniqueId)) {
                closeAdjustmentTypePopup();
            }
        }

        function closeAdjustmentTypePopup() {
            const popup = document.getElementById('adjustment-type-popup');
            if (popup) popup.remove();
            document.removeEventListener('click', closeAdjustmentPopupOnOutsideClick);
            currentAdjustmentContext = null;
        }

        async function saveAdjustmentType() {
            if (!currentAdjustmentContext) return;

            const selectedType = document.querySelector('input[name="adjustment_type"]:checked')?.value;
            const isMcaFunding = selectedType === 'mca_funding';

            let mcaLenderId = null;
            let mcaLenderName = null;

            if (isMcaFunding) {
                const select = document.getElementById('adjustment-mca-lender-select');
                mcaLenderId = select.value;

                if (!mcaLenderId) {
                    showToast('Please select an MCA lender', 'error');
                    return;
                }

                if (mcaLenderId === 'custom') {
                    mcaLenderName = document.getElementById('adjustment-custom-lender-name').value.trim();
                    if (!mcaLenderName) {
                        showToast('Please enter the lender name', 'error');
                        return;
                    }
                    mcaLenderId = 'custom_' + mcaLenderName.toLowerCase().replace(/\s+/g, '_');
                } else {
                    mcaLenderName = mcaLenders[mcaLenderId] || mcaLenderId;
                }
            }

            const { uniqueId, description, amount, currentClass, monthKey, sessionId } = currentAdjustmentContext;
            closeAdjustmentTypePopup();

            await submitRevenueClassification(uniqueId, description, amount, currentClass, monthKey, sessionId, isMcaFunding, mcaLenderId, mcaLenderName);
        }

        async function submitRevenueClassification(uniqueId, description, amount, currentClass, monthKey, sessionId, isMcaFunding, mcaLenderId, mcaLenderName) {
            const btn = document.querySelector('.revenue-toggle-btn-' + uniqueId);
            const textSpan = btn.querySelector('.classification-text');

            // Disable button during request
            btn.disabled = true;
            btn.style.opacity = '0.5';

            try {
                const response = await fetch('{{ route("bankstatement.toggle-revenue") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        transaction_id: 0,
                        description: description,
                        amount: amount,
                        current_classification: currentClass,
                        is_mca_funding: isMcaFunding,
                        mca_lender_id: mcaLenderId,
                        mca_lender_name: mcaLenderName
                    })
                });

                const data = await response.json();
                console.log('Server response:', data);

                if (data.success) {
                    const newClass = data.new_classification;
                    const isAdjustment = newClass === 'adjustment';

                    // Debug: Log the MCA funding check
                    console.log('MCA check - is_mca_funding:', data.is_mca_funding, 'mca_lender_id:', data.mca_lender_id, 'mca_lender_name:', data.mca_lender_name);

                    // Add custom lender to the mcaLenders list if it's new
                    if (data.is_mca_funding && data.mca_lender_id && data.mca_lender_name) {
                        if (!mcaLenders[data.mca_lender_id]) {
                            mcaLenders[data.mca_lender_id] = data.mca_lender_name;
                            console.log('Added new lender to list:', data.mca_lender_id, data.mca_lender_name);
                        } else {
                            console.log('Lender already exists:', data.mca_lender_id);
                        }
                    }

                    // Update button appearance
                    let buttonText = isAdjustment ? 'Adjustment' : 'True Revenue';
                    if (isAdjustment && data.is_mca_funding && data.mca_lender_name) {
                        buttonText = 'MCA: ' + data.mca_lender_name;
                    }
                    textSpan.textContent = buttonText;

                    // Update button classes
                    btn.classList.remove('bg-orange-100', 'text-orange-800', 'dark:bg-orange-900', 'dark:text-orange-200', 'hover:bg-orange-200');
                    btn.classList.remove('bg-green-100', 'text-green-800', 'dark:bg-green-900', 'dark:text-green-200', 'hover:bg-green-200');
                    btn.classList.remove('bg-purple-100', 'text-purple-800', 'dark:bg-purple-900', 'dark:text-purple-200', 'hover:bg-purple-200');

                    if (isAdjustment) {
                        if (data.is_mca_funding) {
                            btn.classList.add('bg-purple-100', 'text-purple-800', 'dark:bg-purple-900', 'dark:text-purple-200', 'hover:bg-purple-200');
                        } else {
                            btn.classList.add('bg-orange-100', 'text-orange-800', 'dark:bg-orange-900', 'dark:text-orange-200', 'hover:bg-orange-200');
                        }
                    } else {
                        btn.classList.add('bg-green-100', 'text-green-800', 'dark:bg-green-900', 'dark:text-green-200', 'hover:bg-green-200');
                    }

                    // Update onclick to reflect new state
                    btn.setAttribute('onclick', `toggleRevenueClass('${uniqueId}', '${description.replace(/'/g, "\\'")}', ${amount}, '${newClass}', '${monthKey}', '${sessionId}')`);

                    // Update adjustments list
                    updateAdjustmentsList(sessionId, monthKey, description, amount, currentClass, newClass);

                    // Update monthly summary in real-time
                    updateMonthlySummary(sessionId, monthKey, amount, currentClass, newClass);

                    // Update MCA Obligations section if this is MCA funding
                    if (data.is_mca_funding && data.mca_lender_id) {
                        updateMcaFundingSection(data.mca_lender_id, data.mca_lender_name, amount, true);
                    }

                    // Show success feedback
                    showToast(data.message, 'success');

                    // After marking MCA funding, check for similar transactions to batch-mark
                    if (data.is_mca_funding && data.mca_lender_id && data.mca_lender_name) {
                        setTimeout(() => {
                            const similar = findSimilarTransactions(description, data.mca_lender_id, data.mca_lender_name, uniqueId);
                            const totalSimilar = similar.credits.length + similar.debits.length;

                            if (totalSimilar > 0) {
                                showToast(`Found ${totalSimilar} similar transactions. You can batch-mark them.`, 'info');
                                setTimeout(() => {
                                    showSimilarTransactionsModal(description, data.mca_lender_id, data.mca_lender_name, amount, uniqueId);
                                }, 500);
                            }
                        }, 300);
                    }
                } else {
                    showToast('Failed to update classification', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error updating classification', 'error');
            }

            // Re-enable button
            btn.disabled = false;
            btn.style.opacity = '1';
        }

        async function toggleTransactionType(uniqueId, transactionId, currentType, description, amount, monthKey, sessionId) {
            const btn = document.querySelector('.type-toggle-btn-' + uniqueId);
            const textSpan = btn.querySelector('.type-text');
            const amountCell = btn.closest('tr').querySelector('td:nth-child(4)');

            // Check if transactionId is valid
            if (!transactionId || transactionId === 'null' || transactionId === null) {
                showToast('Transaction ID not found. Please refresh the page and try again.', 'error');
                return;
            }

            // Disable button during request
            btn.disabled = true;
            btn.style.opacity = '0.5';

            try {
                const response = await fetch('{{ route("bankstatement.toggle-type") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        transaction_id: transactionId
                    })
                });

                const data = await response.json();
                console.log('Toggle type response:', data);

                if (data.success) {
                    const newType = data.new_type;
                    const isCredit = newType === 'credit';

                    // Update button text
                    textSpan.textContent = newType.charAt(0).toUpperCase() + newType.slice(1);

                    // Update button classes
                    btn.classList.remove('bg-green-100', 'text-green-800', 'dark:bg-green-900', 'dark:text-green-200');
                    btn.classList.remove('bg-red-100', 'text-red-800', 'dark:bg-red-900', 'dark:text-red-200');

                    if (isCredit) {
                        btn.classList.add('bg-green-100', 'text-green-800', 'dark:bg-green-900', 'dark:text-green-200');
                        amountCell.classList.remove('text-red-600', 'dark:text-red-400');
                        amountCell.classList.add('text-green-600', 'dark:text-green-400');
                    } else {
                        btn.classList.add('bg-red-100', 'text-red-800', 'dark:bg-red-900', 'dark:text-red-200');
                        amountCell.classList.remove('text-green-600', 'dark:text-green-400');
                        amountCell.classList.add('text-red-600', 'dark:text-red-400');
                    }

                    // Update onclick to reflect new state
                    btn.setAttribute('onclick', `toggleTransactionType('${uniqueId}', ${transactionId}, '${newType}', '${description.replace(/'/g, "\\'")}', ${amount}, '${monthKey}', '${sessionId}')`);

                    // Update monthly summary totals
                    updateMonthlySummaryAfterTypeToggle(sessionId, monthKey, amount, currentType, newType);

                    // Update session totals if available
                    if (data.session_totals) {
                        updateSessionTotals(sessionId, data.session_totals);
                    }

                    // Show success feedback
                    showToast(data.message, 'success');
                } else {
                    const errorMsg = data.message || data.error || 'Failed to update transaction type';
                    showToast(errorMsg, 'error');
                    console.error('Server error:', data);
                }
            } catch (error) {
                console.error('Error:', error);
                const errorMsg = error.message || 'Error updating transaction type';
                showToast(errorMsg, 'error');
            }

            // Re-enable button
            btn.disabled = false;
            btn.style.opacity = '1';
        }

        function updateMonthlySummaryAfterTypeToggle(sessionId, monthKey, amount, oldType, newType) {
            console.log('Updating monthly summary:', { sessionId, monthKey, amount, oldType, newType });

            // Find the monthly summary section by looking for the grid container
            // The structure is: Month button -> Expandable div -> Summary grid (first grid after button)
            const monthCards = document.querySelectorAll(`[data-month="${monthKey}"][data-session="${sessionId}"]`);
            if (monthCards.length === 0) {
                console.log('No month card found');
                return;
            }

            // Find the summary grid - it's in the expandable section
            // Look for parent container and find the summary cards grid
            const firstCard = monthCards[0];
            const monthContainer = firstCard.closest('.border-b');
            if (!monthContainer) {
                console.log('Month container not found');
                return;
            }

            // Find the summary grid with the 4 cards (Deposits, Adjustments, True Revenue, Total Debits)
            const summaryGrid = monthContainer.querySelector('.grid.grid-cols-2.md\\:grid-cols-4');
            if (!summaryGrid) {
                console.log('Summary grid not found');
                return;
            }

            // Get the summary card elements
            const cards = summaryGrid.querySelectorAll('.bg-white.dark\\:bg-gray-700');
            if (cards.length < 4) {
                console.log('Not enough summary cards found');
                return;
            }

            // Cards order: [0] = Deposits, [1] = Adjustments, [2] = True Revenue, [3] = Total Debits
            const depositsElement = cards[0].querySelector('p.text-lg');
            const adjustmentsElement = cards[1].querySelector('p.text-lg');
            const trueRevenueElement = cards[2].querySelector('p.text-lg');
            const debitsElement = cards[3].querySelector('p.text-lg');

            if (!depositsElement || !debitsElement || !trueRevenueElement || !adjustmentsElement) {
                console.log('Could not find all required elements');
                return;
            }

            // Parse current values
            let currentDeposits = parseFloat(depositsElement.textContent.replace(/[$,]/g, '')) || 0;
            let currentDebits = parseFloat(debitsElement.textContent.replace(/[$,]/g, '')) || 0;
            let currentAdjustments = parseFloat(adjustmentsElement.textContent.replace(/[$,]/g, '')) || 0;

            // Update values based on the toggle
            if (oldType === 'credit' && newType === 'debit') {
                currentDeposits -= amount;
                currentDebits += amount;
            } else if (oldType === 'debit' && newType === 'credit') {
                currentDebits -= amount;
                currentDeposits += amount;
            }

            // Calculate new true revenue (deposits - adjustments)
            const newTrueRevenue = currentDeposits - currentAdjustments;

            // Update the display
            depositsElement.textContent = '$' + currentDeposits.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            debitsElement.textContent = '$' + currentDebits.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            trueRevenueElement.textContent = '$' + newTrueRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Also update the header true revenue display
            const headerRevenueElement = monthContainer.querySelector(`.header-rev-${sessionId}-${monthKey}`);
            if (headerRevenueElement) {
                headerRevenueElement.textContent = '$' + newTrueRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Update any elements with specific classes for this month
            const cardRevElements = document.querySelectorAll(`.card-rev-${sessionId}-${monthKey}`);
            cardRevElements.forEach(el => {
                el.textContent = '$' + newTrueRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });

            // Update the monthlyData object to keep data in sync
            const sessionData = monthlyData[sessionId];
            if (sessionData && sessionData.totals) {
                if (oldType === 'credit' && newType === 'debit') {
                    sessionData.totals.deposits -= amount;
                    sessionData.totals.debits += amount;
                } else if (oldType === 'debit' && newType === 'credit') {
                    sessionData.totals.debits -= amount;
                    sessionData.totals.deposits += amount;
                }
            }

            // Update the Combined Analysis Summary dashboard
            updateCombinedSummary();

            console.log('Monthly summary updated successfully');
        }

        function updateSessionTotals(sessionId, totals) {
            // Update the session totals in the summary section if visible
            const sessionSection = document.querySelector(`[data-session-id="${sessionId}"]`);
            if (!sessionSection) return;

            const creditTotal = sessionSection.querySelector('[data-total="credits"]');
            const debitTotal = sessionSection.querySelector('[data-total="debits"]');
            const netFlow = sessionSection.querySelector('[data-total="net_flow"]');

            if (creditTotal) creditTotal.textContent = '$' + totals.total_credits;
            if (debitTotal) debitTotal.textContent = '$' + totals.total_debits;
            if (netFlow) netFlow.textContent = '$' + totals.net_flow;
        }

        function updateAdjustmentsList(sessionId, monthKey, description, amount, oldClass, newClass) {
            const container = document.getElementById(`adjustments-container-${sessionId}-${monthKey}`);
            const list = document.getElementById(`adjustments-list-${sessionId}-${monthKey}`);

            if (!container || !list) return;

            const formatNumber = (num) => num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (newClass === 'adjustment') {
                // Add to adjustments list
                const newItem = document.createElement('div');
                newItem.className = 'flex justify-between text-xs adj-item gap-4';
                newItem.setAttribute('data-description', description);
                newItem.innerHTML = `
                    <span class="text-orange-600 dark:text-orange-400 flex-1">${description}</span>
                    <span class="text-orange-700 dark:text-orange-300 font-medium whitespace-nowrap">$${formatNumber(amount)}</span>
                `;
                list.appendChild(newItem);
                container.classList.remove('hidden');
            } else {
                // Remove from adjustments list
                const items = list.querySelectorAll('.adj-item');
                items.forEach(item => {
                    if (item.getAttribute('data-description') === description) {
                        item.remove();
                    }
                });

                // Hide container if no more items
                if (list.querySelectorAll('.adj-item').length === 0) {
                    container.classList.add('hidden');
                }
            }
        }

        function updateMonthlySummary(sessionId, monthKey, amount, oldClass, newClass) {
            // Find the session's monthly data container
            const sessionData = monthlyData[sessionId];
            if (!sessionData) return;

            // Find the month in the data
            const monthData = sessionData.months.find(m => m.month_key === monthKey);
            if (!monthData) return;

            // Update the values
            if (oldClass === 'true_revenue' && newClass === 'adjustment') {
                monthData.adjustments += amount;
                monthData.true_revenue -= amount;
            } else if (oldClass === 'adjustment' && newClass === 'true_revenue') {
                monthData.adjustments -= amount;
                monthData.true_revenue += amount;
            }

            // Update totals
            sessionData.totals.adjustments = sessionData.months.reduce((sum, m) => sum + m.adjustments, 0);
            sessionData.totals.true_revenue = sessionData.months.reduce((sum, m) => sum + m.true_revenue, 0);

            // Update averages
            const monthCount = sessionData.months.length;
            sessionData.averages.adjustments = sessionData.totals.adjustments / monthCount;
            sessionData.averages.true_revenue = sessionData.totals.true_revenue / monthCount;

            // Update average daily
            monthData.average_daily = monthData.true_revenue / monthData.days_in_month;
            sessionData.totals.average_daily = sessionData.months.reduce((sum, m) => sum + m.average_daily, 0);
            sessionData.averages.average_daily = sessionData.totals.average_daily / monthCount;

            // Re-render the monthly table for this session
            updateMonthlyTableUI(sessionId, sessionData);
        }

        function updateMonthlyTableUI(sessionId, data) {
            // Helper to format numbers
            const formatNumber = (num) => num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Update each month's row in the main table
            data.months.forEach(month => {
                const monthKey = month.month_key;

                // Update month adjustments in table
                const adjCell = document.querySelector(`.month-adj-${sessionId}-${monthKey} .adj-value`);
                if (adjCell) adjCell.textContent = formatNumber(month.adjustments);

                // Update month true revenue in table
                const revCell = document.querySelector(`.month-rev-${sessionId}-${monthKey} .rev-value`);
                if (revCell) revCell.textContent = formatNumber(month.true_revenue);

                // Update month average daily in table
                const avgCell = document.querySelector(`.month-avg-${sessionId}-${monthKey} .avg-value`);
                if (avgCell) avgCell.textContent = formatNumber(month.average_daily);

                // Update collapsible header true revenue
                const headerRev = document.querySelector(`.header-rev-${sessionId}-${monthKey}`);
                if (headerRev) headerRev.textContent = '$' + formatNumber(month.true_revenue);

                // Update month summary cards (inside collapsible)
                const cardAdj = document.querySelector(`.card-adj-${sessionId}-${monthKey}`);
                if (cardAdj) cardAdj.textContent = '$' + formatNumber(month.adjustments);

                const cardRev = document.querySelector(`.card-rev-${sessionId}-${monthKey}`);
                if (cardRev) cardRev.textContent = '$' + formatNumber(month.true_revenue);
            });

            // Update total row
            const totalAdj = document.querySelector(`.total-adj-${sessionId} .adj-value`);
            if (totalAdj) totalAdj.textContent = formatNumber(data.totals.adjustments);

            const totalRev = document.querySelector(`.total-rev-${sessionId} .rev-value`);
            if (totalRev) totalRev.textContent = formatNumber(data.totals.true_revenue);

            const totalAvg = document.querySelector(`.total-avg-${sessionId} .avg-value`);
            if (totalAvg) totalAvg.textContent = formatNumber(data.totals.average_daily);

            // Update average row
            const avgAdj = document.querySelector(`.avg-adj-${sessionId} .adj-value`);
            if (avgAdj) avgAdj.textContent = formatNumber(data.averages.adjustments);

            const avgRev = document.querySelector(`.avg-rev-${sessionId} .rev-value`);
            if (avgRev) avgRev.textContent = formatNumber(data.averages.true_revenue);

            const avgAvg = document.querySelector(`.avg-avg-${sessionId} .avg-value`);
            if (avgAvg) avgAvg.textContent = formatNumber(data.averages.average_daily);

            // Update the summary cards
            const summaryRev = document.querySelector(`.summary-rev-${sessionId}`);
            if (summaryRev) summaryRev.textContent = '$' + formatNumber(data.totals.true_revenue);

            const summaryAdj = document.querySelector(`.summary-adj-${sessionId}`);
            if (summaryAdj) {
                if (data.totals.adjustments > 0) {
                    summaryAdj.textContent = '-$' + formatNumber(data.totals.adjustments) + ' adjustments';
                } else {
                    summaryAdj.textContent = '';
                }
            }

            // Update Combined Summary section if it exists
            updateCombinedSummary();

            console.log('Updated monthly table for session:', sessionId);
        }

        function updateCombinedSummary() {
            // Recalculate combined totals from all session data
            let combinedTotals = {
                deposits: 0,
                debits: 0,
                adjustments: 0,
                true_revenue: 0
            };

            // Aggregate from all sessions
            Object.keys(monthlyData).forEach(sessionId => {
                const data = monthlyData[sessionId];
                if (data && data.totals) {
                    combinedTotals.deposits += data.totals.deposits || 0;
                    combinedTotals.debits += data.totals.debits || 0;
                    combinedTotals.adjustments += data.totals.adjustments || 0;
                    combinedTotals.true_revenue += data.totals.true_revenue || 0;
                }
            });

            // Helper to format numbers
            const formatNumber = (num) => num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Update combined table total row
            const combinedTotalAdj = document.querySelector('.combined-total-adj .adj-value');
            if (combinedTotalAdj) combinedTotalAdj.textContent = formatNumber(combinedTotals.adjustments);

            const combinedTotalRev = document.querySelector('.combined-total-rev .rev-value');
            if (combinedTotalRev) combinedTotalRev.textContent = formatNumber(combinedTotals.true_revenue);

            // Update combined summary metrics
            const combinedMetricDeposits = document.querySelector('.combined-metric-deposits');
            if (combinedMetricDeposits) combinedMetricDeposits.textContent = '$' + formatNumber(combinedTotals.deposits);

            const combinedMetricDebits = document.querySelector('.combined-metric-debits');
            if (combinedMetricDebits) combinedMetricDebits.textContent = '$' + formatNumber(combinedTotals.debits);

            const combinedMetricAdj = document.querySelector('.combined-metric-adj');
            if (combinedMetricAdj) combinedMetricAdj.textContent = '$' + formatNumber(combinedTotals.adjustments);

            const combinedMetricRev = document.querySelector('.combined-metric-rev');
            if (combinedMetricRev) combinedMetricRev.textContent = '$' + formatNumber(combinedTotals.true_revenue);

            // Update combined summary cards
            const combinedCardDeposits = document.querySelector('.combined-card-deposits');
            if (combinedCardDeposits) combinedCardDeposits.textContent = '$' + formatNumber(combinedTotals.deposits);

            const combinedCardDebits = document.querySelector('.combined-card-debits');
            if (combinedCardDebits) combinedCardDebits.textContent = '$' + formatNumber(combinedTotals.debits);

            const combinedCardAdj = document.querySelector('.combined-card-adj');
            if (combinedCardAdj) combinedCardAdj.textContent = '$' + formatNumber(combinedTotals.adjustments);

            const combinedCardRev = document.querySelector('.combined-card-rev');
            if (combinedCardRev) combinedCardRev.textContent = '$' + formatNumber(combinedTotals.true_revenue);

            // Update individual month rows in combined table
            // Group by month across all sessions
            let combinedMonths = {};
            Object.keys(monthlyData).forEach(sessionId => {
                const data = monthlyData[sessionId];
                if (data && data.months) {
                    data.months.forEach(month => {
                        const key = month.month_key;
                        if (!combinedMonths[key]) {
                            combinedMonths[key] = {
                                adjustments: 0,
                                true_revenue: 0,
                                average_daily: 0,
                                days_in_month: month.days_in_month
                            };
                        }
                        combinedMonths[key].adjustments += month.adjustments;
                        combinedMonths[key].true_revenue += month.true_revenue;
                    });
                }
            });

            // Update each combined month row
            Object.keys(combinedMonths).forEach(monthKey => {
                const month = combinedMonths[monthKey];
                month.average_daily = month.days_in_month > 0 ? month.true_revenue / month.days_in_month : 0;

                const adjCell = document.querySelector(`.combined-adj-${monthKey} .adj-value`);
                if (adjCell) adjCell.textContent = formatNumber(month.adjustments);

                const revCell = document.querySelector(`.combined-rev-${monthKey} .rev-value`);
                if (revCell) revCell.textContent = formatNumber(month.true_revenue);

                const avgCell = document.querySelector(`.combined-avg-${monthKey} .avg-value`);
                if (avgCell) avgCell.textContent = formatNumber(month.average_daily);
            });
        }

        let currentMcaContext = null;

        function showMcaDropdown(uniqueId, description, amount, isMca, currentLender, currentLenderId, monthKey, sessionId, transactionId) {
            // Debug: Log current state of mcaLenders
            console.log('=== showMcaDropdown called ===');
            console.log('Opening MCA dropdown - mcaLenders has', Object.keys(mcaLenders).length, 'lenders:', Object.keys(mcaLenders));
            console.log('Raw transaction ID received:', transactionId, 'Type:', typeof transactionId);

            // Parse transaction ID to ensure it's a valid number
            const parsedTransactionId = transactionId && transactionId !== 'null' && transactionId !== null ? parseInt(transactionId) : null;
            console.log('Parsed transaction ID:', parsedTransactionId, 'Type:', typeof parsedTransactionId);

            // Remove any existing dropdown
            const existingDropdown = document.getElementById('mca-dropdown');
            if (existingDropdown) existingDropdown.remove();

            currentMcaContext = {
                uniqueId,
                description,
                amount,
                isMca,
                currentLender,
                currentLenderId,
                monthKey,
                sessionId,
                transactionId: parsedTransactionId
            };

            console.log('Stored context:', currentMcaContext);

            const btn = document.querySelector('.mca-toggle-btn-' + uniqueId);

            // Create dropdown
            const dropdown = document.createElement('div');
            dropdown.id = 'mca-dropdown';
            dropdown.className = 'fixed z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl p-3 w-64';

            // Position dropdown - if button exists, position relative to it, otherwise center on screen
            if (btn) {
                const rect = btn.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + 5) + 'px';
                dropdown.style.left = Math.min(rect.left, window.innerWidth - 280) + 'px';
            } else {
                // Center on screen if button not found
                dropdown.style.top = '50%';
                dropdown.style.left = '50%';
                dropdown.style.transform = 'translate(-50%, -50%)';
            }

            let optionsHtml = '';
            for (const [id, name] of Object.entries(mcaLenders)) {
                optionsHtml += `<option value="${id}" ${currentLenderId === id ? 'selected' : ''}>${name}</option>`;
            }

            dropdown.innerHTML = `
                <div class="mb-2">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Select MCA Lender</label>
                    <select id="mca-lender-select" class="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5">
                        <option value="">-- Select Lender --</option>
                        ${optionsHtml}
                        <option value="custom">+ Add Custom Lender</option>
                    </select>
                </div>
                <div id="custom-lender-input" class="mb-2 hidden">
                    <input type="text" id="custom-lender-name" placeholder="Enter lender name" class="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5">
                </div>
                <div class="flex gap-2">
                    <button onclick="saveMcaSelection()" class="flex-1 px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700">
                        Mark as MCA
                    </button>
                    ${isMca ? `<button onclick="removeMcaMarking()" class="flex-1 px-3 py-1.5 bg-gray-500 text-white text-xs font-medium rounded hover:bg-gray-600">Remove</button>` : ''}
                    <button onclick="closeMcaDropdown()" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-medium rounded hover:bg-gray-300">
                        Cancel
                    </button>
                </div>
            `;

            document.body.appendChild(dropdown);

            // Handle custom lender selection
            document.getElementById('mca-lender-select').addEventListener('change', function() {
                const customInput = document.getElementById('custom-lender-input');
                if (this.value === 'custom') {
                    customInput.classList.remove('hidden');
                } else {
                    customInput.classList.add('hidden');
                }
            });

            // Close on outside click
            setTimeout(() => {
                document.addEventListener('click', closeMcaDropdownOnOutsideClick);
            }, 100);
        }

        function closeMcaDropdownOnOutsideClick(e) {
            const dropdown = document.getElementById('mca-dropdown');
            if (dropdown && !dropdown.contains(e.target) && !e.target.classList.contains('mca-toggle-btn-' + currentMcaContext?.uniqueId)) {
                closeMcaDropdown();
            }
        }

        function closeMcaDropdown() {
            const dropdown = document.getElementById('mca-dropdown');
            if (dropdown) dropdown.remove();
            document.removeEventListener('click', closeMcaDropdownOnOutsideClick);
            currentMcaContext = null;
        }

        window.saveMcaSelection = async function() {
            if (!currentMcaContext) return;

            const select = document.getElementById('mca-lender-select');
            let lenderId = select.value;
            let lenderName = '';

            if (!lenderId) {
                showToast('Please select a lender', 'error');
                return;
            }

            if (lenderId === 'custom') {
                lenderName = document.getElementById('custom-lender-name').value.trim();
                if (!lenderName) {
                    showToast('Please enter lender name', 'error');
                    return;
                }
                lenderId = 'custom_' + lenderName.toLowerCase().replace(/\s+/g, '_');
            } else {
                lenderName = mcaLenders[lenderId] || lenderId;
            }

            // Store context before closing dropdown
            const context = { ...currentMcaContext };

            closeMcaDropdown();

            // Show confirmation dialog asking if user wants to scan for similar transactions
            showMcaScanConfirmation(context, lenderId, lenderName);
        }

        // Show confirmation dialog for scanning similar transactions
        function showMcaScanConfirmation(context, lenderId, lenderName) {
            const modal = document.getElementById('mca-scan-confirmation-modal');
            const lenderNameSpan = document.getElementById('mca-scan-lender-name');
            const descriptionSpan = document.getElementById('mca-scan-description');

            lenderNameSpan.textContent = lenderName;
            descriptionSpan.textContent = context.description.substring(0, 80) + (context.description.length > 80 ? '...' : '');

            // Store context for later use
            window.pendingScanContext = { context, lenderId, lenderName };

            modal.classList.remove('hidden');
        }

        window.confirmMcaScan = async function() {
            const modal = document.getElementById('mca-scan-confirmation-modal');
            modal.classList.add('hidden');

            if (!window.pendingScanContext) return;

            const { context, lenderId, lenderName } = window.pendingScanContext;

            console.log('Starting scan for similar transactions...', {
                description: context.description,
                lenderId: lenderId,
                lenderName: lenderName
            });

            try {
                // Show similar transactions modal
                const result = await window.showSimilarMcaModal(
                    context.description,
                    'debit',
                    lenderId,
                    lenderName,
                    true,
                    context
                );

                console.log('Scan result:', result);

                // If no similar transactions found, proceed directly with single transaction
                if (!result || result.count === 0) {
                    console.log('No similar transactions found, updating single transaction');
                    currentMcaContext = context;
                    await toggleMcaStatusDirect(true, lenderId, lenderName, true);
                }
            } catch (error) {
                console.error('Error during scan:', error);
                showToast('Error scanning for similar transactions: ' + error.message, 'error');

                // Still update the current transaction on error
                currentMcaContext = context;
                await toggleMcaStatusDirect(true, lenderId, lenderName, true);
            }

            window.pendingScanContext = null;
        }

        window.skipMcaScan = async function() {
            console.log('=== skipMcaScan called ===');
            const modal = document.getElementById('mca-scan-confirmation-modal');
            modal.classList.add('hidden');

            if (!window.pendingScanContext) {
                console.error('No pendingScanContext found!');
                return;
            }

            const { context, lenderId, lenderName } = window.pendingScanContext;

            console.log('Context:', context);
            console.log('Context has transactionId?', context.transactionId);
            console.log('Full context object:', JSON.stringify(context, null, 2));

            // Mark only the current transaction (pass true for singleTransactionOnly)
            currentMcaContext = context;
            console.log('Calling toggleMcaStatusDirect with singleTransactionOnly=true');
            await toggleMcaStatusDirect(true, lenderId, lenderName, true);

            window.pendingScanContext = null;
        }

        window.closeMcaScanConfirmation = function() {
            const modal = document.getElementById('mca-scan-confirmation-modal');
            modal.classList.add('hidden');
            window.pendingScanContext = null;
        }

        window.removeMcaMarking = async function() {
            if (!currentMcaContext) return;

            // Store context before closing dropdown (closeMcaDropdown sets currentMcaContext to null)
            const context = { ...currentMcaContext };

            closeMcaDropdown();

            // For removal, show simple confirmation dialog first
            if (!confirm('Remove MCA marking from all similar transactions with this description?')) {
                return;
            }

            // Temporarily restore context for the API call
            currentMcaContext = context;

            // Proceed with removal for all matching transactions
            await toggleMcaStatusDirect(false, context.currentLenderId || '', context.currentLender || '');
        }

        async function toggleMcaStatusDirect(isMca, lenderId, lenderName, singleTransactionOnly = false) {
            console.log('=== toggleMcaStatusDirect called ===');
            console.log('Parameters:', { isMca, lenderId, lenderName, singleTransactionOnly });
            console.log('currentMcaContext:', currentMcaContext);

            const { uniqueId, description, amount, monthKey, sessionId, transactionId } = currentMcaContext;

            console.log('Destructured values:', { uniqueId, description, amount, monthKey, sessionId, transactionId });
            console.log('transactionId type:', typeof transactionId);
            console.log('transactionId value:', transactionId);

            const btn = document.querySelector('.mca-toggle-btn-' + uniqueId);
            const textSpan = btn.querySelector('.mca-text');

            closeMcaDropdown();

            btn.disabled = true;
            btn.style.opacity = '0.5';

            // Build request body
            const requestBody = {
                description: description,
                amount: amount,
                is_mca: isMca,
                lender_id: lenderId,
                lender_name: lenderName
            };

            // If updating single transaction only, include the transaction ID
            if (singleTransactionOnly && transactionId) {
                requestBody.transaction_ids = [transactionId];
                console.log('✅ Updating single transaction ID:', transactionId);
            } else {
                console.warn('⚠️ Updating all matching transactions by description');
                console.log('Reason: singleTransactionOnly=', singleTransactionOnly, 'transactionId=', transactionId);
            }

            console.log('Final requestBody being sent:', JSON.stringify(requestBody, null, 2));

            try {
                const response = await fetch('{{ route("bankstatement.toggle-mca") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                const data = await response.json();

                if (data.success) {
                    // Add custom lender to the mcaLenders list if it's new
                    if (isMca && lenderId && lenderName && !mcaLenders[lenderId]) {
                        mcaLenders[lenderId] = lenderName;
                        console.log('Added new lender to list:', lenderId, lenderName);
                    }

                    showToast(data.message || `✓ Updated ${data.updated_count || 1} transaction(s)`, 'success');

                    // Reload page to show all updated transactions
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast('Failed to update MCA status', 'error');
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error updating MCA status', 'error');
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        }

        // Track MCA data for real-time updates
        let combinedMcaData = {
            lenders: {},
            totalCount: 0,
            totalPayments: 0,
            totalAmount: 0
        };

        // Initialize from existing data on page load
        document.addEventListener('DOMContentLoaded', function() {
            const tbody = document.getElementById('combined-mca-lenders-tbody');
            if (tbody) {
                tbody.querySelectorAll('.mca-lender-row').forEach(row => {
                    const lenderId = row.dataset.lenderId;
                    const paymentCount = parseInt(row.querySelector('.lender-payment-count').textContent) || 0;
                    const totalAmountText = row.querySelector('.lender-total-amount').textContent.replace(/[$,]/g, '');
                    const totalAmount = parseFloat(totalAmountText) || 0;

                    combinedMcaData.lenders[lenderId] = {
                        name: row.querySelector('.lender-name').textContent,
                        paymentCount: paymentCount,
                        totalAmount: totalAmount
                    };
                    combinedMcaData.totalPayments += paymentCount;
                    combinedMcaData.totalAmount += totalAmount;
                });
                combinedMcaData.totalCount = Object.keys(combinedMcaData.lenders).length;
            }
        });

        function updateMcaSummary(sessionId, isMca, lenderId, lenderName, amount, description) {
            const section = document.getElementById('combined-mca-section');
            const amountNum = parseFloat(amount) || 0;

            if (isMca) {
                // Adding an MCA
                if (!combinedMcaData.lenders[lenderId]) {
                    // New lender
                    combinedMcaData.lenders[lenderId] = {
                        name: lenderName,
                        paymentCount: 1,
                        totalAmount: amountNum
                    };
                    combinedMcaData.totalCount++;

                    // Add row to table
                    addLenderRow(lenderId, lenderName, 1, amountNum);
                } else {
                    // Existing lender - increment
                    combinedMcaData.lenders[lenderId].paymentCount++;
                    combinedMcaData.lenders[lenderId].totalAmount += amountNum;

                    // Update existing row
                    updateLenderRow(lenderId);
                }

                combinedMcaData.totalPayments++;
                combinedMcaData.totalAmount += amountNum;

                // Show section if it was hidden
                if (section) {
                    section.classList.remove('hidden');
                }
            } else {
                // Removing an MCA
                if (combinedMcaData.lenders[lenderId]) {
                    combinedMcaData.lenders[lenderId].paymentCount--;
                    combinedMcaData.lenders[lenderId].totalAmount -= amountNum;
                    combinedMcaData.totalPayments--;
                    combinedMcaData.totalAmount -= amountNum;

                    if (combinedMcaData.lenders[lenderId].paymentCount <= 0) {
                        // Remove lender completely
                        delete combinedMcaData.lenders[lenderId];
                        combinedMcaData.totalCount--;
                        removeLenderRow(lenderId);
                    } else {
                        updateLenderRow(lenderId);
                    }

                    // Hide section if no more MCAs
                    if (combinedMcaData.totalCount <= 0 && section) {
                        section.classList.add('hidden');
                    }
                }
            }

            // Update summary cards
            updateMcaSummaryCards();
        }

        function addLenderRow(lenderId, lenderName, paymentCount, totalAmount) {
            const tbody = document.getElementById('combined-mca-lenders-tbody');
            if (!tbody) return;

            const avgPayment = paymentCount > 0 ? totalAmount / paymentCount : 0;
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 mca-lender-row';
            row.dataset.lenderId = lenderId;
            row.innerHTML = `
                <td class="px-4 py-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="font-medium text-gray-900 dark:text-gray-100 lender-name">${lenderName}</p>
                    </div>
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold lender-total-funding text-gray-400 dark:text-gray-500">-</td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                        Single Payment
                    </span>
                </td>
                <td class="px-4 py-3 text-center text-sm font-medium text-gray-900 dark:text-gray-100 lender-payment-count">${paymentCount}</td>
                <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300 lender-avg-payment">$${avgPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="px-4 py-3 text-right text-sm font-semibold text-red-600 dark:text-red-400 lender-total-amount">$${totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            `;
            tbody.appendChild(row);
        }

        function updateLenderRow(lenderId) {
            const row = document.querySelector(`.mca-lender-row[data-lender-id="${lenderId}"]`);
            if (!row || !combinedMcaData.lenders[lenderId]) return;

            const data = combinedMcaData.lenders[lenderId];
            const avgPayment = data.paymentCount > 0 ? data.totalAmount / data.paymentCount : 0;

            row.querySelector('.lender-payment-count').textContent = data.paymentCount;
            row.querySelector('.lender-avg-payment').textContent = '$' + avgPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            row.querySelector('.lender-total-amount').textContent = '$' + data.totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function removeLenderRow(lenderId) {
            const row = document.querySelector(`.mca-lender-row[data-lender-id="${lenderId}"]`);
            if (row) row.remove();
        }

        function updateMcaSummaryCards() {
            const activeCount = document.getElementById('combined-mca-active-count');
            const paymentCount = document.getElementById('combined-mca-payment-count');
            const totalAmount = document.getElementById('combined-mca-total-amount');
            const lenderCount = document.getElementById('combined-mca-lender-count');
            const footerPayments = document.getElementById('combined-mca-footer-payments');
            const footerAmount = document.getElementById('combined-mca-footer-amount');
            const fundingAmount = document.getElementById('combined-mca-funding-amount');
            const footerFunding = document.getElementById('combined-mca-footer-funding');

            if (activeCount) activeCount.textContent = combinedMcaData.totalCount;
            if (paymentCount) paymentCount.textContent = combinedMcaData.totalPayments;
            if (totalAmount) totalAmount.textContent = '$' + combinedMcaData.totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            if (lenderCount) lenderCount.textContent = combinedMcaData.totalCount + ' ' + (combinedMcaData.totalCount === 1 ? 'Lender' : 'Lenders');
            if (footerPayments) footerPayments.textContent = combinedMcaData.totalPayments;
            if (footerAmount) footerAmount.textContent = '$' + combinedMcaData.totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            if (fundingAmount) fundingAmount.textContent = '$' + (combinedMcaData.totalFunding || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            if (footerFunding) footerFunding.textContent = '$' + (combinedMcaData.totalFunding || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Update MCA funding section when a credit is marked as MCA funding
        function updateMcaFundingSection(lenderId, lenderName, amount, isAdding) {
            const section = document.getElementById('combined-mca-section');
            const amountNum = parseFloat(amount) || 0;

            // Initialize funding tracking if not exists
            if (!combinedMcaData.totalFunding) combinedMcaData.totalFunding = 0;

            if (isAdding) {
                // Adding MCA funding
                if (!combinedMcaData.lenders[lenderId]) {
                    // New lender - add to table
                    combinedMcaData.lenders[lenderId] = {
                        name: lenderName,
                        paymentCount: 0,
                        totalAmount: 0,
                        fundingCount: 1,
                        totalFunding: amountNum
                    };
                    combinedMcaData.totalCount++;
                    addLenderRowWithFunding(lenderId, lenderName, 0, 0, amountNum);
                } else {
                    // Existing lender - update funding
                    if (!combinedMcaData.lenders[lenderId].fundingCount) {
                        combinedMcaData.lenders[lenderId].fundingCount = 0;
                        combinedMcaData.lenders[lenderId].totalFunding = 0;
                    }
                    combinedMcaData.lenders[lenderId].fundingCount++;
                    combinedMcaData.lenders[lenderId].totalFunding += amountNum;
                    updateLenderFundingInRow(lenderId);
                }

                combinedMcaData.totalFunding += amountNum;

                // Show section if hidden
                if (section) section.classList.remove('hidden');
            }

            // Update summary cards
            updateMcaSummaryCards();
        }

        function addLenderRowWithFunding(lenderId, lenderName, paymentCount, totalPaid, totalFunding) {
            const tbody = document.getElementById('combined-mca-lenders-tbody');
            if (!tbody) return;

            const avgPayment = paymentCount > 0 ? totalPaid / paymentCount : 0;
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 mca-lender-row';
            row.dataset.lenderId = lenderId;
            row.innerHTML = `
                <td class="px-4 py-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="font-medium text-gray-900 dark:text-gray-100 lender-name">${lenderName}</p>
                    </div>
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold lender-total-funding ${totalFunding > 0 ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400 dark:text-gray-500'}">
                    ${totalFunding > 0 ? '$' + totalFunding.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                        No Payments Yet
                    </span>
                </td>
                <td class="px-4 py-3 text-center text-sm font-medium text-gray-900 dark:text-gray-100 lender-payment-count">${paymentCount}</td>
                <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300 lender-avg-payment">$${avgPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="px-4 py-3 text-right text-sm font-semibold text-red-600 dark:text-red-400 lender-total-amount">$${totalPaid.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            `;
            tbody.appendChild(row);
        }

        function updateLenderFundingInRow(lenderId) {
            const row = document.querySelector(`.mca-lender-row[data-lender-id="${lenderId}"]`);
            if (!row || !combinedMcaData.lenders[lenderId]) return;

            const data = combinedMcaData.lenders[lenderId];
            const fundingCell = row.querySelector('.lender-total-funding');

            if (fundingCell) {
                const funding = data.totalFunding || 0;
                fundingCell.textContent = funding > 0 ? '$' + funding.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-';
                fundingCell.className = `px-4 py-3 text-right text-sm font-semibold lender-total-funding ${funding > 0 ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400 dark:text-gray-500'}`;
            }
        }

        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white text-sm font-medium z-50 transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' :
                type === 'error' ? 'bg-red-600' : 'bg-blue-600'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);

            // Animate in
            setTimeout(() => toast.classList.add('translate-y-0', 'opacity-100'), 10);

            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ========================================
        // MCA OFFER CALCULATOR FUNCTIONS
        // ========================================

        // Get all session UUIDs for save/load functionality
        const mcaCalcSessionUuids = @json($allSessionIds);
        const mcaCalcPrimarySessionUuid = mcaCalcSessionUuids[0] || '';

        // Initialize calculator on page load
        document.addEventListener('DOMContentLoaded', function() {
            recalculateOffer();
            loadSavedOffers();
        });

        function getTrueRevenue() {
            const overrideToggle = document.getElementById('mca-calc-override-toggle');
            if (overrideToggle && overrideToggle.checked) {
                const overrideValue = parseFloat(document.getElementById('mca-calc-override-value').value) || 0;
                return overrideValue;
            }
            return parseFloat(document.getElementById('mca-calc-true-revenue-value').value) || 0;
        }

        function toggleRevenueOverride() {
            const overrideToggle = document.getElementById('mca-calc-override-toggle');
            const overrideInput = document.getElementById('mca-calc-override-input');
            const revenueDisplay = document.getElementById('mca-calc-true-revenue');

            if (overrideToggle.checked) {
                overrideInput.classList.remove('hidden');
                revenueDisplay.classList.add('text-gray-400', 'line-through');
                revenueDisplay.classList.remove('text-green-600', 'dark:text-green-400');
            } else {
                overrideInput.classList.add('hidden');
                revenueDisplay.classList.remove('text-gray-400', 'line-through');
                revenueDisplay.classList.add('text-green-600', 'dark:text-green-400');
            }
            recalculateOffer();
        }

        function updateWithholdPercent(value) {
            document.getElementById('mca-calc-withhold-percent').value = value;
            recalculateOffer();
        }

        function updateWithholdSlider(value) {
            document.getElementById('mca-calc-withhold-slider').value = value;
            recalculateOffer();
        }

        function updateFactorRate(value) {
            document.getElementById('mca-calc-factor-rate').value = value;
            recalculateOffer();
        }

        function updateFactorSlider(value) {
            document.getElementById('mca-calc-factor-slider').value = value;
            recalculateOffer();
        }

        // Switch Term Type (Daily, Weekly, BiWeekly, Monthly)
        function switchTermType(type) {
            // Hide all dropdowns
            document.getElementById('term-dropdown-daily').classList.add('hidden');
            document.getElementById('term-dropdown-weekly').classList.add('hidden');
            document.getElementById('term-dropdown-biweekly').classList.add('hidden');
            document.getElementById('term-dropdown-monthly').classList.add('hidden');

            // Remove all label highlights
            document.querySelectorAll('input[name="term-type"]').forEach(radio => {
                const label = radio.closest('label');
                label.classList.remove('bg-blue-50', 'dark:bg-blue-900/30', 'border-blue-500');
                const span = label.querySelector('span');
                span.classList.remove('font-medium', 'text-blue-700', 'dark:text-blue-300');
                span.classList.add('text-gray-700', 'dark:text-gray-300');
            });

            // Show selected dropdown and highlight label
            const selectedRadio = document.querySelector(`input[name="term-type"][value="${type}"]`);
            const selectedLabel = selectedRadio.closest('label');
            selectedLabel.classList.add('bg-blue-50', 'dark:bg-blue-900/30', 'border-blue-500');
            const selectedSpan = selectedLabel.querySelector('span');
            selectedSpan.classList.add('font-medium', 'text-blue-700', 'dark:text-blue-300');
            selectedSpan.classList.remove('text-gray-700', 'dark:text-gray-300');

            document.getElementById(`term-dropdown-${type}`).classList.remove('hidden');

            // Recalculate offer
            recalculateOffer();
        }

        // Get Term in Months (convert from selected payment frequency)
        function getTermInMonths() {
            const termType = document.querySelector('input[name="term-type"]:checked').value;

            switch(termType) {
                case 'daily':
                    const daily = parseInt(document.getElementById('mca-calc-term-daily').value) || 180;
                    return daily / 21.67; // Convert daily payments to months (~21.67 business days per month)
                case 'weekly':
                    const weekly = parseInt(document.getElementById('mca-calc-term-weekly').value) || 36;
                    return weekly / 4.33; // Convert weekly payments to months (4.33 weeks = 1 month)
                case 'biweekly':
                    const biweekly = parseInt(document.getElementById('mca-calc-term-biweekly').value) || 18;
                    return biweekly / 2.17; // Convert bi-weekly payments to months (2.17 payments = 1 month)
                case 'monthly':
                default:
                    return parseInt(document.getElementById('mca-calc-term-monthly').value) || 9;
            }
        }

        // Get Term Display Text
        function getTermDisplayText() {
            const termType = document.querySelector('input[name="term-type"]:checked').value;

            switch(termType) {
                case 'daily':
                    const daily = parseInt(document.getElementById('mca-calc-term-daily').value) || 180;
                    return `${daily} daily payments`;
                case 'weekly':
                    const weekly = parseInt(document.getElementById('mca-calc-term-weekly').value) || 36;
                    return `${weekly} weekly payments`;
                case 'biweekly':
                    const biweekly = parseInt(document.getElementById('mca-calc-term-biweekly').value) || 18;
                    return `${biweekly} bi-weekly payments`;
                case 'monthly':
                default:
                    const monthly = parseInt(document.getElementById('mca-calc-term-monthly').value) || 9;
                    return `${monthly} monthly ${monthly === 1 ? 'payment' : 'payments'}`;
            }
        }

        function recalculateOffer() {
            // Check if any overrides are active, if so, route to appropriate function
            const withholdOverride = document.getElementById('mca-calc-withhold-override-toggle').checked;
            const fundedOverride = document.getElementById('mca-calc-funded-override-toggle').checked;

            if (withholdOverride) {
                recalculateWithWithholdOverride();
                return;
            }

            if (fundedOverride) {
                recalculateWithFundedOverride();
                return;
            }

            const trueRevenue = getTrueRevenue();
            const existingPayment = parseFloat(document.getElementById('mca-calc-existing-payment').value) || 0;
            const withholdPercent = parseFloat(document.getElementById('mca-calc-withhold-percent').value) || 20;
            const factorRate = parseFloat(document.getElementById('mca-calc-factor-rate').value) || 1.30;
            const termMonths = getTermInMonths();

            // Calculate cap amount and new payment available
            const capAmount = trueRevenue * (withholdPercent / 100);
            const newPaymentAvailable = Math.max(0, capAmount - existingPayment);

            // Calculate funded amount based on withhold constraint
            const fundedAmount = (newPaymentAvailable * termMonths) / factorRate;

            // Calculate offer details: Total Payback = Funded Amount × Factor Rate
            const totalPayback = fundedAmount * factorRate;
            const monthlyPayment = termMonths > 0 ? totalPayback / termMonths : 0;
            const weeklyPayment = monthlyPayment / 4.33; // ~4.33 weeks per month
            const dailyPayment = monthlyPayment / 21.67; // ~21.67 business days per month

            // Calculate withhold utilization
            const totalMonthlyPayment = existingPayment + monthlyPayment;
            const withholdUtilization = capAmount > 0 ? (totalMonthlyPayment / capAmount) * 100 : 0;

            // Update UI
            document.getElementById('mca-calc-cap-amount').textContent = formatCurrency(capAmount);
            document.getElementById('mca-calc-new-payment').textContent = formatCurrency(newPaymentAvailable);
            document.getElementById('mca-calc-funded-amount').textContent = formatCurrency(fundedAmount);
            document.getElementById('mca-calc-result-factor').textContent = factorRate.toFixed(2);
            document.getElementById('mca-calc-result-payback').textContent = formatCurrency(totalPayback);
            document.getElementById('mca-calc-result-monthly').textContent = formatCurrency(monthlyPayment);
            document.getElementById('mca-calc-result-weekly').textContent = formatCurrency(weeklyPayment);
            document.getElementById('mca-calc-result-daily').textContent = formatCurrency(dailyPayment);
            document.getElementById('mca-calc-result-term').textContent = getTermDisplayText();

            // Update utilization bar
            const utilizationBar = document.getElementById('mca-calc-utilization-bar');
            const utilizationText = document.getElementById('mca-calc-utilization-text');
            utilizationText.textContent = Math.min(withholdUtilization, 100).toFixed(1) + '%';
            utilizationBar.style.width = Math.min(withholdUtilization, 100) + '%';

            // Update utilization bar color
            if (withholdUtilization <= 50) {
                utilizationBar.className = 'h-full bg-green-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-green-600 dark:text-green-400';
            } else if (withholdUtilization <= 80) {
                utilizationBar.className = 'h-full bg-yellow-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-yellow-600 dark:text-yellow-400';
            } else if (withholdUtilization <= 100) {
                utilizationBar.className = 'h-full bg-orange-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-orange-600 dark:text-orange-400';
            } else {
                utilizationBar.className = 'h-full bg-red-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-red-600 dark:text-red-400';
            }

            // Show/hide warning
            const warningDiv = document.getElementById('mca-calc-warning');
            const warningText = document.getElementById('mca-calc-warning-text');

            if (withholdUtilization > 100) {
                warningDiv.classList.remove('hidden');
                warningText.textContent = `Total withhold utilization (${withholdUtilization.toFixed(1)}%) exceeds 100%. This offer may not be viable.`;
            } else if (fundedAmount <= 0 && trueRevenue > 0) {
                warningDiv.classList.remove('hidden');
                warningText.textContent = `No funding capacity available. Existing MCA payments consume the full withhold capacity.`;
            } else {
                warningDiv.classList.add('hidden');
            }
        }

        function formatCurrency(value) {
            return '$' + value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function resetCalculator() {
            // Reset revenue override
            document.getElementById('mca-calc-override-toggle').checked = false;
            document.getElementById('mca-calc-override-value').value = '';
            document.getElementById('mca-calc-override-input').classList.add('hidden');
            document.getElementById('mca-calc-true-revenue').classList.remove('text-gray-400', 'line-through');
            document.getElementById('mca-calc-true-revenue').classList.add('text-green-600', 'dark:text-green-400');

            // Reset funded amount override
            document.getElementById('mca-calc-funded-override-toggle').checked = false;
            document.getElementById('mca-calc-funded-override-value').value = '';
            document.getElementById('mca-calc-funded-override-input').classList.add('hidden');
            document.getElementById('mca-calc-funded-amount').classList.remove('text-gray-400', 'line-through');
            document.getElementById('funded-amount-subtitle').textContent = 'Based on withhold constraint';

            // Reset withhold percentage override
            document.getElementById('mca-calc-withhold-override-toggle').checked = false;
            document.getElementById('mca-calc-withhold-override-value').value = '';
            document.getElementById('mca-calc-withhold-override-input').classList.add('hidden');
            document.getElementById('withhold-slider-container').classList.remove('opacity-50', 'pointer-events-none');

            // Reset other values
            document.getElementById('mca-calc-withhold-percent').value = 20;
            document.getElementById('mca-calc-withhold-slider').value = 20;
            document.getElementById('mca-calc-factor-rate').value = 1.30;
            document.getElementById('mca-calc-factor-slider').value = 1.30;

            // Reset term type to monthly
            document.querySelector('input[name="term-type"][value="monthly"]').checked = true;
            switchTermType('monthly');
            document.getElementById('mca-calc-term-monthly').value = 9;

            recalculateOffer();
        }

        // Toggle Funded Amount Override
        function toggleFundedOverride() {
            const toggle = document.getElementById('mca-calc-funded-override-toggle');
            const input = document.getElementById('mca-calc-funded-override-input');
            const fundedAmountDisplay = document.getElementById('mca-calc-funded-amount');
            const subtitle = document.getElementById('funded-amount-subtitle');

            if (toggle.checked) {
                input.classList.remove('hidden');
                fundedAmountDisplay.classList.add('text-gray-400', 'line-through');
                subtitle.textContent = 'Manual override active';
            } else {
                input.classList.add('hidden');
                fundedAmountDisplay.classList.remove('text-gray-400', 'line-through');
                subtitle.textContent = 'Based on withhold constraint';
                document.getElementById('mca-calc-funded-override-value').value = '';
                recalculateOffer();
            }
        }

        // Recalculate with Funded Amount Override
        function recalculateWithFundedOverride() {
            const override = document.getElementById('mca-calc-funded-override-toggle').checked;

            if (!override) {
                // Check if withhold is overridden
                const withholdOverride = document.getElementById('mca-calc-withhold-override-toggle').checked;
                if (withholdOverride) {
                    recalculateWithWithholdOverride();
                } else {
                    recalculateOffer();
                }
                return;
            }

            const manualFundedAmount = parseFloat(document.getElementById('mca-calc-funded-override-value').value) || 0;
            const trueRevenue = getTrueRevenue();
            const existingPayment = parseFloat(document.getElementById('mca-calc-existing-payment').value) || 0;
            const factorRate = parseFloat(document.getElementById('mca-calc-factor-rate').value) || 1.30;
            const termMonths = getTermInMonths();

            // Use manual funded amount instead of calculated
            const fundedAmount = manualFundedAmount;

            // Calculate offer details based on manual funded amount
            const totalPayback = fundedAmount * factorRate;
            const monthlyPayment = termMonths > 0 ? totalPayback / termMonths : 0;

            // Calculate required withhold percentage to support this funded amount
            const totalMonthlyPayment = existingPayment + monthlyPayment;
            const requiredWithholdPercent = trueRevenue > 0 ? (totalMonthlyPayment / trueRevenue) * 100 : 0;

            // Check if withhold percentage is manually overridden
            const withholdOverride = document.getElementById('mca-calc-withhold-override-toggle').checked;
            let withholdPercent;

            if (withholdOverride) {
                // If withhold is manually overridden, use the custom value
                withholdPercent = parseFloat(document.getElementById('mca-calc-withhold-override-value').value) || 20;
            } else {
                // Auto-update withhold slider to match the required percentage
                withholdPercent = requiredWithholdPercent;

                // Update the slider and input to show the calculated withhold percentage
                // Clamp between 5-25 for slider, but allow higher values in the input
                const sliderValue = Math.min(Math.max(requiredWithholdPercent, 5), 25);
                document.getElementById('mca-calc-withhold-slider').value = sliderValue.toFixed(0);
                document.getElementById('mca-calc-withhold-percent').value = requiredWithholdPercent.toFixed(1);
            }

            // Calculate cap amount based on the withhold percentage
            const capAmount = trueRevenue * (withholdPercent / 100);
            const weeklyPayment = monthlyPayment / 4.33;
            const dailyPayment = monthlyPayment / 21.67;

            // Calculate withhold utilization (totalMonthlyPayment already calculated above)
            const withholdUtilization = capAmount > 0 ? (totalMonthlyPayment / capAmount) * 100 : 0;

            // Calculate new payment available (reverse calculation)
            const newPaymentAvailable = Math.max(0, capAmount - existingPayment);

            // Update UI - keep calculated funded amount crossed out, show manual in results
            document.getElementById('mca-calc-cap-amount').textContent = formatCurrency(capAmount);
            document.getElementById('mca-calc-new-payment').textContent = formatCurrency(newPaymentAvailable);
            // Don't update mca-calc-funded-amount as it should show the calculated value (crossed out)
            document.getElementById('mca-calc-result-factor').textContent = factorRate.toFixed(2);
            document.getElementById('mca-calc-result-payback').textContent = formatCurrency(totalPayback);
            document.getElementById('mca-calc-result-monthly').textContent = formatCurrency(monthlyPayment);
            document.getElementById('mca-calc-result-weekly').textContent = formatCurrency(weeklyPayment);
            document.getElementById('mca-calc-result-daily').textContent = formatCurrency(dailyPayment);
            document.getElementById('mca-calc-result-term').textContent = getTermDisplayText();

            // Update utilization bar
            const utilizationBar = document.getElementById('mca-calc-utilization-bar');
            const utilizationText = document.getElementById('mca-calc-utilization-text');
            utilizationText.textContent = Math.min(withholdUtilization, 100).toFixed(1) + '%';
            utilizationBar.style.width = Math.min(withholdUtilization, 100) + '%';

            // Update utilization bar color
            if (withholdUtilization <= 50) {
                utilizationBar.className = 'h-full bg-green-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-green-600 dark:text-green-400';
            } else if (withholdUtilization <= 80) {
                utilizationBar.className = 'h-full bg-yellow-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-yellow-600 dark:text-yellow-400';
            } else if (withholdUtilization <= 100) {
                utilizationBar.className = 'h-full bg-orange-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-orange-600 dark:text-orange-400';
            } else {
                utilizationBar.className = 'h-full bg-red-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-red-600 dark:text-red-400';
            }

            // Show/hide warning
            const warningDiv = document.getElementById('mca-calc-warning');
            const warningText = document.getElementById('mca-calc-warning-text');

            if (withholdUtilization > 100) {
                warningDiv.classList.remove('hidden');
                warningText.textContent = `Total withhold utilization (${withholdUtilization.toFixed(1)}%) exceeds 100%. This offer may not be viable.`;
            } else if (fundedAmount <= 0) {
                warningDiv.classList.remove('hidden');
                warningText.textContent = `Please enter a valid funded amount greater than $0.`;
            } else {
                warningDiv.classList.add('hidden');
            }
        }

        // Toggle Withhold Percentage Override
        function toggleWithholdOverride() {
            const toggle = document.getElementById('mca-calc-withhold-override-toggle');
            const input = document.getElementById('mca-calc-withhold-override-input');
            const sliderContainer = document.getElementById('withhold-slider-container');

            if (toggle.checked) {
                input.classList.remove('hidden');
                sliderContainer.classList.add('opacity-50', 'pointer-events-none');
            } else {
                input.classList.add('hidden');
                sliderContainer.classList.remove('opacity-50', 'pointer-events-none');
                document.getElementById('mca-calc-withhold-override-value').value = '';

                // Check if funded amount is overridden
                const fundedOverride = document.getElementById('mca-calc-funded-override-toggle').checked;
                if (fundedOverride) {
                    recalculateWithFundedOverride();
                } else {
                    recalculateOffer();
                }
            }
        }

        // Recalculate with Withhold Percentage Override
        function recalculateWithWithholdOverride() {
            const withholdOverride = document.getElementById('mca-calc-withhold-override-toggle').checked;
            const fundedOverride = document.getElementById('mca-calc-funded-override-toggle').checked;

            if (!withholdOverride) {
                // If withhold override is disabled, use normal calculation
                if (fundedOverride) {
                    recalculateWithFundedOverride();
                } else {
                    recalculateOffer();
                }
                return;
            }

            // Get custom withhold percentage
            const customWithhold = parseFloat(document.getElementById('mca-calc-withhold-override-value').value) || 0;

            if (customWithhold < 0 || customWithhold > 100) {
                showToast('Withhold percentage must be between 0% and 100%', 'error');
                return;
            }

            // Get other values
            const trueRevenue = getTrueRevenue();
            const existingPayment = parseFloat(document.getElementById('mca-calc-existing-payment').value) || 0;
            const factorRate = parseFloat(document.getElementById('mca-calc-factor-rate').value) || 1.30;
            const termMonths = getTermInMonths();

            // Calculate cap amount with custom withhold
            const capAmount = trueRevenue * (customWithhold / 100);
            const newPaymentAvailable = Math.max(0, capAmount - existingPayment);

            let fundedAmount, monthlyPayment, totalPayback, withholdUtilization;

            if (fundedOverride) {
                // If funded amount is also overridden, use manual funded amount
                fundedAmount = parseFloat(document.getElementById('mca-calc-funded-override-value').value) || 0;
                totalPayback = fundedAmount * factorRate;
                monthlyPayment = termMonths > 0 ? totalPayback / termMonths : 0;
            } else {
                // Calculate funded amount based on custom withhold constraint
                fundedAmount = (newPaymentAvailable * termMonths) / factorRate;
                totalPayback = fundedAmount * factorRate;
                monthlyPayment = termMonths > 0 ? totalPayback / termMonths : 0;
            }

            const weeklyPayment = monthlyPayment / 4.33;
            const dailyPayment = monthlyPayment / 21.67;

            // Calculate withhold utilization with custom percentage
            const totalMonthlyPayment = existingPayment + monthlyPayment;
            withholdUtilization = capAmount > 0 ? (totalMonthlyPayment / capAmount) * 100 : 0;

            // Update UI
            document.getElementById('mca-calc-cap-amount').textContent = formatCurrency(capAmount);
            document.getElementById('mca-calc-new-payment').textContent = formatCurrency(newPaymentAvailable);

            if (!fundedOverride) {
                document.getElementById('mca-calc-funded-amount').textContent = formatCurrency(fundedAmount);
            }

            document.getElementById('mca-calc-result-factor').textContent = factorRate.toFixed(2);
            document.getElementById('mca-calc-result-payback').textContent = formatCurrency(totalPayback);
            document.getElementById('mca-calc-result-monthly').textContent = formatCurrency(monthlyPayment);
            document.getElementById('mca-calc-result-weekly').textContent = formatCurrency(weeklyPayment);
            document.getElementById('mca-calc-result-daily').textContent = formatCurrency(dailyPayment);
            document.getElementById('mca-calc-result-term').textContent = getTermDisplayText();

            // Update utilization bar
            const utilizationBar = document.getElementById('mca-calc-utilization-bar');
            const utilizationText = document.getElementById('mca-calc-utilization-text');
            utilizationText.textContent = Math.min(withholdUtilization, 100).toFixed(1) + '%';
            utilizationBar.style.width = Math.min(withholdUtilization, 100) + '%';

            // Update utilization bar color
            if (withholdUtilization <= 50) {
                utilizationBar.className = 'h-full bg-green-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-green-600 dark:text-green-400';
            } else if (withholdUtilization <= 80) {
                utilizationBar.className = 'h-full bg-yellow-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-yellow-600 dark:text-yellow-400';
            } else if (withholdUtilization <= 100) {
                utilizationBar.className = 'h-full bg-orange-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-orange-600 dark:text-orange-400';
            } else {
                utilizationBar.className = 'h-full bg-red-500 rounded-full transition-all duration-300';
                utilizationText.className = 'font-semibold text-red-600 dark:text-red-400';
            }

            // Show/hide warning
            const warningDiv = document.getElementById('mca-calc-warning');
            const warningText = document.getElementById('mca-calc-warning-text');

            if (withholdUtilization > 100) {
                warningDiv.classList.remove('hidden');
                warningText.textContent = `Total withhold utilization (${withholdUtilization.toFixed(1)}%) exceeds 100%. This offer may not be viable.`;
            } else if (fundedAmount <= 0 && trueRevenue > 0) {
                warningDiv.classList.remove('hidden');
                warningText.textContent = `No funding capacity available. Existing MCA payments consume the full withhold capacity.`;
            } else if (customWithhold > 30) {
                warningDiv.classList.remove('hidden');
                warningText.textContent = `Warning: Withhold percentage of ${customWithhold.toFixed(1)}% is unusually high. Standard range is 5-25%.`;
            } else {
                warningDiv.classList.add('hidden');
            }
        }

        async function saveCurrentOffer() {
            const trueRevenue = getTrueRevenue();
            const existingPayment = parseFloat(document.getElementById('mca-calc-existing-payment').value) || 0;
            const factorRate = parseFloat(document.getElementById('mca-calc-factor-rate').value) || 1.30;
            const termMonths = getTermInMonths();

            // Check if withhold percentage is manually overridden
            const withholdOverrideToggle = document.getElementById('mca-calc-withhold-override-toggle');
            let withholdPercent;
            if (withholdOverrideToggle.checked) {
                withholdPercent = parseFloat(document.getElementById('mca-calc-withhold-override-value').value) || 20;
            } else {
                withholdPercent = parseFloat(document.getElementById('mca-calc-withhold-percent').value) || 20;
            }

            // Check if funded amount is manually overridden
            const fundedOverrideToggle = document.getElementById('mca-calc-funded-override-toggle');
            let fundedAmount;

            if (fundedOverrideToggle.checked) {
                // Use manual funded amount
                fundedAmount = parseFloat(document.getElementById('mca-calc-funded-override-value').value) || 0;
            } else {
                // Calculate funded amount based on withhold constraint
                const capAmount = trueRevenue * (withholdPercent / 100);
                const newPaymentAvailable = Math.max(0, capAmount - existingPayment);
                fundedAmount = (newPaymentAvailable * termMonths) / factorRate;
            }

            if (fundedAmount <= 0) {
                showToast('No funding capacity available to save', 'error');
                return;
            }

            const overrideToggle = document.getElementById('mca-calc-override-toggle');
            const termType = document.querySelector('input[name="term-type"]:checked').value;

            // Get the actual term value based on type
            let termValue;
            switch(termType) {
                case 'daily':
                    termValue = parseInt(document.getElementById('mca-calc-term-daily').value) || 180;
                    break;
                case 'weekly':
                    termValue = parseInt(document.getElementById('mca-calc-term-weekly').value) || 36;
                    break;
                case 'biweekly':
                    termValue = parseInt(document.getElementById('mca-calc-term-biweekly').value) || 18;
                    break;
                case 'monthly':
                default:
                    termValue = parseInt(document.getElementById('mca-calc-term-monthly').value) || 9;
                    break;
            }

            const offerData = {
                session_uuid: mcaCalcPrimarySessionUuid,
                true_revenue_monthly: trueRevenue,
                revenue_override: overrideToggle.checked,
                override_revenue: overrideToggle.checked ? trueRevenue : null,
                existing_mca_payment: existingPayment,
                withhold_percent: withholdPercent,
                withhold_override: withholdOverrideToggle.checked,
                factor_rate: factorRate,
                term_months: termMonths,
                term_type: termType,
                term_value: termValue,
                advance_amount: fundedAmount,
                funded_amount_override: fundedOverrideToggle.checked,
                offer_name: null,
                notes: null
            };

            try {
                const response = await fetch('{{ route("bankstatement.save-offer") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(offerData)
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Offer saved successfully', 'success');
                    loadSavedOffers();
                } else {
                    showToast(data.message || 'Failed to save offer', 'error');
                }
            } catch (error) {
                console.error('Save offer error:', error);
                showToast('Error saving offer', 'error');
            }
        }

        async function loadSavedOffers() {
            if (!mcaCalcPrimarySessionUuid) return;

            try {
                const response = await fetch('{{ route("bankstatement.load-offers") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ session_uuid: mcaCalcPrimarySessionUuid })
                });

                const data = await response.json();

                if (data.success) {
                    renderSavedOffers(data.offers);
                }
            } catch (error) {
                console.error('Load offers error:', error);
            }
        }

        function renderSavedOffers(offers) {
            const container = document.getElementById('mca-saved-offers-list');
            const section = document.getElementById('mca-saved-offers-section');
            const countBadge = document.getElementById('saved-offers-count');

            if (offers.length === 0) {
                section.classList.add('hidden');
                return;
            }

            section.classList.remove('hidden');
            countBadge.textContent = offers.length;

            container.innerHTML = offers.map(offer => `
                <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200 dark:border-gray-600 relative">
                    <button onclick="deleteOffer('${offer.offer_id}')" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 transition-colors" title="Delete offer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-500 dark:text-gray-400">${new Date(offer.created_at).toLocaleDateString()}</span>
                            <span class="text-xs px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded">${offer.term_months}mo</span>
                        </div>
                        <div class="text-lg font-bold text-gray-900 dark:text-gray-100">${formatCurrency(parseFloat(offer.advance_amount))}</div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-500">Factor:</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">${parseFloat(offer.factor_rate).toFixed(2)}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Monthly:</span>
                                <span class="font-medium text-blue-600 dark:text-blue-400">${formatCurrency(parseFloat(offer.monthly_payment))}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Payback:</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">${formatCurrency(parseFloat(offer.total_payback))}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Revenue:</span>
                                <span class="font-medium text-green-600 dark:text-green-400">${formatCurrency(parseFloat(offer.true_revenue_monthly))}</span>
                            </div>
                        </div>
                        <button onclick="loadOfferToCalculator(${JSON.stringify(offer).replace(/"/g, '&quot;')})" class="w-full mt-2 px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-medium rounded hover:bg-gray-300 dark:hover:bg-gray-500 transition">
                            Load to Calculator
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function loadOfferToCalculator(offer) {
            // Set override if needed
            const analysisTrueRevenue = parseFloat(document.getElementById('mca-calc-true-revenue-value').value) || 0;
            const offerRevenue = parseFloat(offer.true_revenue_monthly);

            if (offer.revenue_override || Math.abs(analysisTrueRevenue - offerRevenue) > 1) {
                document.getElementById('mca-calc-override-toggle').checked = true;
                document.getElementById('mca-calc-override-value').value = offerRevenue;
                document.getElementById('mca-calc-override-input').classList.remove('hidden');
                document.getElementById('mca-calc-true-revenue').classList.add('text-gray-400', 'line-through');
            } else {
                document.getElementById('mca-calc-override-toggle').checked = false;
                document.getElementById('mca-calc-override-input').classList.add('hidden');
                document.getElementById('mca-calc-true-revenue').classList.remove('text-gray-400', 'line-through');
            }

            document.getElementById('mca-calc-existing-payment').value = parseFloat(offer.existing_mca_payment) || 0;
            document.getElementById('mca-calc-withhold-percent').value = parseFloat(offer.withhold_percent) || 20;
            document.getElementById('mca-calc-withhold-slider').value = parseFloat(offer.withhold_percent) || 20;
            document.getElementById('mca-calc-factor-rate').value = parseFloat(offer.factor_rate) || 1.30;
            document.getElementById('mca-calc-factor-slider').value = parseFloat(offer.factor_rate) || 1.30;
            document.getElementById('mca-calc-term-months').value = parseInt(offer.term_months) || 9;

            recalculateOffer();

            showToast('Offer loaded to calculator', 'success');
        }

        async function deleteOffer(offerId) {
            if (!confirm('Are you sure you want to delete this offer?')) return;

            try {
                const response = await fetch('{{ route("bankstatement.delete-offer") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ offer_id: offerId })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Offer deleted', 'success');
                    loadSavedOffers();
                } else {
                    showToast(data.message || 'Failed to delete offer', 'error');
                }
            } catch (error) {
                console.error('Delete offer error:', error);
                showToast('Error deleting offer', 'error');
            }
        }

        // Toggle Dashboard View between Credit and Debit focus
        function toggleDashboardView(viewMode) {
            const creditBtn = document.getElementById('credit-view-btn');
            const debitBtn = document.getElementById('debit-view-btn');
            const metricCards = document.querySelectorAll('.metric-card');

            // Update button states
            if (viewMode === 'credit') {
                // Credit button active
                creditBtn.className = 'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 bg-white dark:bg-gray-800 text-green-600 dark:text-green-400 shadow-sm';
                debitBtn.className = 'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600';

                // Highlight credit metrics, de-emphasize debit metrics
                metricCards.forEach(card => {
                    const metricType = card.getAttribute('data-metric-type');

                    if (metricType === 'credit') {
                        // Emphasize credit metrics
                        card.classList.remove('opacity-60', 'scale-95');
                        card.classList.add('scale-105', 'shadow-lg', 'ring-2', 'ring-green-500', 'ring-opacity-50');

                        const value = card.querySelector('.metric-value');
                        const subtext = card.querySelector('.metric-subtext');
                        if (value) value.classList.add('text-3xl');
                        if (subtext) subtext.classList.remove('text-xs');
                        if (subtext) subtext.classList.add('text-sm', 'font-medium');
                    } else if (metricType === 'debit') {
                        // De-emphasize debit metrics
                        card.classList.add('opacity-60', 'scale-95');
                        card.classList.remove('scale-105', 'shadow-lg', 'ring-2', 'ring-red-500', 'ring-opacity-50');

                        const value = card.querySelector('.metric-value');
                        const subtext = card.querySelector('.metric-subtext');
                        if (value) value.classList.remove('text-3xl');
                        if (subtext) subtext.classList.remove('text-sm', 'font-medium');
                        if (subtext) subtext.classList.add('text-xs');
                    } else {
                        // Neutral metrics - normal state
                        card.classList.remove('opacity-60', 'scale-95', 'scale-105', 'shadow-lg', 'ring-2', 'ring-green-500', 'ring-red-500', 'ring-opacity-50');
                    }
                });
            } else if (viewMode === 'debit') {
                // Debit button active
                debitBtn.className = 'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 bg-white dark:bg-gray-800 text-red-600 dark:text-red-400 shadow-sm';
                creditBtn.className = 'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600';

                // Highlight debit metrics, de-emphasize credit metrics
                metricCards.forEach(card => {
                    const metricType = card.getAttribute('data-metric-type');

                    if (metricType === 'debit') {
                        // Emphasize debit metrics
                        card.classList.remove('opacity-60', 'scale-95');
                        card.classList.add('scale-105', 'shadow-lg', 'ring-2', 'ring-red-500', 'ring-opacity-50');

                        const value = card.querySelector('.metric-value');
                        const subtext = card.querySelector('.metric-subtext');
                        if (value) value.classList.add('text-3xl');
                        if (subtext) subtext.classList.remove('text-xs');
                        if (subtext) subtext.classList.add('text-sm', 'font-medium');
                    } else if (metricType === 'credit') {
                        // De-emphasize credit metrics
                        card.classList.add('opacity-60', 'scale-95');
                        card.classList.remove('scale-105', 'shadow-lg', 'ring-2', 'ring-green-500', 'ring-opacity-50');

                        const value = card.querySelector('.metric-value');
                        const subtext = card.querySelector('.metric-subtext');
                        if (value) value.classList.remove('text-3xl');
                        if (subtext) subtext.classList.remove('text-sm', 'font-medium');
                        if (subtext) subtext.classList.add('text-xs');
                    } else {
                        // Neutral metrics - normal state
                        card.classList.remove('opacity-60', 'scale-95', 'scale-105', 'shadow-lg', 'ring-2', 'ring-green-500', 'ring-red-500', 'ring-opacity-50');
                    }
                });
            }
        }
    </script>

    <!-- Similar Transactions Modal -->
    <div id="similar-transactions-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeSimilarTransactionsModal()"></div>
        <div class="fixed inset-4 md:inset-10 lg:inset-20 bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="bg-purple-600 px-6 py-4 flex items-center justify-between flex-shrink-0">
                <div>
                    <h3 class="text-lg font-bold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Mark Similar Transactions
                    </h3>
                    <p class="text-purple-200 text-sm" id="similar-modal-subtitle"></p>
                </div>
                <button onclick="closeSimilarTransactionsModal()" class="text-white hover:text-purple-200 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Credits Section -->
                <div id="similar-credits-section" class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-purple-700 dark:text-purple-400 flex items-center">
                            <span class="w-3 h-3 bg-purple-500 rounded-full mr-2"></span>
                            Credits - Mark as MCA Funding
                            <span class="ml-2 text-xs font-normal text-gray-500">(These will be marked as adjustments from this lender)</span>
                        </h4>
                        <label class="flex items-center text-sm cursor-pointer hover:text-purple-600">
                            <input type="checkbox" id="select-all-credits" class="mr-2 rounded border-gray-300 text-purple-600 focus:ring-purple-500" onchange="toggleAllCredits()">
                            Select All Credits
                        </label>
                    </div>
                    <div id="similar-credits-list" class="space-y-2 max-h-60 overflow-y-auto"></div>
                </div>

                <!-- Debits Section -->
                <div id="similar-debits-section">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-red-700 dark:text-red-400 flex items-center">
                            <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                            Debits - Mark as MCA Payments
                            <span class="ml-2 text-xs font-normal text-gray-500">(These will be marked as payments to this lender)</span>
                        </h4>
                        <label class="flex items-center text-sm cursor-pointer hover:text-red-600">
                            <input type="checkbox" id="select-all-debits" class="mr-2 rounded border-gray-300 text-red-600 focus:ring-red-500" onchange="toggleAllDebits()">
                            Select All Debits
                        </label>
                    </div>
                    <div id="similar-debits-list" class="space-y-2 max-h-60 overflow-y-auto"></div>
                </div>

                <!-- No Results Message -->
                <div id="no-similar-found" class="hidden text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-lg">No similar transactions found</p>
                    <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">All matching transactions have already been classified</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900 flex justify-between items-center flex-shrink-0">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <span id="similar-selected-count" class="font-semibold text-purple-600">0</span> transactions selected
                </p>
                <div class="flex gap-3">
                    <button onclick="closeSimilarTransactionsModal()"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors font-medium">
                        Skip
                    </button>
                    <button onclick="batchMarkSimilarTransactions()"
                        id="batch-mark-btn"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                        disabled>
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Mark Selected
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Classification Modal -->
    <!-- Category Selection Modal -->
    <div id="category-modal-results" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50">
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all w-full max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-6 pt-5 pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Classify Transaction</h3>
                            <button onclick="closeCategoryModalResults()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="mb-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Description</p>
                            <p id="modal-description-results" class="text-sm font-medium text-gray-900 dark:text-white"></p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Category</label>
                            <div id="category-grid-results" class="grid grid-cols-2 gap-2 max-h-96 overflow-y-auto">
                                <!-- Categories will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end gap-2">
                        <button onclick="closeCategoryModalResults()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-500">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Update Confirmation Modal -->
    <div id="bulk-update-confirmation-modal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-[60]">
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all w-full max-w-md">
                    <div class="bg-white dark:bg-gray-800 px-6 pt-5 pb-4">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900">
                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Apply to All Similar Transactions?</h3>
                            </div>
                        </div>

                        <div class="mb-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                                Found <span id="bulk-count" class="font-bold text-gray-900 dark:text-white">0</span> transaction(s) with the same description:
                            </p>
                            <p id="bulk-description" class="text-sm font-medium text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-2 rounded border dark:border-gray-600"></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">
                                Would you like to apply the <span id="bulk-category-name" class="font-semibold"></span> category to all of them?
                            </p>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end gap-2">
                        <button onclick="cancelBulkUpdate()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-500">
                            Only This One
                        </button>
                        <button onclick="confirmBulkUpdate()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Apply to All <span id="bulk-count-button"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Similar Transactions Modal for Category -->
    <div id="similar-category-modal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-[70]">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Similar Transactions Found
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Select which transactions to classify as <span id="similar-category-label" class="font-semibold text-blue-600 dark:text-blue-400"></span>
                    </p>
                </div>

                <div class="px-6 py-4 max-h-[50vh] overflow-y-auto">
                    <div class="mb-4 flex items-center gap-2">
                        <input type="checkbox" id="select-all-similar-category" onchange="toggleAllSimilarCategory()" class="w-4 h-4 text-blue-600 rounded" checked>
                        <label for="select-all-similar-category" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Select All (<span id="similar-category-count">0</span> transactions)
                        </label>
                    </div>

                    <div id="similar-category-list" class="space-y-2">
                        <!-- Transactions will be populated here -->
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        <span id="similar-category-selected">0</span> selected
                    </span>
                    <div class="flex gap-2">
                        <button onclick="closeSimilarCategoryModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-500">
                            Cancel
                        </button>
                        <button onclick="confirmSimilarCategoryUpdate()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MCA Scan Confirmation Modal -->
    <div id="mca-scan-confirmation-modal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-[70]">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                Scan for Similar Transactions?
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-3">
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded p-3 mb-3">
                        <p class="text-xs font-medium text-red-800 dark:text-red-300">
                            <span class="font-bold"><span id="mca-scan-lender-name"></span></span>
                        </p>
                        <p class="text-xs text-red-700 dark:text-red-400 mt-1 truncate" title="">
                            <span id="mca-scan-description" class="italic"></span>
                        </p>
                    </div>

                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                        Would you like to find and mark all similar transactions, or just this one?
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-2 flex justify-end gap-2">
                    <button onclick="closeMcaScanConfirmation()" class="px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded hover:bg-gray-100 dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button onclick="skipMcaScan()" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                        Just This One
                    </button>
                    <button onclick="confirmMcaScan()" class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700">
                        Scan for Similar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Similar Transactions Confirmation Modal for MCA -->
    <div id="similar-mca-modal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-[70]">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                                <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                Similar Transactions Found
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Select which transactions to mark as <span id="similar-mca-lender-label" class="font-semibold text-red-600 dark:text-red-400"></span>
                            </p>
                            <p id="similar-mca-subtitle" class="mt-1 text-xs text-gray-400 dark:text-gray-500 italic"></p>
                        </div>
                        <button onclick="closeSimilarMcaModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-4 max-h-[50vh] overflow-y-auto">
                    <div class="mb-4 flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="select-all-similar" onchange="toggleAllSimilar()" class="w-4 h-4 text-red-600 focus:ring-red-500 rounded">
                            <label for="select-all-similar" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Select All (<span id="similar-count-total" class="font-bold text-red-600 dark:text-red-400">0</span> transactions)
                            </label>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Check the transactions you want to mark as MCA
                        </span>
                    </div>

                    <div id="similar-transactions-list" class="space-y-2">
                        <!-- Transactions will be populated here -->
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            <span id="similar-selected-count" class="font-semibold text-red-600 dark:text-red-400">0</span> transaction(s) selected
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Marking as: <span id="similar-mca-action-label" class="font-medium"></span>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="closeSimilarMcaModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-500 transition-colors">
                            Cancel
                        </button>
                        <button onclick="confirmSimilarMcaUpdate()" class="px-5 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Mark Selected as MCA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ============================================================================
        // NOTE: Category classification functions have been MOVED to the first script section
        // to prevent "not defined" errors when onclick handlers execute.
        //
        // The following functions are now defined in the first script section (~line 2670):
        // - window.closeCategoryModalResults
        // - window.clearCategoryResults
        // - window.selectCategoryResults
        // - window.toggleAllSimilarCategory
        // - window.updateSelectedCategoryCount
        // - window.closeSimilarCategoryModal
        // - window.confirmSimilarCategoryUpdate
        // - showSimilarTransactionsForCategory
        // - performCategoryUpdate
        // - updateAllMatchingRows
        // - updateSingleRow
        // - updateCategoryCellHTML
        // - extractAccountNumberResults
        // - getTransferDirectionResults
        // - updateCategoryStatistics
        // - showNotificationResults
        // - showBulkUpdateConfirmation
        // - window.confirmBulkUpdate
        // - window.cancelBulkUpdate
        // ============================================================================

        /* DUPLICATE CODE REMOVED - ALL CATEGORY FUNCTIONS NOW IN FIRST SCRIPT SECTION */
        /*
        window.closeCategoryModalResults = function() {
            document.getElementById('category-modal-results').classList.add('hidden');
            currentTransactionIdResults = null;
            currentTransactionDbIdResults = null;
            currentTransactionTypeResults = null;
        }

        ... (duplicate code removed - see first script section) ...
        */
        /* END OF COMMENTED OUT DUPLICATE CODE */

        // ============================================================================
        // Account filtering functions - dynamically generated per session/month
        // These are kept here because they are dynamically generated based on session data
        // ============================================================================
        @foreach($results as $result)
            @if($result['success'] && isset($result['monthly_data']['months']))
                @foreach($result['monthly_data']['months'] as $month)
                    @php
                        $sessionId = $result['session_id'];
                        $monthKey = $month['month_key'];
                    @endphp
                    window.filterAccount_{{ $sessionId }}_{{ $monthKey }} = function(accountKey) {
                        const sessionId = '{{ $sessionId }}';
                        const monthKey = '{{ $monthKey }}';

                        // Update button states
                        const buttons = document.querySelectorAll('.account-filter-btn-' + sessionId + '-' + monthKey);
                        buttons.forEach(btn => {
                            if (btn.dataset.account === accountKey) {
                                btn.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-blue-300', 'dark:border-blue-700');
                                btn.classList.add('bg-blue-600', 'text-white');
                            } else {
                                btn.classList.remove('bg-blue-600', 'text-white');
                                btn.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-blue-300', 'dark:border-blue-700');
                            }
                        });

                        // Filter transaction rows
                        const rows = document.querySelectorAll('.txn-account-' + sessionId + '-' + monthKey);
                        rows.forEach(row => {
                            if (accountKey === 'all') {
                                row.style.display = '';
                            } else {
                                row.style.display = row.dataset.account === accountKey ? '' : 'none';
                            }
                        });

                        // Show/hide account summaries
                        const summaries = document.querySelectorAll('.account-summary-' + sessionId + '-' + monthKey + '-' + accountKey);
                        document.querySelectorAll('[class*="account-summary-' + sessionId + '-' + monthKey + '-"]').forEach(s => s.classList.add('hidden'));
                        if (accountKey !== 'all' && accountKey !== 'unknown') {
                            summaries.forEach(s => s.classList.remove('hidden'));
                        }
                    };
                @endforeach
            @endif
        @endforeach

        /* ============================================================================
         * DUPLICATE CATEGORY FUNCTIONS COMMENTED OUT BELOW
         * All these functions have been moved to the first script section
         * ============================================================================
        // Store pending category update data
        let pendingCategoryUpdate = null;

        function showBulkUpdateConfirmation(count, description, categoryKey) {
            const categoryInfo = categoriesDataResults[categoryKey];
            document.getElementById('bulk-count').textContent = count;
            document.getElementById('bulk-count-button').textContent = `(${count})`;
            document.getElementById('bulk-description').textContent = description;
            document.getElementById('bulk-category-name').textContent = categoryInfo.label;
            document.getElementById('bulk-category-name').className = `font-semibold text-${categoryInfo.color}-600 dark:text-${categoryInfo.color}-400`;

            // Hide category modal and show confirmation modal
            document.getElementById('category-modal-results').classList.add('hidden');
            document.getElementById('bulk-update-confirmation-modal').classList.remove('hidden');
        }

        window.confirmBulkUpdate = function() {
            console.log('User confirmed bulk update');
            document.getElementById('bulk-update-confirmation-modal').classList.add('hidden');
            if (pendingCategoryUpdate) {
                performCategoryUpdate(pendingCategoryUpdate, true);
                pendingCategoryUpdate = null;
            }
        }

        window.cancelBulkUpdate = function() {
            console.log('User cancelled bulk update - updating only current transaction');
            document.getElementById('bulk-update-confirmation-modal').classList.add('hidden');
            if (pendingCategoryUpdate) {
                // Update only the current transaction by adding a flag
                pendingCategoryUpdate.update_single_only = true;
                performCategoryUpdate(pendingCategoryUpdate, false);
                pendingCategoryUpdate = null;
            }
        }

        function performCategoryUpdate(requestBody, isBulk) {
            console.log('Performing category update:', requestBody, 'isBulk:', isBulk);

            fetch('{{ route("bankstatement.toggle-category") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    const description = requestBody.description;
                    const categoryKey = requestBody.category;

                    // If multiple transactions were updated (and user confirmed bulk), update all visible rows
                    if (data.updated_count > 1 && isBulk) {
                        console.log(`Updating ${data.updated_count} transactions with matching description`);
                        updateAllMatchingRows(description, categoryKey, requestBody.amount, requestBody.type);
                    } else {
                        // Update only the current transaction
                        updateSingleRow(currentTransactionIdResults, categoryKey, description, requestBody.amount, requestBody.type);
                    }

                    closeCategoryModalResults();
                    const msg = isBulk && data.updated_count > 1
                        ? data.message
                        : `Transaction classified as "${categoriesDataResults[categoryKey].label}"`;
                    showNotificationResults(msg, 'success');

                    // Update category statistics
                    updateCategoryStatistics();
                } else {
                    showNotificationResults(data.message || 'Error updating category', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotificationResults('Network error. Please try again.', 'error');
            });
        }

        function updateAllMatchingRows(description, categoryKey, amount, type) {
            const normalizedDescription = description.trim().toLowerCase();
            let updatedRowsCount = 0;

            document.querySelectorAll('tr[class*="txn-row-"]').forEach(txnRow => {
                const descCell = txnRow.querySelector('td:nth-child(2)');
                if (descCell && descCell.textContent.trim().toLowerCase() === normalizedDescription) {
                    const rowClasses = txnRow.className.split(' ');
                    const txnRowClass = rowClasses.find(c => c.startsWith('txn-row-'));
                    if (txnRowClass) {
                        const txnId = txnRowClass.replace('txn-row-', '');
                        const categoryCell = document.getElementById('category-cell-' + txnId);
                        if (categoryCell) {
                            const amountCell = txnRow.querySelector('td:nth-child(4)');
                            const rowAmount = amountCell ? parseFloat(amountCell.textContent.trim().replace(/[$,]/g, '')) : amount;
                            const txnDbId = txnRow.getAttribute('data-transaction-id');
                            const rowType = amountCell && (amountCell.classList.contains('text-green-600') || amountCell.classList.contains('text-green-400')) ? 'credit' : 'debit';

                            updateCategoryCellHTML(categoryCell, txnId, categoryKey, description, rowAmount, rowType, txnDbId);
                            updatedRowsCount++;

                            // Add highlight animation
                            txnRow.classList.add('bg-blue-50', 'dark:bg-blue-900/20');
                            setTimeout(() => txnRow.classList.remove('bg-blue-50', 'dark:bg-blue-900/20'), 2000);
                        }
                    }
                }
            });
            console.log(`Successfully updated ${updatedRowsCount} visible transaction rows`);
        }

        function updateSingleRow(txnId, categoryKey, description, amount, type) {
            const categoryCell = document.getElementById('category-cell-' + txnId);
            if (categoryCell) {
                updateCategoryCellHTML(categoryCell, txnId, categoryKey, description, amount, type, currentTransactionDbIdResults);
            }
        }

        window.selectCategoryResults = function(categoryKey) {
            console.log('selectCategoryResults called:', categoryKey);
            console.log('currentTransactionIdResults:', currentTransactionIdResults);
            console.log('currentTransactionDbIdResults:', currentTransactionDbIdResults);

            if (!currentTransactionIdResults) return;

            const description = document.getElementById('modal-description-results').textContent;

            // Find row by class name (txn-row-{uniqueId})
            const row = document.querySelector(`.txn-row-${currentTransactionIdResults}`);

            if (!row) {
                console.error('Could not find transaction row with class:', `txn-row-${currentTransactionIdResults}`);
                showNotificationResults('Error: Could not find transaction row', 'error');
                return;
            }

            const amountText = row.querySelector('td:nth-child(4)').textContent.trim();
            const amount = parseFloat(amountText.replace(/[$,]/g, ''));

            // Count matching transactions across all visible rows
            const normalizedDescription = description.trim().toLowerCase();
            let matchCount = 0;
            document.querySelectorAll('tr[class*="txn-row-"]').forEach(txnRow => {
                const descCell = txnRow.querySelector('td:nth-child(2)');
                if (descCell && descCell.textContent.trim().toLowerCase() === normalizedDescription) {
                    matchCount++;
                }
            });

            console.log(`Found ${matchCount} matching transactions`);

            const requestBody = {
                description: description,
                amount: amount,
                type: currentTransactionTypeResults,
                category: categoryKey,
                subcategory: null
            };

            // Include transaction_id if we have a valid database ID
            if (currentTransactionDbIdResults) {
                requestBody.transaction_id = currentTransactionDbIdResults;
            }

            // If multiple matches, show detailed similar transactions modal
            if (matchCount > 1) {
                closeCategoryModalResults();
                showSimilarTransactionsForCategory(description, currentTransactionTypeResults, categoryKey, requestBody);
                return;
            }

            // Single transaction - update directly
            console.log('Single transaction update:', requestBody);
            performCategoryUpdate(requestBody, false);
        }

        // Show similar transactions modal for category classification
        async function showSimilarTransactionsForCategory(description, type, categoryKey, requestBody) {
            // Get current session IDs from URL
            const urlParams = new URLSearchParams(window.location.search);
            const sessionIds = urlParams.getAll('sessions[]');

            // Find similar transactions
            const response = await fetch('{{ route("bankstatement.find-similar-transactions") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    description: description,
                    type: type,
                    session_ids: sessionIds
                })
            });

            const data = await response.json();

            if (data.success && data.count > 0) {
                // Store pending update
                window.pendingCategoryUpdateData = {
                    categoryKey: categoryKey,
                    requestBody: requestBody,
                    transactions: data.matching_transactions
                };

                // Get category info
                const categoryInfo = categoriesDataResults[categoryKey];
                const categoryLabel = categoryInfo ? categoryInfo.label : categoryKey;

                // Update modal
                document.getElementById('similar-category-label').textContent = categoryLabel;
                document.getElementById('similar-category-count').textContent = data.count;

                // Populate transaction list
                const listContainer = document.getElementById('similar-category-list');
                listContainer.innerHTML = data.matching_transactions.map((txn) => `
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <input type="checkbox"
                               id="similar-cat-txn-${txn.id}"
                               value="${txn.id}"
                               onchange="updateSelectedCategoryCount()"
                               class="similar-cat-checkbox w-4 h-4 text-blue-600 rounded"
                               checked>
                        <div class="flex-1 text-sm">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                ${txn.date} - $${txn.amount.toFixed(2)}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400 truncate">
                                ${txn.description}
                            </div>
                        </div>
                    </div>
                `).join('');

                // Show modal
                document.getElementById('similar-category-modal').classList.remove('hidden');
                document.getElementById('select-all-similar-category').checked = true;
                updateSelectedCategoryCount();
            }
        }

        function toggleAllSimilarCategory() {
            const selectAll = document.getElementById('select-all-similar-category');
            document.querySelectorAll('.similar-cat-checkbox').forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateSelectedCategoryCount();
        }

        function updateSelectedCategoryCount() {
            const selected = document.querySelectorAll('.similar-cat-checkbox:checked').length;
            document.getElementById('similar-category-selected').textContent = selected;
        }

        function closeSimilarCategoryModal() {
            document.getElementById('similar-category-modal').classList.add('hidden');
            window.pendingCategoryUpdateData = null;
        }

        async function confirmSimilarCategoryUpdate() {
            if (!window.pendingCategoryUpdateData) return;

            // Get selected transaction IDs
            const selectedIds = Array.from(document.querySelectorAll('.similar-cat-checkbox:checked'))
                .map(cb => parseInt(cb.value));

            if (selectedIds.length === 0) {
                alert('Please select at least one transaction');
                return;
            }

            const { categoryKey, requestBody } = window.pendingCategoryUpdateData;

            // Close modal
            closeSimilarCategoryModal();

            // Update requestBody with selected transaction IDs
            requestBody.transaction_ids = selectedIds;
            requestBody.update_single_only = false;

            // Perform update
            try {
                const response = await fetch('{{ route("bankstatement.toggle-category") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                const data = await response.json();

                if (data.success) {
                    showNotificationResults(`✓ Updated ${selectedIds.length} transaction(s)`, 'success');

                    // Reload page to reflect changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotificationResults(data.message || 'Failed to update categories', 'error');
                }
            } catch (error) {
                console.error('Error updating categories:', error);
                showNotificationResults('An error occurred while updating categories', 'error');
            }
        }

        // Removed duplicate dead code - now using performCategoryUpdate() function above

        // Keeping window.clearCategoryResults defined below
        window._oldCodeRemoved = function() { if (false) {
            fetch('{{ route("bankstatement.toggle-category") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    console.log('Category saved successfully');

                    // If multiple transactions were updated, find and update all matching rows across ALL sessions
                    if (data.updated_count > 1) {
                        console.log(`Updating ${data.updated_count} transactions with matching description across all visible sessions`);

                        // Find all transaction rows with matching description (case-insensitive)
                        const normalizedDescription = description.trim().toLowerCase();
                        let updatedRowsCount = 0;

                        document.querySelectorAll('tr[class*="txn-row-"]').forEach(txnRow => {
                            // Description is in the 2nd column (td:nth-child(2))
                            const descCell = txnRow.querySelector('td:nth-child(2)');
                            if (descCell && descCell.textContent.trim().toLowerCase() === normalizedDescription) {
                                console.log('Found matching row:', descCell.textContent.trim());

                                // Get the unique ID from the row class
                                const rowClasses = txnRow.className.split(' ');
                                const txnRowClass = rowClasses.find(c => c.startsWith('txn-row-'));
                                if (txnRowClass) {
                                    const txnId = txnRowClass.replace('txn-row-', '');
                                    console.log('Transaction ID:', txnId);

                                    const categoryCell = document.getElementById('category-cell-' + txnId);
                                    console.log('Category cell found:', categoryCell ? 'yes' : 'no');

                                    if (categoryCell) {
                                        // Get amount from the row for this specific transaction (4th column)
                                        const amountCell = txnRow.querySelector('td:nth-child(4)');
                                        const rowAmount = amountCell ? parseFloat(amountCell.textContent.trim().replace(/[$,]/g, '')) : amount;

                                        // Get transaction database ID from data attribute
                                        const txnDbId = txnRow.getAttribute('data-transaction-id');

                                        // Determine transaction type from amount cell color (green=credit, red=debit)
                                        const rowType = amountCell && (amountCell.classList.contains('text-green-600') || amountCell.classList.contains('text-green-400')) ? 'credit' : 'debit';

                                        updateCategoryCellHTML(categoryCell, txnId, categoryKey, description, rowAmount, rowType, txnDbId);
                                        updatedRowsCount++;

                                        // Add a subtle highlight animation to show the update
                                        txnRow.classList.add('bg-blue-50', 'dark:bg-blue-900/20');
                                        setTimeout(() => {
                                            txnRow.classList.remove('bg-blue-50', 'dark:bg-blue-900/20');
                                        }, 2000);
                                    }
                                }
                            }
                        });

                        console.log(`Successfully updated ${updatedRowsCount} visible transaction rows in the UI`);
                        console.log(`Database reported ${data.updated_count} total updates`);

                        if (updatedRowsCount !== data.updated_count) {
                            console.warn(`Mismatch: Updated ${updatedRowsCount} visible rows but database updated ${data.updated_count} rows. Some transactions may be in collapsed sections.`);
                        }

                        // Update category statistics if they exist
                        updateCategoryStatistics();
                    } else {
                        // Update only the current transaction
                        const categoryCell = document.getElementById('category-cell-' + currentTransactionIdResults);
                        if (categoryCell) {
                            updateCategoryCellHTML(categoryCell, currentTransactionIdResults, categoryKey, description, amount, currentTransactionTypeResults, currentTransactionDbIdResults);
                        }

                        // Update category statistics
                        updateCategoryStatistics();
                    }

                    closeCategoryModalResults();

                    // Show enhanced success message
                    const categoryInfo = categoriesDataResults[categoryKey];
                    const categoryLabel = categoryInfo ? categoryInfo.label : categoryKey;
                    const updateMessage = data.updated_count > 1
                        ? `✓ Updated ${data.updated_count} transaction${data.updated_count !== 1 ? 's' : ''} to "${categoryLabel}"`
                        : data.message;
                    showNotificationResults(updateMessage, 'success');
                } else {
                    showNotificationResults(data.message || 'Failed to classify transaction', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotificationResults('An error occurred while classifying the transaction', 'error');
            });
        } }; // End of if (false) and _oldCodeRemoved function

        function applyCategoryColorsResults() {
            const colorClasses = {
                'gray': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                'red': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'orange': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                'amber': 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                'yellow': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                'lime': 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
                'green': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'emerald': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                'teal': 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
                'cyan': 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
                'blue': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                'indigo': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                'purple': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                'pink': 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200'
            };

            document.querySelectorAll('.category-badge').forEach(badge => {
                const category = badge.dataset.category;
                if (categoriesDataResults && categoriesDataResults[category]) {
                    const color = categoriesDataResults[category].color;
                    const classes = colorClasses[color] || colorClasses['gray'];
                    // Add all classes from the string
                    badge.className = badge.className.replace(/bg-\w+-\d+|text-\w+-\d+|dark:bg-\w+-\d+|dark:text-\w+-\d+/g, '');
                    badge.className += ' ' + classes;
                }
            });
        }

        // Helper function to update category statistics across the dashboard
        function updateCategoryStatistics() {
            // Count transactions by category for each visible session
            const categoryStats = {};

            document.querySelectorAll('.category-badge').forEach(badge => {
                const category = badge.dataset.category;
                if (category) {
                    if (!categoryStats[category]) {
                        categoryStats[category] = 0;
                    }
                    categoryStats[category]++;
                }
            });

            console.log('Updated category statistics:', categoryStats);

            // You can add code here to display these statistics in a summary panel
            // For now, this provides the data structure for future enhancements
        }

        // Helper function to update category cell HTML
        function updateCategoryCellHTML(categoryCell, txnId, categoryKey, description, amount, txnType, txnDbId) {
            const categoryInfo = categoriesDataResults[categoryKey];

            // Check if this is a transfer category
            const isTransfer = ['internal_transfer', 'wire_transfer', 'ach_transfer'].includes(categoryKey);
            const accountNumber = extractAccountNumberResults(description);
            const transferDirection = isTransfer ? getTransferDirectionResults(description) : null;

            // Escape description for onclick handler
            const escapedDescription = description.replace(/'/g, "\\'");

            // Build the category display with clickable button
            let categoryHTML = '<div class="flex flex-col items-center gap-1">';

            // Get color classes based on category color
            const colorClasses = {
                'gray': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                'red': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'orange': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                'amber': 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                'yellow': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                'lime': 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
                'green': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'emerald': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                'teal': 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
                'cyan': 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
                'blue': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                'indigo': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                'purple': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                'pink': 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200'
            };
            const categoryColorClass = colorClasses[categoryInfo.color] || colorClasses['gray'];

            // Clickable category badge with optional transfer ring
            categoryHTML += `<button onclick="openCategoryModalResults('${txnId}', '${escapedDescription}', ${amount}, '${txnType}', ${txnDbId})" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium category-badge ${isTransfer ? 'ring-2 ring-offset-1 ring-blue-400 dark:ring-blue-500' : ''} hover:opacity-80 transition cursor-pointer ${categoryColorClass}" data-category="${categoryKey}" title="Click to change category">`;

            // Transfer direction icon
            if (isTransfer && transferDirection) {
                if (transferDirection === 'in') {
                    categoryHTML += `<svg class="w-3 h-3 mr-1 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                    </svg>`;
                } else {
                    categoryHTML += `<svg class="w-3 h-3 mr-1 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                    </svg>`;
                }
            }

            categoryHTML += `${categoryInfo.label}`;

            // Add edit icon
            categoryHTML += `<svg class="w-3 h-3 ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
            </svg>`;

            categoryHTML += `</button>`;

            // Account number badge if present
            if (accountNumber) {
                categoryHTML += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    ****${accountNumber}
                </span>`;
            }

            categoryHTML += '</div>';
            categoryCell.innerHTML = categoryHTML;
        }

        function showNotificationResults(message, type) {
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        */ // END OF COMMENTED OUT DUPLICATE CATEGORY FUNCTIONS

        // ============================================================================
        // Similar MCA Transactions Modal Functions (NOT category-related)
        let pendingMcaUpdate = null;
        let similarTransactionsData = [];

        window.showSimilarMcaModal = async function(description, type, lenderId, lenderName, isMca, sourceContext = null) {
            console.log('showSimilarMcaModal called with:', { description, type, lenderId, lenderName, isMca });

            // Get current session IDs from URL
            const urlParams = new URLSearchParams(window.location.search);
            const sessionIds = urlParams.getAll('sessions[]');

            console.log('Session IDs from URL:', sessionIds);

            // Show loading state
            showToast('Scanning for similar transactions...', 'info');

            try {
                // Find similar transactions
                const response = await fetch('{{ route("bankstatement.find-similar-transactions") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        description: description,
                        type: type,
                        session_ids: sessionIds
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('API response:', data);

            if (data.success && data.count > 0) {
                similarTransactionsData = data.matching_transactions;
                pendingMcaUpdate = { description, type, lenderId, lenderName, isMca, sourceContext };

                // Update modal title and subtitle
                document.getElementById('similar-mca-lender-label').textContent =
                    isMca ? lenderName : 'Not MCA';
                document.getElementById('similar-count-total').textContent = data.count;

                // Update action label in footer
                const actionLabel = document.getElementById('similar-mca-action-label');
                if (actionLabel) {
                    actionLabel.textContent = isMca ? lenderName : 'Not MCA';
                }

                // Update subtitle with description
                const subtitle = document.getElementById('similar-mca-subtitle');
                if (subtitle) {
                    subtitle.textContent = `Found ${data.count} transaction(s) matching: "${description.substring(0, 60)}${description.length > 60 ? '...' : ''}"`;
                }

                // Populate transaction list
                const listContainer = document.getElementById('similar-transactions-list');
                listContainer.innerHTML = data.matching_transactions.map((txn, index) => `
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors border border-transparent hover:border-red-200 dark:hover:border-red-800">
                        <input type="checkbox"
                               id="similar-txn-${txn.id}"
                               value="${txn.id}"
                               onchange="updateSelectedCount()"
                               class="similar-txn-checkbox w-4 h-4 text-red-600 focus:ring-red-500 rounded">
                        <div class="flex-1 text-sm">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                <span class="text-red-600 dark:text-red-400 font-mono">${txn.date}</span>
                                <span class="mx-2 text-gray-400">|</span>
                                <span class="font-semibold">$${parseFloat(txn.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            </div>
                            <div class="text-gray-600 dark:text-gray-400 truncate mt-1" title="${txn.description}">
                                ${txn.description}
                            </div>
                            ${txn.is_mca_payment ? `
                                <div class="mt-1">
                                    <span class="text-xs px-2 py-0.5 bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 rounded">
                                        Currently: ${txn.mca_lender_name || 'MCA'}
                                    </span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('');

                // Show modal
                document.getElementById('similar-mca-modal').classList.remove('hidden');
                document.getElementById('select-all-similar').checked = true;
                document.querySelectorAll('.similar-txn-checkbox').forEach(cb => cb.checked = true);
                updateSelectedCount();

                showToast(`Found ${data.count} similar transaction(s)`, 'success');
                return { count: data.count };
            } else {
                // No similar transactions found
                console.log('No similar transactions found');
                showToast('No similar transactions found', 'info');
                return { count: 0 };
            }
            } catch (error) {
                console.error('Error in showSimilarMcaModal:', error);
                showToast('Error searching for similar transactions: ' + error.message, 'error');
                return { count: 0, error: error.message };
            }
        }

        window.toggleAllSimilar = function() {
            const selectAll = document.getElementById('select-all-similar');
            document.querySelectorAll('.similar-txn-checkbox').forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            window.updateSelectedCount();
        }

        window.updateSelectedCount = function() {
            const selected = document.querySelectorAll('.similar-txn-checkbox:checked').length;
            document.getElementById('similar-selected-count').textContent = selected;
        }

        window.closeSimilarMcaModal = function() {
            document.getElementById('similar-mca-modal').classList.add('hidden');
            pendingMcaUpdate = null;
            similarTransactionsData = [];
        }

        window.confirmSimilarMcaUpdate = async function() {
            if (!pendingMcaUpdate) return;

            // Get selected transaction IDs
            const selectedIds = Array.from(document.querySelectorAll('.similar-txn-checkbox:checked'))
                .map(cb => parseInt(cb.value));

            if (selectedIds.length === 0) {
                alert('Please select at least one transaction');
                return;
            }

            const { description, type, lenderId, lenderName, isMca } = pendingMcaUpdate;

            // Close modal
            closeSimilarMcaModal();

            // Update transactions via API
            const endpoint = type === 'debit'
                ? '{{ route("bankstatement.toggle-mca") }}'
                : '{{ route("bankstatement.toggle-revenue") }}';

            const requestBody = type === 'debit' ? {
                description: description,
                amount: 0,
                is_mca: isMca,
                lender_id: lenderId,
                lender_name: lenderName,
                transaction_ids: selectedIds
            } : {
                transaction_id: 0,
                description: description,
                amount: 0,
                current_classification: 'true_revenue',
                is_mca_funding: isMca,
                mca_lender_id: lenderId,
                mca_lender_name: lenderName,
                transaction_ids: selectedIds
            };

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                const data = await response.json();

                if (data.success) {
                    showNotificationResults(`✓ Updated ${selectedIds.length} transaction(s)`, 'success');

                    // Reload page to reflect changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotificationResults(data.message || 'Failed to update transactions', 'error');
                }
            } catch (error) {
                console.error('Error updating transactions:', error);
                showNotificationResults('An error occurred while updating transactions', 'error');
            }
        }
    </script>

    <!-- Chart.js for Category Distribution -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        // Global variables for category chart
        let categoryChartInstance = null;
        let allCategoryStats = {};
        let creditCategoryStats = {};
        let debitCategoryStats = {};
        let currentCategoryView = 'all';

        // Category Distribution Chart
        document.addEventListener('DOMContentLoaded', function() {
            const results = @json($results);

            // Calculate category distribution for all, credit, and debit separately
            allCategoryStats = {};
            creditCategoryStats = {};
            debitCategoryStats = {};
            let totalAmount = 0;
            let totalCount = 0;

            // Process all transactions from all sessions
            results.forEach(result => {
                if (result.success && result.monthly_data && result.monthly_data.months) {
                    result.monthly_data.months.forEach(month => {
                        if (month.transactions && Array.isArray(month.transactions)) {
                            month.transactions.forEach(txn => {
                                const category = txn.category || 'Uncategorized';
                                const amount = Math.abs(parseFloat(txn.amount) || 0);
                                const type = txn.type;

                                // Update all stats
                                if (!allCategoryStats[category]) {
                                    allCategoryStats[category] = { count: 0, amount: 0, label: category };
                                }
                                allCategoryStats[category].count++;
                                allCategoryStats[category].amount += amount;
                                totalCount++;
                                totalAmount += amount;

                                // Update type-specific stats
                                if (type === 'credit') {
                                    if (!creditCategoryStats[category]) {
                                        creditCategoryStats[category] = { count: 0, amount: 0, label: category };
                                    }
                                    creditCategoryStats[category].count++;
                                    creditCategoryStats[category].amount += amount;
                                } else if (type === 'debit') {
                                    if (!debitCategoryStats[category]) {
                                        debitCategoryStats[category] = { count: 0, amount: 0, label: category };
                                    }
                                    debitCategoryStats[category].count++;
                                    debitCategoryStats[category].amount += amount;
                                }
                            });
                        }
                    });
                }
            });

            console.log('All Category Stats:', allCategoryStats);
            console.log('Credit Category Stats:', creditCategoryStats);
            console.log('Debit Category Stats:', debitCategoryStats);
            console.log('Total transactions found:', totalCount);

            // Initialize the chart with 'all' view
            renderCategoryChart('all');

        });

        // Define colors for categories (matching category colors)
        const categoryColors = {
            'loan_payments': 'rgb(147, 51, 234)',
            'operating_expenses': 'rgb(59, 130, 246)',
            'payroll': 'rgb(99, 102, 241)',
            'revenue': 'rgb(34, 197, 94)',
            'transfers': 'rgb(6, 182, 212)',
            'fees': 'rgb(245, 158, 11)',
            'taxes': 'rgb(239, 68, 68)',
            'equipment': 'rgb(236, 72, 153)',
            'marketing': 'rgb(234, 179, 8)',
            'inventory': 'rgb(16, 185, 129)',
            'utilities': 'rgb(249, 115, 22)',
            'rent': 'rgb(20, 184, 166)',
            'Uncategorized': 'rgb(107, 114, 128)'
        };

        // Function to render category chart based on view mode
        function renderCategoryChart(viewMode) {
            currentCategoryView = viewMode;

            // Select the appropriate stats based on view mode
            let categoryStats = {};
            if (viewMode === 'all') {
                categoryStats = allCategoryStats;
            } else if (viewMode === 'credit') {
                categoryStats = creditCategoryStats;
            } else if (viewMode === 'debit') {
                categoryStats = debitCategoryStats;
            }

            const categories = Object.keys(categoryStats);
            const totalCount = categories.reduce((sum, cat) => sum + categoryStats[cat].count, 0);
            const totalAmount = categories.reduce((sum, cat) => sum + categoryStats[cat].amount, 0);

            console.log(`Rendering ${viewMode} view:`, categoryStats);

            // If no categories found, show a message
            if (categories.length === 0) {
                console.warn(`No categorized ${viewMode} transactions found`);
                const ctx = document.getElementById('categoryPieChart');
                if (ctx && ctx.parentElement) {
                    ctx.parentElement.innerHTML = `<div class="text-center text-gray-500 dark:text-gray-400 py-8"><p>No categorized ${viewMode} transactions yet.</p><p class="text-sm mt-2">Start classifying transactions to see the distribution.</p></div>`;
                }

                // Clear the table
                const tableBody = document.getElementById('categoryStatsTable');
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No data available</td></tr>';
                }
                return;
            }

            const counts = categories.map(cat => categoryStats[cat].count);
            const backgroundColors = categories.map(cat =>
                categoryColors[cat] || `hsl(${Math.random() * 360}, 70%, 60%)`
            );

            // Destroy existing chart if it exists
            if (categoryChartInstance) {
                categoryChartInstance.destroy();
            }

            // Create pie chart
            const ctx = document.getElementById('categoryPieChart');
            if (ctx) {
                categoryChartInstance = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: categories.map(cat => {
                            // Convert category key to readable label
                            return cat.split('_').map(word =>
                                word.charAt(0).toUpperCase() + word.slice(1)
                            ).join(' ');
                        }),
                        datasets: [{
                            data: counts,
                            backgroundColor: backgroundColors,
                            borderColor: 'rgba(255, 255, 255, 1)',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    },
                                    color: getComputedStyle(document.documentElement)
                                        .getPropertyValue('color') || '#374151'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const category = categories[context.dataIndex];
                                        const count = categoryStats[category].count;
                                        const amount = categoryStats[category].amount;
                                        const percentage = ((count / totalCount) * 100).toFixed(1);
                                        return [
                                            `Count: ${count} (${percentage}%)`,
                                            `Amount: $${amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`
                                        ];
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Populate stats table
            const tableBody = document.getElementById('categoryStatsTable');
            if (tableBody) {
                // Sort categories by count (descending)
                const sortedCategories = categories.sort((a, b) =>
                    categoryStats[b].count - categoryStats[a].count
                );

                tableBody.innerHTML = sortedCategories.map((cat, index) => {
                    const stats = categoryStats[cat];
                    const percentage = ((stats.count / totalCount) * 100).toFixed(1);
                    const label = cat.split('_').map(word =>
                        word.charAt(0).toUpperCase() + word.slice(1)
                    ).join(' ');
                    const color = backgroundColors[categories.indexOf(cat)];

                    return `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background-color: ${color}"></span>
                                    <span class="text-gray-900 dark:text-gray-100">${label}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">${stats.count}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">$${stats.amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">${percentage}%</td>
                        </tr>
                    `;
                }).join('');

                // Add total row
                tableBody.innerHTML += `
                    <tr class="bg-gray-100 dark:bg-gray-700 font-semibold">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                        <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">${totalCount}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">$${totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">100%</td>
                    </tr>
                `;
            }
        }

        // Toggle function for category view
        function toggleCategoryView(viewMode) {
            // Update button styles
            const allBtn = document.getElementById('category-all-btn');
            const creditBtn = document.getElementById('category-credit-btn');
            const debitBtn = document.getElementById('category-debit-btn');

            // Reset all buttons
            [allBtn, creditBtn, debitBtn].forEach(btn => {
                if (btn) {
                    btn.className = 'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600';
                }
            });

            // Set active button
            const activeBtn = viewMode === 'all' ? allBtn : (viewMode === 'credit' ? creditBtn : debitBtn);
            if (activeBtn) {
                activeBtn.className = 'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 bg-white dark:bg-gray-800 text-purple-600 dark:text-purple-400 shadow-sm';
            }

            // Render chart with new view
            renderCategoryChart(viewMode);
        }
    </script>
</x-app-layout>
