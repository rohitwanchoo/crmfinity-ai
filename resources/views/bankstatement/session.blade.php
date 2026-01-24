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
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="neutral">
                    <p class="text-sm text-gray-500 dark:text-gray-400">File</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate" title="{{ $session->filename }}">{{ $session->filename }}</p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="neutral">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Transactions</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $session->total_transactions }}</p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="credit">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Credits</p>
                    <p class="metric-value text-2xl font-bold text-green-600 dark:text-green-400" id="total-credits">${{ number_format($session->total_credits, 2) }}</p>
                    <p class="metric-subtext text-xs text-gray-500"><span id="credit-count">{{ $credits->count() }}</span> transactions</p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="debit">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Debits</p>
                    <p class="metric-value text-2xl font-bold text-red-600 dark:text-red-400" id="total-debits">${{ number_format($session->total_debits, 2) }}</p>
                    <p class="metric-subtext text-xs text-gray-500"><span id="debit-count">{{ $debits->count() }}</span> transactions</p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="neutral">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Net Balance</p>
                    <p class="text-2xl font-bold {{ $session->net_flow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" id="net-balance">
                        ${{ number_format($session->net_flow, 2) }}
                    </p>
                </div>
                <div class="metric-card bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm transition-all duration-300" data-metric-type="neutral">
                    <p class="text-sm text-gray-500 dark:text-gray-400">API Cost</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($session->api_cost ?? 0, 4) }}</p>
                    <p class="text-xs text-gray-500">LSC AI</p>
                </div>
            </div>

            <!-- Info Banner -->
            <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Click the <strong>Toggle</strong> button to change a transaction from Credit to Debit or vice versa. Your corrections will train the AI for future analyses.
                    </p>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
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
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700" id="txn-row-{{ $txn->id }}" data-transaction-id="{{ $txn->id }}">
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

                                            // Match patterns like: ACCT ****1234, ACCT XXXX1234, Account ending in 1234, etc.
                                            if (preg_match('/(?:ACCT|ACCOUNT|A\/C)[\s#:]*([X*]+)?(\d{4,})/i', $description, $matches)) {
                                                $accountNumber = $matches[2];
                                            } elseif (preg_match('/(?:ending in|ending|ends in)[\s:]*(\d{4,})/i', $description, $matches)) {
                                                $accountNumber = $matches[1];
                                            } elseif (preg_match('/[X*]{4,}(\d{4,})/', $description, $matches)) {
                                                $accountNumber = $matches[1];
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
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium category-badge {{ $isTransfer ? 'ring-2 ring-offset-1 ring-blue-400 dark:ring-blue-500' : '' }}" data-category="{{ $txn->category }}">
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
                                                </span>
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

            // Filter categories based on transaction type
            const filteredCategories = Object.entries(categoriesData).filter(([key, cat]) =>
                cat.type === 'both' || cat.type === type
            );

            // Build category grid
            const grid = document.getElementById('category-grid');
            grid.innerHTML = filteredCategories.map(([key, cat]) => `
                <button onclick="selectCategory('${key}')"
                        class="flex items-center gap-2 px-3 py-2 text-left text-sm border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition category-option"
                        data-category="${key}">
                    <span class="w-3 h-3 rounded-full category-color-${cat.color}"></span>
                    <span class="text-gray-900 dark:text-white">${cat.label}</span>
                </button>
            `).join('');

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
    </script>
</x-app-layout>
