<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Analysis: {{ $session->session_id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $session->filename }}</h3>
                    <p class="text-gray-600 dark:text-gray-400">Analyzed on {{ $session->created_at->format('F d, Y \a\t H:i') }}</p>
                </div>
                <a href="{{ route('smartmca.history') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to History
                </a>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <p class="text-sm text-green-600 dark:text-green-400 font-medium">Total Credits</p>
                    <p class="credit-amount text-2xl font-bold text-green-700 dark:text-green-300" data-value="{{ $session->total_credits }}">${{ number_format($session->total_credits, 2) }}</p>
                    <p class="credit-count text-xs text-green-500" data-value="{{ $transactions->where('type', 'credit')->count() }}">{{ $transactions->where('type', 'credit')->count() }} transactions</p>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                    <p class="text-sm text-red-600 dark:text-red-400 font-medium">Total Debits</p>
                    <p class="debit-amount text-2xl font-bold text-red-700 dark:text-red-300" data-value="{{ $session->total_debits }}">${{ number_format($session->total_debits, 2) }}</p>
                    <p class="debit-count text-xs text-red-500" data-value="{{ $transactions->where('type', 'debit')->count() }}">{{ $transactions->where('type', 'debit')->count() }} transactions</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Net Flow</p>
                    <p class="net-flow-amount text-2xl font-bold {{ $session->net_flow >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}" data-value="{{ $session->net_flow }}">
                        {{ $session->net_flow >= 0 ? '+' : '' }}${{ number_format($session->net_flow, 2) }}
                    </p>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                    <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">Transactions</p>
                    <p class="text-2xl font-bold text-purple-700 dark:text-purple-300">{{ $session->total_transactions }}</p>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                    <p class="text-sm text-yellow-600 dark:text-yellow-400 font-medium">Confidence</p>
                    <p class="text-lg font-bold text-yellow-700 dark:text-yellow-300">
                        <span class="text-green-600">{{ $session->high_confidence_count }}</span> /
                        <span class="text-yellow-600">{{ $session->medium_confidence_count }}</span> /
                        <span class="text-red-600">{{ $session->low_confidence_count }}</span>
                    </p>
                    <p class="text-xs text-yellow-500">H / M / L</p>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Transactions</h4>

                    @if($transactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Confidence</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($transactions as $txn)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transaction-row" data-id="{{ $txn->id }}">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $txn->transaction_date->format('Y-m-d') }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 max-w-md" title="{{ $txn->description }}">
                                                <div class="truncate">{{ Str::limit($txn->description, 50) }}</div>
                                                @if($txn->was_corrected)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 mt-1">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        Corrected
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                @php
                                                    $confidencePercent = round($txn->confidence * 100);
                                                @endphp
                                                <div class="flex flex-col items-center">
                                                    <div class="w-16 bg-gray-200 dark:bg-gray-600 rounded-full h-2 mb-1">
                                                        <div class="h-2 rounded-full {{ $txn->confidence_label === 'high' ? 'bg-green-500' : ($txn->confidence_label === 'medium' ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $confidencePercent }}%"></div>
                                                    </div>
                                                    <span class="text-xs {{ $txn->confidence_label === 'high' ? 'text-green-600 dark:text-green-400' : ($txn->confidence_label === 'medium' ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                                                        {{ $confidencePercent }}%
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                <span class="type-badge px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $txn->type === 'credit' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                                    {{ ucfirst($txn->type) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium amount-cell {{ $txn->type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                <span class="amount-prefix">{{ $txn->type === 'credit' ? '+' : '-' }}</span>${{ number_format($txn->amount, 2) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                <button type="button"
                                                        class="toggle-type-btn inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                                        data-id="{{ $txn->id }}"
                                                        data-description="{{ $txn->description }}"
                                                        data-current-type="{{ $txn->type }}"
                                                        data-amount="{{ $txn->amount }}"
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
                            <p>No transactions found for this session.</p>
                        </div>
                    @endif
                </div>
            </div>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.toggle-type-btn');
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');

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

            function formatCurrency(value) {
                return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function updateSummaryCards(amount, fromType, toType) {
                const creditAmountEl = document.querySelector('.credit-amount');
                const debitAmountEl = document.querySelector('.debit-amount');
                const creditCountEl = document.querySelector('.credit-count');
                const debitCountEl = document.querySelector('.debit-count');
                const netFlowEl = document.querySelector('.net-flow-amount');

                let creditTotal = parseFloat(creditAmountEl.dataset.value) || 0;
                let debitTotal = parseFloat(debitAmountEl.dataset.value) || 0;
                let creditCount = parseInt(creditCountEl.dataset.value) || 0;
                let debitCount = parseInt(debitCountEl.dataset.value) || 0;

                const txnAmount = parseFloat(amount);

                if (fromType === 'credit' && toType === 'debit') {
                    creditTotal -= txnAmount;
                    debitTotal += txnAmount;
                    creditCount--;
                    debitCount++;
                } else if (fromType === 'debit' && toType === 'credit') {
                    debitTotal -= txnAmount;
                    creditTotal += txnAmount;
                    debitCount--;
                    creditCount++;
                }

                // Update data attributes
                creditAmountEl.dataset.value = creditTotal;
                debitAmountEl.dataset.value = debitTotal;
                creditCountEl.dataset.value = creditCount;
                debitCountEl.dataset.value = debitCount;

                // Update displayed values
                creditAmountEl.textContent = formatCurrency(creditTotal);
                debitAmountEl.textContent = formatCurrency(debitTotal);
                creditCountEl.textContent = creditCount + ' transactions';
                debitCountEl.textContent = debitCount + ' transactions';

                // Update net flow
                const netFlow = creditTotal - debitTotal;
                netFlowEl.dataset.value = netFlow;
                netFlowEl.textContent = (netFlow >= 0 ? '+' : '') + formatCurrency(netFlow);
                netFlowEl.className = 'net-flow-amount text-2xl font-bold ' +
                    (netFlow >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300');
            }

            toggleButtons.forEach(button => {
                button.addEventListener('click', async function() {
                    const row = this.closest('tr');
                    const txnId = this.dataset.id;
                    const description = this.dataset.description;
                    const currentType = this.dataset.currentType;
                    const amount = this.dataset.amount;
                    const newType = currentType === 'credit' ? 'debit' : 'credit';

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
                                amount: parseFloat(amount),
                                transaction_id: parseInt(txnId),
                                session_id: '{{ $session->session_id }}'
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.dataset.currentType = newType;

                            const typeBadge = row.querySelector('.type-badge');
                            typeBadge.textContent = newType.charAt(0).toUpperCase() + newType.slice(1);
                            typeBadge.className = 'type-badge px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ' +
                                (newType === 'credit'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200');

                            const amountCell = row.querySelector('.amount-cell');
                            const amountPrefix = row.querySelector('.amount-prefix');
                            amountCell.className = 'px-4 py-3 whitespace-nowrap text-sm text-right font-medium amount-cell ' +
                                (newType === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400');
                            amountPrefix.textContent = newType === 'credit' ? '+' : '-';

                            // Update summary cards
                            updateSummaryCards(amount, currentType, newType);

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

                    this.disabled = false;
                    this.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>Toggle';
                });
            });
        });
    </script>
</x-app-layout>
