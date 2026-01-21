<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Smart MCA - Analysis Results
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full sm:px-6 lg:px-8">
            <!-- Header with back button -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Analysis Results</h3>
                    <p class="text-gray-600 dark:text-gray-400">{{ count($allTransactions) }} statement(s) processed</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button type="button"
                            id="calculate-revenue-btn"
                            onclick="calculateTrueRevenue()"
                            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Calculate True Revenue
                    </button>
                    <a href="{{ route('smartmca.pricing') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Pricing Calculator
                    </a>
                    <a href="{{ route('smartmca.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back
                    </a>
                </div>
            </div>

            @if(count($allTransactions) > 0)
                @foreach($allTransactions as $statementIndex => $statement)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <!-- Statement Header -->
                            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ $statement['file'] }}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $statement['pages'] }} page(s) | {{ $statement['summary']['transaction_count'] }} transactions</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary Cards -->
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                    <p class="text-sm text-green-600 dark:text-green-400 font-medium">Total Credits</p>
                                    <p class="credit-amount text-2xl font-bold text-green-700 dark:text-green-300" data-value="{{ $statement['summary']['total_credits'] }}">${{ number_format($statement['summary']['total_credits'], 2) }}</p>
                                    <p class="credit-count text-xs text-green-500 dark:text-green-500" data-value="{{ $statement['summary']['credit_count'] }}">{{ $statement['summary']['credit_count'] }} transactions</p>
                                </div>
                                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                                    <p class="text-sm text-red-600 dark:text-red-400 font-medium">Total Debits</p>
                                    <p class="debit-amount text-2xl font-bold text-red-700 dark:text-red-300" data-value="{{ $statement['summary']['total_debits'] }}">${{ number_format($statement['summary']['total_debits'], 2) }}</p>
                                    <p class="debit-count text-xs text-red-500 dark:text-red-500" data-value="{{ $statement['summary']['debit_count'] }}">{{ $statement['summary']['debit_count'] }} transactions</p>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                    <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Net Flow</p>
                                    <p class="net-flow-amount text-2xl font-bold {{ $statement['summary']['net_flow'] >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                        {{ $statement['summary']['net_flow'] >= 0 ? '+' : '' }}${{ number_format($statement['summary']['net_flow'], 2) }}
                                    </p>
                                    <p class="text-xs text-blue-500 dark:text-blue-500">Credits - Debits</p>
                                </div>
                                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                                    <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">Transactions</p>
                                    <p class="text-2xl font-bold text-purple-700 dark:text-purple-300">{{ $statement['summary']['transaction_count'] }}</p>
                                    <p class="text-xs text-purple-500 dark:text-purple-500">Total count</p>
                                </div>
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                                    <p class="text-sm text-yellow-600 dark:text-yellow-400 font-medium">AI Confidence</p>
                                    <p class="text-lg font-bold text-yellow-700 dark:text-yellow-300">
                                        <span class="text-green-600">{{ $statement['summary']['high_confidence'] ?? 0 }}</span> /
                                        <span class="text-yellow-600">{{ $statement['summary']['medium_confidence'] ?? 0 }}</span> /
                                        <span class="text-red-600">{{ $statement['summary']['low_confidence'] ?? 0 }}</span>
                                    </p>
                                    <p class="text-xs text-yellow-500 dark:text-yellow-500">High / Med / Low</p>
                                </div>
                            </div>

                            <!-- Category Breakdown Summary -->
                            @php
                                // Calculate detailed category breakdown
                                $categoryBreakdown = [
                                    'credits' => [],
                                    'debits' => [],
                                    'mca_positions' => [],
                                    'risk_indicators' => []
                                ];

                                foreach ($statement['transactions'] as $txn) {
                                    $desc = strtolower($txn['description']);
                                    $amount = $txn['amount'];
                                    $type = $txn['type'];

                                    // Determine category
                                    $category = 'Other';
                                    if (preg_match('/paypal/i', $desc)) $category = 'PayPal';
                                    elseif (preg_match('/cash app|cashapp/i', $desc)) $category = 'Cash App';
                                    elseif (preg_match('/zelle/i', $desc)) $category = 'Zelle';
                                    elseif (preg_match('/atm/i', $desc)) $category = 'ATM';
                                    elseif (preg_match('/venmo/i', $desc)) $category = 'Venmo';
                                    elseif (preg_match('/wire|wt fed/i', $desc)) $category = 'Wire Transfer';
                                    elseif (preg_match('/branch|edeposit|withdrawal made/i', $desc)) $category = 'Branch/Deposit';
                                    elseif (preg_match('/transfer from|transferred/i', $desc)) $category = 'Internal Transfer';
                                    elseif (preg_match('/kapitus|merchant|mca|loan|funding|advance/i', $desc)) $category = 'MCA/Loans';
                                    elseif (preg_match('/check|chk/i', $desc)) $category = 'Check';
                                    elseif (preg_match('/ach|direct|deposit/i', $desc)) $category = 'ACH/Direct';
                                    elseif (preg_match('/pos|purchase|debit card/i', $desc)) $category = 'POS/Purchase';

                                    // Add to credits or debits
                                    $key = $type === 'credit' ? 'credits' : 'debits';
                                    if (!isset($categoryBreakdown[$key][$category])) {
                                        $categoryBreakdown[$key][$category] = ['count' => 0, 'total' => 0];
                                    }
                                    $categoryBreakdown[$key][$category]['count']++;
                                    $categoryBreakdown[$key][$category]['total'] += $amount;

                                    // Detect MCA positions
                                    if (preg_match('/kapitus|merchant marketplace|yellowstone|credibly|fundbox|ondeck|bluevine|kabbage|can capital|forward|rapid|swift|clear|bizfi|lendio/i', $desc)) {
                                        $mcaName = 'Unknown MCA';
                                        if (preg_match('/kapitus/i', $desc)) $mcaName = 'Kapitus';
                                        elseif (preg_match('/merchant marketplace/i', $desc)) $mcaName = 'Merchant Marketplace';
                                        elseif (preg_match('/yellowstone/i', $desc)) $mcaName = 'Yellowstone';
                                        elseif (preg_match('/credibly/i', $desc)) $mcaName = 'Credibly';
                                        elseif (preg_match('/fundbox/i', $desc)) $mcaName = 'Fundbox';
                                        elseif (preg_match('/ondeck/i', $desc)) $mcaName = 'OnDeck';
                                        elseif (preg_match('/bluevine/i', $desc)) $mcaName = 'BlueVine';
                                        elseif (preg_match('/kabbage/i', $desc)) $mcaName = 'Kabbage';

                                        if (!isset($categoryBreakdown['mca_positions'][$mcaName])) {
                                            $categoryBreakdown['mca_positions'][$mcaName] = ['payments' => 0, 'payment_count' => 0, 'funding' => 0];
                                        }
                                        if ($type === 'debit') {
                                            $categoryBreakdown['mca_positions'][$mcaName]['payments'] += $amount;
                                            $categoryBreakdown['mca_positions'][$mcaName]['payment_count']++;
                                        } else {
                                            $categoryBreakdown['mca_positions'][$mcaName]['funding'] += $amount;
                                        }
                                    }

                                    // Risk indicators
                                    if (preg_match('/nsf|overdraft|returned|insufficient/i', $desc)) {
                                        if (!isset($categoryBreakdown['risk_indicators']['NSF/Overdraft'])) {
                                            $categoryBreakdown['risk_indicators']['NSF/Overdraft'] = ['count' => 0, 'total' => 0];
                                        }
                                        $categoryBreakdown['risk_indicators']['NSF/Overdraft']['count']++;
                                        $categoryBreakdown['risk_indicators']['NSF/Overdraft']['total'] += $amount;
                                    }
                                    if (preg_match('/negative|-\d+\.\d{2}$/i', $desc) && $amount > 1000) {
                                        if (!isset($categoryBreakdown['risk_indicators']['Large Negative Balance'])) {
                                            $categoryBreakdown['risk_indicators']['Large Negative Balance'] = ['count' => 0, 'total' => 0];
                                        }
                                        $categoryBreakdown['risk_indicators']['Large Negative Balance']['count']++;
                                    }
                                }

                                // Sort by total descending
                                arsort($categoryBreakdown['credits']);
                                arsort($categoryBreakdown['debits']);

                                // Calculate totals
                                $totalCreditsSum = array_sum(array_column($categoryBreakdown['credits'], 'total'));
                                $totalDebitsSum = array_sum(array_column($categoryBreakdown['debits'], 'total'));
                            @endphp

                            <div class="mb-6">
                                <button type="button" class="category-breakdown-toggle w-full flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors" onclick="toggleCategoryBreakdown({{ $statementIndex }})">
                                    <span class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300">
                                        <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        Category Breakdown Analysis
                                    </span>
                                    <svg class="breakdown-arrow-{{ $statementIndex }} w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <div id="breakdown-content-{{ $statementIndex }}" class="hidden mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Credits by Category -->
                                    <div class="bg-green-50 dark:bg-green-900/10 rounded-lg p-4">
                                        <h5 class="text-sm font-semibold text-green-700 dark:text-green-400 mb-3 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            Credits by Category
                                        </h5>
                                        <div class="space-y-2">
                                            @foreach($categoryBreakdown['credits'] as $catName => $catData)
                                                @php $pct = $totalCreditsSum > 0 ? round(($catData['total'] / $totalCreditsSum) * 100, 1) : 0; @endphp
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-gray-600 dark:text-gray-400">{{ $catName }}</span>
                                                    <div class="flex items-center">
                                                        <span class="text-green-600 dark:text-green-400 font-medium">${{ number_format($catData['total'], 2) }}</span>
                                                        <span class="text-xs text-gray-400 ml-2">({{ $catData['count'] }}) {{ $pct }}%</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                            @if(count($categoryBreakdown['credits']) === 0)
                                                <p class="text-sm text-gray-400">No credits found</p>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Debits by Category -->
                                    <div class="bg-red-50 dark:bg-red-900/10 rounded-lg p-4">
                                        <h5 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-3 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                            </svg>
                                            Debits by Category
                                        </h5>
                                        <div class="space-y-2">
                                            @foreach($categoryBreakdown['debits'] as $catName => $catData)
                                                @php $pct = $totalDebitsSum > 0 ? round(($catData['total'] / $totalDebitsSum) * 100, 1) : 0; @endphp
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-gray-600 dark:text-gray-400">{{ $catName }}</span>
                                                    <div class="flex items-center">
                                                        <span class="text-red-600 dark:text-red-400 font-medium">${{ number_format($catData['total'], 2) }}</span>
                                                        <span class="text-xs text-gray-400 ml-2">({{ $catData['count'] }}) {{ $pct }}%</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                            @if(count($categoryBreakdown['debits']) === 0)
                                                <p class="text-sm text-gray-400">No debits found</p>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- MCA Positions -->
                                    @if(count($categoryBreakdown['mca_positions']) > 0)
                                    <div class="bg-yellow-50 dark:bg-yellow-900/10 rounded-lg p-4">
                                        <h5 class="text-sm font-semibold text-yellow-700 dark:text-yellow-400 mb-3 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            MCA Positions Detected ({{ count($categoryBreakdown['mca_positions']) }})
                                        </h5>
                                        <div class="space-y-2">
                                            @foreach($categoryBreakdown['mca_positions'] as $mcaName => $mcaData)
                                                <div class="flex items-center justify-between text-sm border-b border-yellow-200 dark:border-yellow-800 pb-2">
                                                    <span class="text-gray-700 dark:text-gray-300 font-medium">{{ $mcaName }}</span>
                                                    <div class="text-right">
                                                        @if($mcaData['funding'] > 0)
                                                            <div class="text-green-600 text-xs">Funded: ${{ number_format($mcaData['funding'], 2) }}</div>
                                                        @endif
                                                        <div class="text-red-600 text-xs">Payments: ${{ number_format($mcaData['payments'], 2) }} ({{ $mcaData['payment_count'] }}x)</div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Risk Indicators -->
                                    @if(count($categoryBreakdown['risk_indicators']) > 0)
                                    <div class="bg-orange-50 dark:bg-orange-900/10 rounded-lg p-4">
                                        <h5 class="text-sm font-semibold text-orange-700 dark:text-orange-400 mb-3 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                            Risk Indicators
                                        </h5>
                                        <div class="space-y-2">
                                            @foreach($categoryBreakdown['risk_indicators'] as $riskName => $riskData)
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-orange-600 dark:text-orange-400">{{ $riskName }}</span>
                                                    <span class="text-orange-700 dark:text-orange-300 font-medium">{{ $riskData['count'] }} occurrences</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Category Filter Buttons -->
                            @php
                                $categories = [
                                    'all' => ['label' => 'All', 'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16', 'count' => count($statement['transactions'])],
                                    'paypal' => ['label' => 'PayPal', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'count' => 0, 'pattern' => 'paypal'],
                                    'cashapp' => ['label' => 'Cash App', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'count' => 0, 'pattern' => 'cash app'],
                                    'zelle' => ['label' => 'Zelle', 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', 'count' => 0, 'pattern' => 'zelle'],
                                    'atm' => ['label' => 'ATM', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'count' => 0, 'pattern' => 'atm'],
                                    'mca' => ['label' => 'MCA/Loans', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'count' => 0, 'pattern' => 'kapitus|merchant|mca|loan|funding|advance'],
                                    'wire' => ['label' => 'Wire', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'count' => 0, 'pattern' => 'wire|wt fed'],
                                    'branch' => ['label' => 'Branch', 'icon' => 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z', 'count' => 0, 'pattern' => 'branch|edeposit|withdrawal made'],
                                    'transfer' => ['label' => 'Transfer', 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', 'count' => 0, 'pattern' => 'transfer from|transferred'],
                                ];
                                // Count transactions per category
                                foreach ($statement['transactions'] as $txn) {
                                    $desc = strtolower($txn['description']);
                                    foreach ($categories as $key => &$cat) {
                                        if ($key !== 'all' && isset($cat['pattern']) && preg_match('/' . $cat['pattern'] . '/i', $desc)) {
                                            $cat['count']++;
                                        }
                                    }
                                }
                            @endphp
                            <div class="mb-4">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Filter by Category:</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($categories as $key => $cat)
                                        @if($cat['count'] > 0 || $key === 'all')
                                        <button type="button"
                                                class="category-filter-btn inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-full transition-colors {{ $key === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
                                                data-category="{{ $key }}"
                                                data-pattern="{{ $cat['pattern'] ?? '' }}"
                                                data-statement="{{ $statementIndex }}">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cat['icon'] }}"></path>
                                            </svg>
                                            {{ $cat['label'] }}
                                            <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full {{ $key === 'all' ? 'bg-blue-500' : 'bg-gray-200 dark:bg-gray-600' }}">{{ $cat['count'] }}</span>
                                        </button>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            <!-- Filter Summary (shown when category is selected) -->
                            <div class="filter-summary hidden mb-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-indigo-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                        </svg>
                                        <span class="text-sm text-indigo-700 dark:text-indigo-300">
                                            Showing <span class="filter-count font-bold">0</span> <span class="filter-category-name font-medium">filtered</span> transactions
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-4 text-sm">
                                        <span class="text-green-600 dark:text-green-400">Credits: <span class="filter-credits font-bold">$0.00</span></span>
                                        <span class="text-red-600 dark:text-red-400">Debits: <span class="filter-debits font-bold">$0.00</span></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Info Banner -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        <strong>AI Learning:</strong> Click the toggle button to correct any misclassified transactions. The AI will learn from your corrections and improve future analysis.
                                    </p>
                                </div>
                            </div>

                            @if(!empty($statement['error']))
                                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div>
                                            <p class="text-sm font-medium text-red-700 dark:text-red-300">Extraction Error</p>
                                            <p class="text-sm text-red-600 dark:text-red-400">{{ $statement['error'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Transactions Table -->
                            @if(count($statement['transactions']) > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Confidence</th>
                                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($statement['transactions'] as $txnIndex => $txn)
                                                @php
                                                    $txnDesc = strtolower($txn['description']);
                                                    $txnCategories = [];
                                                    if (preg_match('/paypal/i', $txnDesc)) $txnCategories[] = 'paypal';
                                                    if (preg_match('/cash app|cashapp/i', $txnDesc)) $txnCategories[] = 'cashapp';
                                                    if (preg_match('/zelle/i', $txnDesc)) $txnCategories[] = 'zelle';
                                                    if (preg_match('/atm/i', $txnDesc)) $txnCategories[] = 'atm';
                                                    if (preg_match('/kapitus|merchant|mca|loan|funding|advance/i', $txnDesc)) $txnCategories[] = 'mca';
                                                    if (preg_match('/wire|wt fed/i', $txnDesc)) $txnCategories[] = 'wire';
                                                    if (preg_match('/branch|edeposit|withdrawal made/i', $txnDesc)) $txnCategories[] = 'branch';
                                                    if (preg_match('/transfer from|transferred/i', $txnDesc)) $txnCategories[] = 'transfer';
                                                @endphp
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transaction-row" data-index="{{ $statementIndex }}-{{ $txnIndex }}" data-statement="{{ $statementIndex }}" data-categories="{{ implode(',', $txnCategories) }}" data-type="{{ $txn['type'] }}">
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                        {{ $txn['date'] }}
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 max-w-md" title="{{ $txn['description'] }}">
                                                        <div class="truncate">{{ \Illuminate\Support\Str::limit($txn['description'], 50) }}</div>
                                                        @if(!empty($txn['corrected']))
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 mt-1">
                                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                </svg>
                                                                Learned
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                                        @php
                                                            $confidence = $txn['confidence'] ?? 0.8;
                                                            $confidenceLabel = $txn['confidence_label'] ?? 'medium';
                                                            $confidencePercent = round($confidence * 100);
                                                        @endphp
                                                        <div class="flex flex-col items-center">
                                                            <div class="w-16 bg-gray-200 dark:bg-gray-600 rounded-full h-2 mb-1">
                                                                <div class="h-2 rounded-full {{ $confidenceLabel === 'high' ? 'bg-green-500' : ($confidenceLabel === 'medium' ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $confidencePercent }}%"></div>
                                                            </div>
                                                            <span class="text-xs {{ $confidenceLabel === 'high' ? 'text-green-600 dark:text-green-400' : ($confidenceLabel === 'medium' ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                                                                {{ $confidencePercent }}%
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                                        <span class="type-badge px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $txn['type'] === 'credit' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                                            {{ ucfirst($txn['type']) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium amount-cell {{ $txn['type'] === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                        <span class="amount-prefix">{{ $txn['type'] === 'credit' ? '+' : '-' }}</span>${{ number_format($txn['amount'], 2) }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                                        <button type="button"
                                                                class="toggle-type-btn inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                                                data-id="{{ $txn['id'] ?? '' }}"
                                                                data-session-id="{{ $statement['session_id'] ?? '' }}"
                                                                data-description="{{ $txn['description'] }}"
                                                                data-current-type="{{ $txn['type'] }}"
                                                                data-amount="{{ $txn['amount'] }}"
                                                                title="Click to toggle and teach AI">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                            </svg>
                                                            Toggle
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="mt-2">No transactions could be extracted from this statement.</p>
                                    <p class="text-sm">The PDF format may not be supported or the statement may be image-based.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No results</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No statements were processed successfully.</p>
                        <div class="mt-6">
                            <a href="{{ route('smartmca.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition ease-in-out duration-150">
                                Try Again
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-4 right-4 hidden transform transition-all duration-300 ease-in-out">
        <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span id="toast-message">Correction saved!</span>
        </div>
    </div>

    <!-- True Revenue Modal -->
    <div id="revenue-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeRevenueModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                                True Revenue Analysis
                            </h3>
                            <div class="mt-4" id="revenue-content">
                                <div class="text-center py-8">
                                    <svg class="animate-spin h-8 w-8 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="mt-2 text-gray-500 dark:text-gray-400">Calculating true revenue...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeRevenueModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Session IDs for true revenue calculation
        const sessionIds = @json(collect($allTransactions)->pluck('session_id')->filter()->values());

        // Toggle category breakdown visibility
        function toggleCategoryBreakdown(statementIndex) {
            const content = document.getElementById('breakdown-content-' + statementIndex);
            const arrow = document.querySelector('.breakdown-arrow-' + statementIndex);

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }

        function openRevenueModal() {
            document.getElementById('revenue-modal').classList.remove('hidden');
        }

        function closeRevenueModal() {
            document.getElementById('revenue-modal').classList.add('hidden');
        }

        // Calculate MCA Offer based on True Revenue
        async function calculateMcaOffer(trueRevenue, existingDailyPayment = 0) {
            const offerContent = document.getElementById('mca-offer-content');
            if (!offerContent) return;

            try {
                // Calculate monthly revenue (assume statement covers ~30 days)
                const monthlyRevenue = trueRevenue;

                // Request a standard offer (50% of monthly revenue as funding)
                const requestedAmount = monthlyRevenue * 0.5;

                const response = await fetch('{{ route("smartmca.pricing.calculate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        monthly_true_revenue: monthlyRevenue,
                        existing_daily_payment: existingDailyPayment,
                        requested_amount: requestedAmount,
                        position: existingDailyPayment > 0 ? 2 : 1,
                        term_months: 6,
                        factor_rate: 1.35
                    })
                });

                const result = await response.json();

                if (result.success && result.data && result.data.offer) {
                    const offer = result.data.offer;
                    const capacity = result.data.capacity || {};

                    offerContent.innerHTML = `
                        <div class="space-y-4">
                            <!-- Capacity Visualization -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Withhold Capacity (20% Max)</span>
                                    <span class="text-sm font-bold ${capacity.at_capacity ? 'text-red-600' : 'text-green-600'}">${(capacity.current_withhold_percent || 0).toFixed(1)}% Used</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-4 overflow-hidden">
                                    <div class="h-4 rounded-full ${capacity.current_withhold_percent > 15 ? 'bg-yellow-500' : 'bg-green-500'}" style="width: ${Math.min((capacity.current_withhold_percent || 0), 100)}%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <span>Existing: $${(existingDailyPayment || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}/day</span>
                                    <span>Available: $${(capacity.remaining_daily_capacity || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}/day</span>
                                </div>
                            </div>

                            <!-- Offer Details -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-center">
                                    <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Funding Amount</p>
                                    <p class="text-xl font-bold text-blue-700 dark:text-blue-300">$${offer.funding_amount.toLocaleString('en-US', {minimumFractionDigits: 0})}</p>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-center">
                                    <p class="text-xs text-green-600 dark:text-green-400 font-medium">Daily Payment</p>
                                    <p class="text-xl font-bold text-green-700 dark:text-green-300">$${offer.daily_payment.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                </div>
                                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3 text-center">
                                    <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">Factor Rate</p>
                                    <p class="text-xl font-bold text-purple-700 dark:text-purple-300">${offer.factor_rate.toFixed(2)}</p>
                                </div>
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3 text-center">
                                    <p class="text-xs text-yellow-600 dark:text-yellow-400 font-medium">Term</p>
                                    <p class="text-xl font-bold text-yellow-700 dark:text-yellow-300">${offer.term_months} months</p>
                                </div>
                            </div>

                            <!-- Key Metrics -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Payback</p>
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">$${offer.payback_amount.toLocaleString('en-US', {minimumFractionDigits: 0})}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Cost of Capital</p>
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">$${offer.cost_of_capital.toLocaleString('en-US', {minimumFractionDigits: 0})}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">New Withhold</p>
                                        <p class="text-sm font-semibold ${offer.withhold_breakdown.new_withhold_percent > 18 ? 'text-yellow-600' : 'text-green-600'}">${offer.withhold_breakdown.new_withhold_percent.toFixed(1)}%</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <a href="{{ route('smartmca.pricing') }}?revenue=${monthlyRevenue}&existing=${existingDailyPayment}"
                                   class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    Adjust Terms
                                </a>
                                <button onclick="generateScenarios(${monthlyRevenue}, ${existingDailyPayment})"
                                        class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                    </svg>
                                    Compare Scenarios
                                </button>
                            </div>
                        </div>
                    `;
                } else if (result.data && result.data.decline_reason) {
                    // Declined - show reason
                    offerContent.innerHTML = `
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 text-center">
                            <svg class="w-8 h-8 text-red-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <p class="font-semibold text-red-700 dark:text-red-300">Cannot Fund at This Time</p>
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1">${result.data.decline_reason.replace(/_/g, ' ')}</p>
                            ${result.data.explanation ? `<p class="text-xs text-gray-600 dark:text-gray-400 mt-2">${result.data.explanation}</p>` : ''}
                            ${result.data.capacity ? `
                            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                Current withhold: ${result.data.capacity.current_withhold_percent.toFixed(1)}% |
                                Max allowed: 20%
                            </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    offerContent.innerHTML = `
                        <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                            <p class="text-sm">Unable to calculate offer. Please try the <a href="{{ route('smartmca.pricing') }}" class="text-blue-600 hover:underline">Pricing Calculator</a>.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('MCA Offer calculation error:', error);
                offerContent.innerHTML = `
                    <div class="text-center py-4 text-red-500">
                        <p class="text-sm">Error calculating offer. <a href="{{ route('smartmca.pricing') }}" class="text-blue-600 hover:underline">Try manual calculator</a>.</p>
                    </div>
                `;
            }
        }

        // Generate scenario comparison
        async function generateScenarios(monthlyRevenue, existingDailyPayment) {
            const offerContent = document.getElementById('mca-offer-content');
            if (!offerContent) return;

            offerContent.innerHTML = `
                <div class="text-center py-4">
                    <svg class="animate-spin h-6 w-6 text-purple-500 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Generating scenarios...</p>
                </div>
            `;

            try {
                const response = await fetch('{{ route("smartmca.pricing.scenarios") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        monthly_true_revenue: monthlyRevenue,
                        existing_daily_payment: existingDailyPayment,
                        requested_amount: monthlyRevenue * 0.5
                    })
                });

                const result = await response.json();

                if (result.success && result.scenarios) {
                    const scenarios = result.scenarios;
                    let scenarioHtml = '<div class="space-y-3">';

                    const scenarioLabels = {
                        'conservative_short': { name: 'Conservative', desc: '4 months @ 1.45', color: 'yellow' },
                        'standard_medium': { name: 'Standard', desc: '6 months @ 1.35', color: 'blue' },
                        'aggressive_long': { name: 'Aggressive', desc: '9 months @ 1.25', color: 'green' }
                    };

                    for (const [key, scenario] of Object.entries(scenarios)) {
                        const label = scenarioLabels[key] || { name: key, desc: '', color: 'gray' };
                        if (scenario.can_fund) {
                            scenarioHtml += `
                                <div class="bg-${label.color}-50 dark:bg-${label.color}-900/20 rounded-lg p-3">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-semibold text-${label.color}-700 dark:text-${label.color}-300">${label.name}</span>
                                        <span class="text-xs text-${label.color}-600 dark:text-${label.color}-400">${label.desc}</span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-400">Funding</p>
                                            <p class="font-semibold">$${scenario.funding_amount.toLocaleString()}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-400">Daily</p>
                                            <p class="font-semibold">$${scenario.daily_payment.toFixed(2)}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-400">Cost</p>
                                            <p class="font-semibold">$${scenario.cost_of_capital.toLocaleString()}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-400">Withhold</p>
                                            <p class="font-semibold">${scenario.withhold_percent.toFixed(1)}%</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            scenarioHtml += `
                                <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 opacity-50">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-gray-600 dark:text-gray-400">${label.name}</span>
                                        <span class="text-xs text-red-500">Not Available</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">${scenario.reason || 'Exceeds capacity'}</p>
                                </div>
                            `;
                        }
                    }

                    scenarioHtml += `
                        <button onclick="calculateMcaOffer(${monthlyRevenue}, ${existingDailyPayment})"
                                class="w-full mt-2 inline-flex items-center justify-center px-4 py-2 bg-gray-200 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-500 transition">
                            Back to Standard Offer
                        </button>
                    </div>`;

                    offerContent.innerHTML = scenarioHtml;
                }
            } catch (error) {
                console.error('Scenario generation error:', error);
                offerContent.innerHTML = `
                    <div class="text-center py-4 text-red-500">
                        <p class="text-sm">Error generating scenarios.</p>
                    </div>
                `;
            }
        }

        async function calculateTrueRevenue() {
            console.log('Calculate True Revenue clicked');
            console.log('Session IDs:', sessionIds);

            if (!sessionIds || sessionIds.length === 0) {
                alert('No session IDs found. Please analyze a statement first.');
                return;
            }

            openRevenueModal();

            try {
                const response = await fetch('{{ route("smartmca.calculateRevenue") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        session_ids: sessionIds
                    })
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('revenue-content').innerHTML = `
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                    <p class="text-sm text-green-600 dark:text-green-400 font-medium">True Revenue</p>
                                    <p class="text-2xl font-bold text-green-700 dark:text-green-300">$${data.true_revenue.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                    <p class="text-xs text-green-500">${data.revenue_deposits} revenue deposits</p>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                    <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Total Credits</p>
                                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">$${data.total_credits.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                    <p class="text-xs text-blue-500">${data.total_credit_count} total deposits</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                                    <p class="text-sm text-yellow-600 dark:text-yellow-400 font-medium">Excluded (Non-Revenue)</p>
                                    <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">$${data.excluded_amount.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                    <p class="text-xs text-yellow-500">${data.excluded_count} transfers/loans/refunds</p>
                                </div>
                                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                                    <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">Revenue Ratio</p>
                                    <p class="text-2xl font-bold text-purple-700 dark:text-purple-300">${data.revenue_ratio}%</p>
                                    <p class="text-xs text-purple-500">of total credits</p>
                                </div>
                            </div>
                            ${data.excluded_items && data.excluded_items.length > 0 ? `
                            <div class="mt-4">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Excluded Transactions:</p>
                                <div class="max-h-40 overflow-y-auto bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                                    <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                        ${data.excluded_items.map(item => `
                                            <li class="flex justify-between">
                                                <span class="truncate mr-2" title="${item.description}">${item.description.substring(0, 40)}${item.description.length > 40 ? '...' : ''}</span>
                                                <span class="text-yellow-600 dark:text-yellow-400 whitespace-nowrap">$${item.amount.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                                            </li>
                                        `).join('')}
                                    </ul>
                                </div>
                            </div>
                            ` : ''}
                            <div class="mt-4 p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                <p class="text-sm text-green-700 dark:text-green-300">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Analysis finalized and saved to database.
                                </p>
                            </div>
                            ${data.fcs_pdf_url ? `
                            <div class="mt-4">
                                <a href="${data.fcs_pdf_url}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Download File Control Sheet (PDF)
                                </a>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${data.fcs_pdf_filename}</p>
                            </div>
                            ` : ''}

                            <!-- MCA Offer Section -->
                            <div id="mca-offer-section" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                                <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    MCA Offer Calculation (20% Withhold Cap)
                                </h4>
                                <div id="mca-offer-content" class="text-center py-4">
                                    <svg class="animate-spin h-6 w-6 text-blue-500 mx-auto" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Calculating MCA offer...</p>
                                </div>
                            </div>
                        </div>
                    `;

                    // Auto-calculate MCA offer after true revenue is displayed
                    calculateMcaOffer(data.true_revenue, data.existing_daily_payment || 0);
                } else {
                    document.getElementById('revenue-content').innerHTML = `
                        <div class="text-center py-8 text-red-500">
                            <svg class="h-8 w-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>${data.message || 'Error calculating revenue'}</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('revenue-content').innerHTML = `
                    <div class="text-center py-8 text-red-500">
                        <svg class="h-8 w-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p>Error connecting to server</p>
                    </div>
                `;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.toggle-type-btn');
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');

            // Calculate True Revenue button
            const revenueBtn = document.getElementById('calculate-revenue-btn');
            if (revenueBtn) {
                revenueBtn.addEventListener('click', calculateTrueRevenue);
            }

            // Category Filter Buttons
            const categoryButtons = document.querySelectorAll('.category-filter-btn');
            categoryButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const category = this.dataset.category;
                    const statementIndex = this.dataset.statement;
                    const statementCard = this.closest('.bg-white, .dark\\:bg-gray-800');
                    const rows = statementCard.querySelectorAll('.transaction-row');

                    // Update active button state for this statement
                    const buttonsInStatement = statementCard.querySelectorAll('.category-filter-btn');
                    buttonsInStatement.forEach(btn => {
                        if (btn === this) {
                            btn.classList.remove('bg-gray-100', 'text-gray-700', 'dark:bg-gray-700', 'dark:text-gray-300', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                            btn.classList.add('bg-blue-600', 'text-white');
                            btn.querySelector('span').classList.remove('bg-gray-200', 'dark:bg-gray-600');
                            btn.querySelector('span').classList.add('bg-blue-500');
                        } else {
                            btn.classList.remove('bg-blue-600', 'text-white');
                            btn.classList.add('bg-gray-100', 'text-gray-700', 'dark:bg-gray-700', 'dark:text-gray-300', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                            btn.querySelector('span').classList.remove('bg-blue-500');
                            btn.querySelector('span').classList.add('bg-gray-200', 'dark:bg-gray-600');
                        }
                    });

                    // Filter rows
                    let visibleCount = 0;
                    let visibleCredits = 0;
                    let visibleDebits = 0;

                    rows.forEach(row => {
                        const rowCategories = (row.dataset.categories || '').split(',').filter(c => c);
                        const rowType = row.dataset.type;
                        const amount = parseFloat(row.querySelector('.amount-cell').textContent.replace(/[$,+\-]/g, '')) || 0;

                        if (category === 'all' || rowCategories.includes(category)) {
                            row.classList.remove('hidden');
                            visibleCount++;
                            if (rowType === 'credit') {
                                visibleCredits += amount;
                            } else {
                                visibleDebits += amount;
                            }
                        } else {
                            row.classList.add('hidden');
                        }
                    });

                    // Update filtered summary display
                    const filterSummary = statementCard.querySelector('.filter-summary');
                    if (filterSummary) {
                        if (category === 'all') {
                            filterSummary.classList.add('hidden');
                        } else {
                            filterSummary.classList.remove('hidden');
                            filterSummary.querySelector('.filter-count').textContent = visibleCount;
                            filterSummary.querySelector('.filter-credits').textContent = '$' + visibleCredits.toLocaleString('en-US', {minimumFractionDigits: 2});
                            filterSummary.querySelector('.filter-debits').textContent = '$' + visibleDebits.toLocaleString('en-US', {minimumFractionDigits: 2});
                            filterSummary.querySelector('.filter-category-name').textContent = this.textContent.trim().split(/\s+/)[0];
                        }
                    }
                });
            });

            function showToast(message, isError = false) {
                toastMessage.textContent = message;
                toast.querySelector('div').className = isError
                    ? 'bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center'
                    : 'bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center';
                toast.classList.remove('hidden');
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 3000);
            }

            function formatCurrency(amount) {
                return '$' + Math.abs(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function updateSummaryCards(statementCard, amount, fromType, toType) {
                const creditAmountEl = statementCard.querySelector('.credit-amount');
                const debitAmountEl = statementCard.querySelector('.debit-amount');
                const creditCountEl = statementCard.querySelector('.credit-count');
                const debitCountEl = statementCard.querySelector('.debit-count');
                const netFlowEl = statementCard.querySelector('.net-flow-amount');

                // Parse current values
                let creditAmount = parseFloat(creditAmountEl.dataset.value || 0);
                let debitAmount = parseFloat(debitAmountEl.dataset.value || 0);
                let creditCount = parseInt(creditCountEl.dataset.value || 0);
                let debitCount = parseInt(debitCountEl.dataset.value || 0);

                // Update based on toggle direction
                if (fromType === 'credit' && toType === 'debit') {
                    creditAmount -= amount;
                    debitAmount += amount;
                    creditCount--;
                    debitCount++;
                } else if (fromType === 'debit' && toType === 'credit') {
                    debitAmount -= amount;
                    creditAmount += amount;
                    debitCount--;
                    creditCount++;
                }

                // Update data attributes
                creditAmountEl.dataset.value = creditAmount;
                debitAmountEl.dataset.value = debitAmount;
                creditCountEl.dataset.value = creditCount;
                debitCountEl.dataset.value = debitCount;

                // Update displayed values
                creditAmountEl.textContent = formatCurrency(creditAmount);
                debitAmountEl.textContent = formatCurrency(debitAmount);
                creditCountEl.textContent = creditCount + ' transactions';
                debitCountEl.textContent = debitCount + ' transactions';

                // Update net flow
                const netFlow = creditAmount - debitAmount;
                netFlowEl.textContent = (netFlow >= 0 ? '+' : '') + formatCurrency(netFlow);
                netFlowEl.className = 'net-flow-amount text-2xl font-bold ' +
                    (netFlow >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300');
            }

            toggleButtons.forEach(button => {
                button.addEventListener('click', async function() {
                    const row = this.closest('tr');
                    const statementCard = this.closest('.bg-white, .dark\\:bg-gray-800').closest('.mb-6');
                    const txnId = this.dataset.id;
                    const sessionId = this.dataset.sessionId;
                    const description = this.dataset.description;
                    const currentType = this.dataset.currentType;
                    const amount = parseFloat(this.dataset.amount);
                    const newType = currentType === 'credit' ? 'debit' : 'credit';

                    // Disable button temporarily
                    this.disabled = true;
                    this.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

                    try {
                        const response = await fetch('{{ route("smartmca.correction") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                description: description,
                                original_type: currentType,
                                correct_type: newType,
                                amount: amount,
                                transaction_id: txnId ? parseInt(txnId) : null,
                                session_id: sessionId || null
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Update the UI
                            this.dataset.currentType = newType;

                            // Update type badge
                            const typeBadge = row.querySelector('.type-badge');
                            typeBadge.textContent = newType.charAt(0).toUpperCase() + newType.slice(1);
                            typeBadge.className = 'type-badge px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ' +
                                (newType === 'credit'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200');

                            // Update amount styling
                            const amountCell = row.querySelector('.amount-cell');
                            const amountPrefix = row.querySelector('.amount-prefix');
                            amountCell.className = 'px-4 py-3 whitespace-nowrap text-sm text-right font-medium amount-cell ' +
                                (newType === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400');
                            amountPrefix.textContent = newType === 'credit' ? '+' : '-';

                            // Update summary cards
                            updateSummaryCards(statementCard, amount, currentType, newType);

                            // Show message with count of similar transactions updated
                            let message = 'Correction saved! AI will learn from this.';
                            if (data.similar_updated > 0) {
                                message += ` (${data.similar_updated} similar transactions also updated)`;
                            }
                            showToast(message);
                        } else {
                            showToast('Failed to save correction', true);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showToast('Error saving correction', true);
                    }

                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>Toggle';
                });
            });
        });
    </script>
</x-app-layout>
