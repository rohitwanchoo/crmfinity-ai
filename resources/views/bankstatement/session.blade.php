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
    </style>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Analysis Details: {{ Str::limit($session->filename, 40) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between">
                <a href="{{ route('bankstatement.history') }}" class="inline-flex items-center text-sm text-green-600 dark:text-green-400 hover:underline">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to History
                </a>
                <div class="flex items-center gap-3">
                    @php
                        $sessionList = isset($relatedSessions) ? explode(',', $relatedSessions) : [$session->session_id];
                    @endphp
                    <a href="{{ route('bankstatement.view-analysis', ['sessions' => $sessionList]) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        View Updated Analysis
                    </a>
                    <a href="{{ route('bankstatement.pdf', $session->session_id) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-red-100 dark:bg-red-900 border border-transparent rounded-md font-semibold text-xs text-red-700 dark:text-red-300 uppercase tracking-widest hover:bg-red-200 dark:hover:bg-red-800 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        View PDF
                    </a>
                    <a href="{{ route('bankstatement.download', $session->session_id) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Download CSV
                    </a>
                </div>
            </div>

            <!-- Analysis Summary Header with Toggle -->
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Analysis Summary</h3>
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

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="neutral">
                    <p class="text-xs text-gray-500 dark:text-gray-400">File</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate" title="{{ $session->filename }}">{{ $session->filename }}</p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="neutral">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Transactions</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 break-words">{{ $session->total_transactions }}</p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="credit">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Credits</p>
                    <p class="metric-value text-sm font-bold text-green-600 dark:text-green-400 break-words" id="total-credits">${{ number_format($session->total_credits, 2) }}</p>
                    <p class="metric-subtext text-xs text-gray-500"><span id="credit-count">{{ $credits->count() }}</span> transactions</p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="debit">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Debits</p>
                    <p class="metric-value text-sm font-bold text-red-600 dark:text-red-400 break-words" id="total-debits">${{ number_format($session->total_debits, 2) }}</p>
                    <p class="metric-subtext text-xs text-gray-500"><span id="debit-count">{{ $debits->count() }}</span> transactions</p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="neutral">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Net Balance</p>
                    <p class="text-sm font-bold break-words {{ $session->net_flow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" id="net-balance">
                        ${{ number_format($session->net_flow, 2) }}
                    </p>
                </div>
                @if($session->average_daily_balance !== null)
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300 border-2 border-blue-200 dark:border-blue-800" data-metric-type="neutral">
                    <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Avg Daily Balance</p>
                    <p class="text-sm font-bold text-blue-600 dark:text-blue-400 break-words">${{ number_format($session->average_daily_balance, 2) }}</p>
                    <p class="text-xs text-gray-500">From statement</p>
                </div>
                @endif
                @if($session->beginning_balance !== null && $session->ending_balance !== null)
                @php
                    $avgLedgerBalance = ($session->beginning_balance + $session->ending_balance) / 2;
                @endphp
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300 border-2 border-indigo-200 dark:border-indigo-800" data-metric-type="neutral">
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Avg Ledger Balance</p>
                    <p class="text-sm font-bold text-indigo-600 dark:text-indigo-400 break-words">${{ number_format($avgLedgerBalance, 2) }}</p>
                    <p class="text-xs text-gray-500">Calculated</p>
                </div>
                @endif
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="neutral">
                    <p class="text-xs text-gray-500 dark:text-gray-400">API Cost</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 break-words">${{ number_format($session->api_cost ?? 0, 4) }}</p>
                    <p class="text-xs text-gray-500">LSC AI</p>
                </div>
            </div>

            <!-- Lender Qualification Section -->
            @if(isset($lenderMatches) && $lenderMatches['counts']['total'] > 0)
            <div class="mb-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-2 border-green-500" id="session-lender-matching">
                <div class="p-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 dark:text-gray-100">Lender Qualification</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Based on this statement's financial profile</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                            {{ $lenderMatches['counts']['qualified'] }} of {{ $lenderMatches['counts']['total'] }} qualify
                        </span>
                    </div>

                    <!-- Criteria chips -->
                    <div class="flex flex-wrap gap-2 mb-3">
                        @php $cu = $lenderMatches['criteria_used']; @endphp
                        <span class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">Avg Monthly Deposits: <strong>${{ number_format($cu['avg_monthly_deposits'], 0) }}</strong></span>
                        <span class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">True Revenue/mo: <strong>${{ number_format($cu['avg_monthly_true_revenue'], 0) }}</strong></span>
                        <span class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">Neg Days/mo: <strong>{{ round($cu['avg_negative_days'], 1) }}</strong></span>
                        <span class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">NSF/mo: <strong>{{ round($cu['avg_nsf_per_month'], 1) }}</strong></span>
                        <span class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">MCA Positions: <strong>{{ $cu['active_mca_positions'] }}</strong></span>
                        @if($cu['avg_daily_balance'] !== null)
                        <span class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">Avg Daily Bal: <strong>${{ number_format($cu['avg_daily_balance'], 0) }}</strong></span>
                        @endif
                    </div>

                    <!-- Toggle -->
                    <div class="flex gap-2 mb-3">
                        <button onclick="lmToggleView('session', 'qualified')" id="session-lm-btn-qualified"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium bg-green-600 text-white transition-colors">
                            Qualified ({{ $lenderMatches['counts']['qualified'] }})
                        </button>
                        <button onclick="lmToggleView('session', 'all')" id="session-lm-btn-all"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 transition-colors">
                            All Lenders ({{ $lenderMatches['counts']['total'] }})
                        </button>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Lender</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Product</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Funding Speed</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Factor Rate</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Max Amount</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Max Pos.</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase" id="session-lm-status-col">Status</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="session-lm-tbody">
                                @foreach($lenderMatches['all'] as $match)
                                <tr class="slm-row hover:bg-gray-50 dark:hover:bg-gray-700 {{ $match['qualified'] ? 'lm-qualified bg-green-50/30 dark:bg-green-900/10' : 'lm-disqualified' }}"
                                    data-qualified="{{ $match['qualified'] ? '1' : '0' }}">
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $match['lender']->lender_name }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $match['lender']->product_type ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $match['lender']->funding_speed ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $match['lender']->factor_rate ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">
                                        {{ $match['lender']->max_loan_amount ? '$'.number_format($match['lender']->max_loan_amount, 0) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $match['lender']->max_positions ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center slm-status-cell">
                                        @if($match['qualified'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300">✓ Qualifies</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300">✗ Does Not Qualify</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button onclick="slmToggleCriteria(this)" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Details ▾</button>
                                    </td>
                                </tr>
                                <!-- Criteria detail row -->
                                <tr class="slm-criteria-row hidden slm-row {{ $match['qualified'] ? 'lm-qualified' : 'lm-disqualified' }}"
                                    data-qualified="{{ $match['qualified'] ? '1' : '0' }}">
                                    <td colspan="8" class="px-5 py-2 bg-gray-50 dark:bg-gray-700/50">
                                        @if(empty($match['criteria']))
                                            <p class="text-xs text-gray-500">No specific criteria defined for this lender.</p>
                                        @else
                                        <ul class="flex flex-wrap gap-x-6 gap-y-1">
                                            @foreach($match['criteria'] as $check)
                                            <li class="flex items-center gap-1 text-xs {{ $check['skipped'] ? 'text-gray-400 dark:text-gray-500' : ($check['passed'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400') }}">
                                                <span>{{ $check['skipped'] ? '—' : ($check['passed'] ? '✓' : '✗') }}</span>
                                                <span class="font-medium">{{ $check['name'] }}:</span>
                                                <span>{{ $check['actual'] }}</span>
                                                <span class="text-gray-400">(req: {{ $check['required'] }})</span>
                                            </li>
                                            @endforeach
                                        </ul>
                                        @if(!empty($match['fail_reasons']))
                                        <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                            @foreach($match['fail_reasons'] as $reason)
                                            <li class="text-xs text-red-600 dark:text-red-400">• {{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                        @endif
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
            function lmToggleView(prefix, view) {
                var tbody = document.getElementById(prefix + '-lm-tbody');
                if (!tbody) return;
                var rows = tbody.querySelectorAll('.slm-row, .lm-row');
                var statusCol = document.getElementById(prefix + '-lm-status-col');
                var statusCells = tbody.querySelectorAll('.slm-status-cell, .lm-status-cell');
                var btnQ = document.getElementById(prefix + '-lm-btn-qualified');
                var btnA = document.getElementById(prefix + '-lm-btn-all');
                var activeClass = 'px-3 py-1.5 rounded-lg text-xs font-medium bg-green-600 text-white transition-colors';
                var inactiveClass = 'px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 transition-colors';
                if (!btnQ) {
                    activeClass = activeClass.replace('py-1.5', 'py-2').replace('text-xs', 'text-sm');
                    inactiveClass = inactiveClass.replace('py-1.5', 'py-2').replace('text-xs', 'text-sm');
                }
                if (view === 'qualified') {
                    rows.forEach(function(r) { r.classList.toggle('hidden', r.dataset.qualified !== '1'); });
                    if (statusCol) statusCol.classList.add('hidden');
                    statusCells.forEach(function(c) { c.classList.add('hidden'); });
                    if (btnQ) btnQ.className = activeClass;
                    if (btnA) btnA.className = inactiveClass;
                } else {
                    rows.forEach(function(r) { r.classList.remove('hidden'); });
                    tbody.querySelectorAll('.slm-criteria-row, .lm-criteria-row').forEach(function(r) {
                        if (!r.classList.contains('lm-open')) r.classList.add('hidden');
                    });
                    if (statusCol) statusCol.classList.remove('hidden');
                    statusCells.forEach(function(c) { c.classList.remove('hidden'); });
                    if (btnA) btnA.className = activeClass;
                    if (btnQ) btnQ.className = inactiveClass;
                }
            }
            function slmToggleCriteria(btn) {
                var criteriaRow = btn.closest('tr').nextElementSibling;
                if (!criteriaRow) return;
                var isOpen = !criteriaRow.classList.contains('hidden');
                criteriaRow.classList.toggle('hidden', isOpen);
                criteriaRow.classList.toggle('lm-open', !isOpen);
                btn.textContent = isOpen ? 'Details ▾' : 'Details ▴';
            }
            document.addEventListener('DOMContentLoaded', function() {
                lmToggleView('session', 'qualified');
            });
            </script>
            @endif

            <!-- Info Banner -->
            <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Click the <strong>Toggle</strong> button to change a transaction from Credit to Debit or vice versa. Click on any <strong>category badge</strong> to reclassify a transaction. Your corrections will train the AI for future analyses.
                    </p>
                </div>
            </div>

            @php
                // Group transactions by account number
                $accountGroups = ['unknown' => []];
                $accountSummaries = ['unknown' => ['credits' => 0, 'debits' => 0, 'credit_count' => 0, 'debit_count' => 0]];

                foreach($transactions as $txn) {
                    $description = $txn->description;
                    $acctNum = null;

                    // Extract account number from description
                    if (preg_match('/(?:ACCT|ACCOUNT|A\/C|CHK|CHECKING|SAV|SAVINGS)[\s#:]*([X*\.]+)?(\d{4,})/i', $description, $m)) {
                        $acctNum = $m[2];
                    } elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $description, $m)) {
                        $acctNum = $m[1];
                    } elseif (preg_match('/([X*\.]{3,})(\d{4,})/', $description, $m)) {
                        $acctNum = $m[2];
                    }

                    $key = $acctNum ?? 'unknown';

                    if (!isset($accountGroups[$key])) {
                        $accountGroups[$key] = [];
                        $accountSummaries[$key] = ['credits' => 0, 'debits' => 0, 'credit_count' => 0, 'debit_count' => 0];
                    }

                    $accountGroups[$key][] = $txn;

                    // Calculate summaries
                    $amount = $txn->amount;
                    if ($txn->type === 'credit') {
                        $accountSummaries[$key]['credits'] += $amount;
                        $accountSummaries[$key]['credit_count']++;
                    } else {
                        $accountSummaries[$key]['debits'] += $amount;
                        $accountSummaries[$key]['debit_count']++;
                    }
                }

                $hasMultipleAccounts = count($accountGroups) > 1;
            @endphp

            <!-- Transactions Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Account Summaries (if multiple accounts detected) -->
                    @if($hasMultipleAccounts)
                    @php
                        // Count only accounts with transactions
                        $accountsWithTransactions = array_filter($accountGroups, function($txns) {
                            return count($txns) > 0;
                        });
                    @endphp
                    <div class="mb-6 border border-blue-300 dark:border-blue-700 rounded-lg overflow-hidden">
                        <div class="bg-blue-600 px-4 py-3">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-white mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                <h3 class="font-semibold text-white">Multiple Accounts Detected ({{ count($accountsWithTransactions) }})</h3>
                            </div>
                        </div>

                        <!-- Account Summary Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Transactions</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Credits</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Count</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Debits</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Count</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Net Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @php
                                        // Sort accounts: numbered accounts first, then 'unknown' last
                                        $sortedAccounts = array_keys($accountGroups);
                                        usort($sortedAccounts, function($a, $b) {
                                            if ($a === 'unknown') return 1;
                                            if ($b === 'unknown') return -1;
                                            return strcmp($a, $b);
                                        });
                                    @endphp
                                    @foreach($sortedAccounts as $acct)
                                    @php
                                        $summary = $accountSummaries[$acct];
                                    @endphp
                                    @if(count($accountGroups[$acct]) > 0)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($acct === 'unknown')
                                                <span class="text-sm text-gray-500 dark:text-gray-400">Other Transactions</span>
                                            @else
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                    </svg>
                                                    <span class="text-sm font-mono font-bold text-blue-900 dark:text-blue-100">****{{ $acct }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200">
                                                {{ count($accountGroups[$acct]) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-green-600 dark:text-green-400">
                                            ${{ number_format($summary['credits'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                {{ $summary['credit_count'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-red-600 dark:text-red-400">
                                            ${{ number_format($summary['debits'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                {{ $summary['debit_count'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold {{ ($summary['credits'] - $summary['debits']) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            ${{ number_format($summary['credits'] - $summary['debits'], 2) }}
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    <!-- Transfer Account Summary -->
                    @php
                        // Filter transfer transactions and group by account number
                        $transferTransactions = $transactions->filter(function($txn) {
                            $category = $txn->category ?? null;
                            return in_array($category, ['internal_transfer', 'wire_transfer', 'ach_transfer']);
                        });

                        // Group transfers by account number
                        $transferSummary = [];
                        foreach ($transferTransactions as $txn) {
                            // Extract account number from description
                            $accountNumber = null;
                            $description = $txn->description;
                            // Match patterns like: ...7072, ****1234, XXXX1234, Chk ...7072
                            if (preg_match('/(?:ACCT|ACCOUNT|A\/C|CHK|CHECKING|SAV|SAVINGS)[\s#:]*([X*\.]+)?(\d{4,})/i', $description, $matches)) {
                                $accountNumber = $matches[2];
                            } elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $description, $matches)) {
                                $accountNumber = $matches[1];
                            } elseif (preg_match('/([X*\.]{3,})(\d{4,})/', $description, $matches)) {
                                $accountNumber = $matches[2];
                            }

                            $acctKey = $accountNumber ?? 'Unknown';

                            if (!isset($transferSummary[$acctKey])) {
                                $transferSummary[$acctKey] = [
                                    'account_number' => $accountNumber,
                                    'total_credits' => 0,
                                    'total_debits' => 0,
                                    'credit_count' => 0,
                                    'debit_count' => 0,
                                ];
                            }

                            if ($txn->type === 'credit') {
                                $transferSummary[$acctKey]['total_credits'] += $txn->amount;
                                $transferSummary[$acctKey]['credit_count']++;
                            } else {
                                $transferSummary[$acctKey]['total_debits'] += $txn->amount;
                                $transferSummary[$acctKey]['debit_count']++;
                            }
                        }
                    @endphp

                    @if(count($transferSummary) > 0)
                    <div class="mb-6 border-2 border-blue-300 dark:border-blue-700 rounded-lg overflow-hidden">
                        <div class="bg-blue-600 px-4 py-3">
                            <h4 class="font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                                Transfer Account Summary
                                <span class="ml-2 text-sm font-normal opacity-90">({{ count($transferTransactions) }} transactions across {{ count($transferSummary) }} account(s))</span>
                            </h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Credits (Incoming)</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Count</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Debits (Outgoing)</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Count</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Net Transfer</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @php
                                        // Sort transfer summary: accounts with numbers first, then Unknown last
                                        $sortedTransfers = collect($transferSummary)->sortBy(function($item, $key) {
                                            return $key === 'Unknown' ? 'zzz' : $key;
                                        })->all();
                                    @endphp
                                    @foreach($sortedTransfers as $summary)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($summary['account_number'])
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                    </svg>
                                                    <span class="text-sm font-mono font-bold text-blue-900 dark:text-blue-100">****{{ $summary['account_number'] }}</span>
                                                </div>
                                            @else
                                                <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">Other Transactions</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-green-600 dark:text-green-400">
                                            ${{ number_format($summary['total_credits'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                {{ $summary['credit_count'] }} {{ $summary['credit_count'] === 1 ? 'deposit' : 'deposits' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-red-600 dark:text-red-400">
                                            ${{ number_format($summary['total_debits'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                {{ $summary['debit_count'] }} {{ $summary['debit_count'] === 1 ? 'transfer' : 'transfers' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold {{ ($summary['total_credits'] - $summary['total_debits']) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            ${{ number_format($summary['total_credits'] - $summary['total_debits'], 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">All Transactions</h3>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" id="credits-badge">
                                <span id="credits-badge-count">{{ $credits->count() }}</span> Credits
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200" id="debits-badge">
                                <span id="debits-badge-count">{{ $debits->count() }}</span> Debits
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Category</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($transactions as $index => $txn)
                                @php
                                    // Extract account number for filtering
                                    $txnAccountNum = null;
                                    $description = $txn->description;
                                    if (preg_match('/(?:ACCT|ACCOUNT|A\/C|CHK|CHECKING|SAV|SAVINGS)[\s#:]*([X*\.]+)?(\d{4,})/i', $description, $m)) {
                                        $txnAccountNum = $m[2];
                                    } elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $description, $m)) {
                                        $txnAccountNum = $m[1];
                                    } elseif (preg_match('/([X*\.]{3,})(\d{4,})/', $description, $m)) {
                                        $txnAccountNum = $m[2];
                                    }
                                    $txnAccountKey = $txnAccountNum ?? 'unknown';
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 txn-account-session" id="txn-row-{{ $txn->id }}" data-transaction-id="{{ $txn->id }}" data-account="{{ $txnAccountKey }}">
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $txn->transaction_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $txn->description }}
                                        @if($txn->was_corrected)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            Corrected
                                        </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center" id="category-cell-{{ $txn->id }}">
                                        @php
                                            // Extract account number from description
                                            $accountNumber = null;
                                            $description = $txn->description;

                                            // Match patterns like: ...7072, ****1234, XXXX1234, Chk ...7072
                                            if (preg_match('/(?:ACCT|ACCOUNT|A\/C|CHK|CHECKING|SAV|SAVINGS)[\s#:]*([X*\.]+)?(\d{4,})/i', $description, $matches)) {
                                                $accountNumber = $matches[2];
                                            } elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $description, $matches)) {
                                                $accountNumber = $matches[1];
                                            } elseif (preg_match('/([X*\.]{3,})(\d{4,})/', $description, $matches)) {
                                                $accountNumber = $matches[2];
                                            }

                                            // Determine if this is a transfer category
                                            $isTransfer = in_array($txn->category, ['internal_transfer', 'wire_transfer', 'ach_transfer']);
                                            $transferDirection = null;
                                            if ($isTransfer) {
                                                if (preg_match('/(?:FROM|IN|INCOMING|RECEIVED|DEPOSIT)/i', $description)) {
                                                    $transferDirection = 'in';
                                                } elseif (preg_match('/(?:TO|OUT|OUTGOING|SENT|PAYMENT)/i', $description)) {
                                                    $transferDirection = 'out';
                                                }
                                            }
                                        @endphp
                                        @if($txn->category)
                                            <div class="flex flex-col items-center gap-1">
                                                <button onclick="openCategoryModal({{ $txn->id }}, '{{ addslashes($txn->description) }}', {{ $txn->amount }}, '{{ $txn->type }}')"
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium category-badge {{ $isTransfer ? 'ring-2 ring-offset-1 ring-blue-400 dark:ring-blue-500' : '' }} hover:opacity-80 transition cursor-pointer" data-category="{{ $txn->category }}" title="Click to change category">
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
                                                    {{ ucwords(str_replace('_', ' ', $txn->category)) }}
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
                                            <button onclick="openCategoryModal({{ $txn->id }}, '{{ addslashes($txn->description) }}', {{ $txn->amount }}, '{{ $txn->type }}')"
                                                    class="inline-flex items-center px-2 py-1 text-xs text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                                </svg>
                                                Classify
                                            </button>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right font-medium amount-cell" id="amount-{{ $txn->id }}" data-type="{{ $txn->type }}">
                                        <span class="{{ $txn->type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            ${{ number_format($txn->amount, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center" id="type-badge-{{ $txn->id }}">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $txn->type === 'credit' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                            {{ ucfirst($txn->type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button onclick="toggleType({{ $txn->id }}, '{{ $txn->type }}')"
                                                class="inline-flex items-center px-2 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                                                id="toggle-btn-{{ $txn->id }}">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                            </svg>
                                            Toggle
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Analysis Info -->
            <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Analysis Information</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Session ID</p>
                            <p class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $session->session_id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Model Used</p>
                            <p class="text-sm text-gray-900 dark:text-gray-100">LSC AI</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Pages</p>
                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $session->pages ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Analyzed At</p>
                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $session->created_at->format('M d, Y H:i:s') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Classification Modal -->
    <div id="category-modal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50">
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all w-full max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-6 pt-5 pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Classify Transaction</h3>
                            <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="mb-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Description</p>
                            <p id="modal-description" class="text-sm font-medium text-gray-900 dark:text-white"></p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Category</label>
                            <div id="category-grid" class="grid grid-cols-2 gap-2 max-h-96 overflow-y-auto">
                                <!-- Categories will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end gap-2">
                        <button onclick="closeCategoryModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-500">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentTransactionId = null;
        let currentTransactionType = null;
        let categoriesData = null;

        // Load categories on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
            applyCategoryColors();
        });

        function loadCategories() {
            fetch('{{ route("bankstatement.categories") }}')
                .then(response => response.json())
                .then(data => {
                    categoriesData = data.categories;
                })
                .catch(error => {
                    console.error('Error loading categories:', error);
                });
        }

        function openCategoryModal(transactionId, description, amount, type) {
            console.log('openCategoryModal called:', { transactionId, description, amount, type });
            currentTransactionId = transactionId;
            currentTransactionType = type;

            document.getElementById('modal-description').textContent = description;

            if (!categoriesData) {
                alert('Categories are still loading. Please try again in a moment.');
                return;
            }

            // Get the current category if it exists
            const categoryCell = document.getElementById('category-cell-' + transactionId);
            const currentCategoryBadge = categoryCell ? categoryCell.querySelector('.category-badge') : null;
            const currentCategory = currentCategoryBadge ? currentCategoryBadge.dataset.category : null;

            // Filter categories based on transaction type
            const filteredCategories = Object.entries(categoriesData).filter(([key, cat]) =>
                cat.type === 'both' || cat.type === type
            );

            // Build category grid
            const grid = document.getElementById('category-grid');
            grid.innerHTML = filteredCategories.map(([key, cat]) => {
                const isSelected = key === currentCategory;
                const selectedClasses = isSelected
                    ? 'border-2 border-blue-500 bg-blue-50 dark:bg-blue-900/30'
                    : 'border';
                const checkIcon = isSelected
                    ? '<svg class="w-4 h-4 text-blue-600 dark:text-blue-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
                    : '';

                return `
                    <button onclick="selectCategory('${key}')"
                            class="flex items-center gap-2 px-3 py-2 text-left text-sm ${selectedClasses} rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition category-option"
                            data-category="${key}">
                        <span class="w-3 h-3 rounded-full category-color-${cat.color}"></span>
                        <span class="text-gray-900 dark:text-white">${cat.label}</span>
                        ${checkIcon}
                    </button>
                `;
            }).join('');

            document.getElementById('category-modal').classList.remove('hidden');
        }

        function closeCategoryModal() {
            document.getElementById('category-modal').classList.add('hidden');
            currentTransactionId = null;
            currentTransactionType = null;
        }

        function extractAccountNumber(description) {
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

        function getTransferDirection(description) {
            if (/(?:FROM|IN|INCOMING|RECEIVED|DEPOSIT)/i.test(description)) {
                return 'in';
            } else if (/(?:TO|OUT|OUTGOING|SENT|PAYMENT)/i.test(description)) {
                return 'out';
            }
            return null;
        }

        function selectCategory(categoryKey) {
            console.log('selectCategory called with:', categoryKey);
            console.log('currentTransactionId:', currentTransactionId);

            if (!currentTransactionId) {
                console.error('No transaction ID set');
                return;
            }

            const description = document.getElementById('modal-description').textContent;
            const row = document.querySelector(`tr[data-transaction-id="${currentTransactionId}"]`);

            if (!row) {
                console.error('Could not find transaction row');
                return;
            }

            const amount = parseFloat(row.querySelector('[id^="amount-"]').textContent.replace(/[$,]/g, ''));

            console.log('Sending category request:', {
                transaction_id: currentTransactionId,
                description: description,
                amount: amount,
                type: currentTransactionType,
                category: categoryKey
            });

            fetch('{{ route("bankstatement.toggle-category") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    transaction_id: currentTransactionId,
                    description: description,
                    amount: amount,
                    type: currentTransactionType,
                    category: categoryKey,
                    subcategory: null
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    console.log('Category saved successfully');
                    // Update the category cell
                    const categoryCell = document.getElementById('category-cell-' + currentTransactionId);
                    const categoryInfo = categoriesData[categoryKey];

                    // Check if this is a transfer category
                    const isTransfer = ['internal_transfer', 'wire_transfer', 'ach_transfer'].includes(categoryKey);
                    const accountNumber = extractAccountNumber(description);
                    const transferDirection = isTransfer ? getTransferDirection(description) : null;

                    // Build the category display
                    let categoryHTML = '<div class="flex flex-col items-center gap-1">';

                    // Category badge with optional transfer ring
                    categoryHTML += `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium category-badge bg-${categoryInfo.color}-100 text-${categoryInfo.color}-800 dark:bg-${categoryInfo.color}-900 dark:text-${categoryInfo.color}-200 ${isTransfer ? 'ring-2 ring-offset-1 ring-blue-400 dark:ring-blue-500' : ''}" data-category="${categoryKey}">`;

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

                    categoryHTML += `${categoryInfo.label}</span>`;

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

                    closeCategoryModal();

                    // Show success message
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message || 'Failed to classify transaction', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while classifying the transaction', 'error');
            });
        }

        function applyCategoryColors() {
            document.querySelectorAll('.category-badge').forEach(badge => {
                const category = badge.dataset.category;
                if (categoriesData && categoriesData[category]) {
                    const color = categoriesData[category].color;
                    badge.classList.add(`bg-${color}-100`, `text-${color}-800`);
                    badge.classList.add(`dark:bg-${color}-900`, `dark:text-${color}-200`);
                }
            });
        }

        function showNotification(message, type) {
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

        function toggleType(transactionId, currentType) {
            const btn = document.getElementById('toggle-btn-' + transactionId);
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>...';

            fetch('{{ route("bankstatement.toggle-type") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    transaction_id: transactionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const newType = data.new_type;

                    // Update type badge
                    const typeBadge = document.getElementById('type-badge-' + transactionId);
                    if (newType === 'credit') {
                        typeBadge.innerHTML = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Credit</span>';
                    } else {
                        typeBadge.innerHTML = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Debit</span>';
                    }

                    // Update amount color
                    const amountCell = document.getElementById('amount-' + transactionId);
                    const amountSpan = amountCell.querySelector('span');
                    if (newType === 'credit') {
                        amountSpan.className = 'text-green-600 dark:text-green-400';
                    } else {
                        amountSpan.className = 'text-red-600 dark:text-red-400';
                    }

                    // Update totals
                    document.getElementById('total-credits').textContent = '$' + data.session_totals.total_credits;
                    document.getElementById('total-debits').textContent = '$' + data.session_totals.total_debits;
                    document.getElementById('net-balance').textContent = '$' + data.session_totals.net_flow;

                    // Update counts
                    const creditCount = document.querySelectorAll('[id^="type-badge-"] .bg-green-100').length;
                    const debitCount = document.querySelectorAll('[id^="type-badge-"] .bg-red-100').length;
                    document.getElementById('credit-count').textContent = creditCount;
                    document.getElementById('debit-count').textContent = debitCount;
                    document.getElementById('credits-badge-count').textContent = creditCount;
                    document.getElementById('debits-badge-count').textContent = debitCount;

                    // Add corrected badge if not already present
                    const row = document.getElementById('txn-row-' + transactionId);
                    const descCell = row.querySelectorAll('td')[2];
                    if (!descCell.querySelector('.bg-yellow-100')) {
                        descCell.innerHTML += ' <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Corrected</span>';
                    }

                    // Show success message
                    showToast(data.message, 'success');
                } else {
                    showToast('Error: ' + (data.message || 'Failed to update'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating transaction', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>Toggle';
            });
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} transition-opacity duration-300`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
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

        // ============================================================================
        // Account Filtering Function
        // ============================================================================
        window.filterAccountSession = function(accountKey) {
            console.log('filterAccountSession called with:', accountKey);

            // Update button states
            const buttons = document.querySelectorAll('.account-filter-btn-session');
            console.log('Found buttons:', buttons.length);
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
            const rows = document.querySelectorAll('.txn-account-session');
            console.log('Found transaction rows:', rows.length);
            rows.forEach(row => {
                console.log('Row account:', row.dataset.account, 'Filter:', accountKey);
                if (accountKey === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.account === accountKey ? '' : 'none';
                }
            });

            // Show/hide account summaries with animation
            const summaries = document.querySelectorAll('.account-summary-session-' + accountKey);
            const allSummaries = document.querySelectorAll('[class*="account-summary-session-"]');

            // Hide all summaries first
            allSummaries.forEach(s => {
                s.classList.add('hidden');
                s.style.opacity = '0';
            });

            // Show selected account summary with fade-in animation
            if (accountKey !== 'all' && accountKey !== 'unknown') {
                summaries.forEach(s => {
                    s.classList.remove('hidden');
                    // Trigger animation
                    setTimeout(() => {
                        s.style.opacity = '1';
                        s.style.transform = 'translateY(0)';
                    }, 10);

                    // Scroll to summary
                    setTimeout(() => {
                        s.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 100);
                });
            }
        }
    </script>
</x-app-layout>
