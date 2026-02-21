<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Analysis History
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between">
                <a href="{{ route('bankstatement.index') }}" class="inline-flex items-center text-sm text-green-600 dark:text-green-400 hover:underline">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Bank Statement Analyzer
                </a>

                <!-- View Mode Toggle -->
                @if($sessions->count() > 0)
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">View:</span>
                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 p-1 bg-gray-100 dark:bg-gray-800">
                        <button id="view-individual" onclick="setViewMode('individual')"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors view-mode-btn active">
                            Individual
                        </button>
                        <button id="view-grouped" onclick="setViewMode('grouped')"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors view-mode-btn">
                            Grouped by Upload
                        </button>
                    </div>
                </div>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Analysis History</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Select multiple sessions to view combined analysis
                        </p>
                    </div>

                    @if($sessions->count() > 0)
                    <!-- Individual View -->
                    <div id="individual-view" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left">
                                        <input type="checkbox" id="select-all"
                                            class="rounded border-gray-300 text-green-600 focus:ring-green-500 dark:border-gray-600 dark:bg-gray-700"
                                            onchange="toggleSelectAll()">
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">File</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bank Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Model</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Transactions</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Credits</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Debits</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Net</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cost</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Processing Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($sessions as $session)
                                <tr class="session-row hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                    data-session-id="{{ $session->session_id }}"
                                    data-filename="{{ $session->filename }}"
                                    data-credits="{{ $session->total_credits }}"
                                    data-debits="{{ $session->total_debits }}"
                                    data-transactions="{{ $session->total_transactions }}"
                                    data-created="{{ $session->created_at->timestamp }}">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" class="session-checkbox rounded border-gray-300 text-green-600 focus:ring-green-500 dark:border-gray-600 dark:bg-gray-700"
                                            value="{{ $session->session_id }}"
                                            onchange="updateSelection()">
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100" title="{{ $session->filename }}">
                                        {{ Str::limit($session->filename, 25) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $session->bank_name ?: '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            LSC AI
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $session->total_transactions }}</td>
                                    <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400">${{ number_format($session->total_credits, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400">${{ number_format($session->total_debits, 2) }}</td>
                                    <td class="px-4 py-3 text-sm {{ $session->net_flow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        ${{ number_format($session->net_flow, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${{ number_format($session->api_cost ?? 0, 4) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        @if($session->processing_time !== null)
                                            {{ $session->processing_time }}s
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $session->created_at->format('M d, Y H:i') }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('bankstatement.view-analysis') }}?sessions[]={{ $session->session_id }}" class="text-green-600 dark:text-green-400 hover:underline">View</a>
                                            <a href="{{ route('bankstatement.download', $session->session_id) }}" class="text-gray-600 dark:text-gray-400 hover:underline">CSV</a>
                                            <a href="{{ route('bankstatement.pdf', $session->session_id) }}" target="_blank" class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 hover:underline">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                                </svg>
                                                PDF
                                            </a>
                                            @if($session->total_tokens > 0)
                                            <span title="Scanned PDF — processed via OCR" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                OCR
                                            </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Grouped View -->
                    <div id="grouped-view" class="hidden">
                        <!-- Will be populated by JavaScript -->
                    </div>

                    <div class="mt-6">
                        {{ $sessions->links() }}
                    </div>
                    @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No analyses yet</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by uploading a bank statement.</p>
                        <div class="mt-6">
                            <a href="{{ route('bankstatement.index') }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                Upload Statement
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Selection Action Bar -->
    <div id="selection-bar" class="fixed bottom-0 left-0 right-0 bg-green-600 text-white transform translate-y-full transition-transform duration-300 z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="font-medium">
                        <span id="selected-count">0</span> sessions selected
                    </span>
                    <span class="text-green-200 text-sm">
                        <span id="selected-transactions">0</span> transactions |
                        <span id="selected-credits">$0.00</span> credits |
                        <span id="selected-debits">$0.00</span> debits
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="clearSelection()" class="px-4 py-2 bg-green-700 hover:bg-green-800 rounded-lg transition-colors text-sm font-medium">
                        Clear Selection
                    </button>
                    <button onclick="viewCombinedAnalysis()" class="px-6 py-2 bg-white text-green-600 hover:bg-green-50 rounded-lg transition-colors font-semibold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        View Combined Analysis
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .view-mode-btn {
            color: #6b7280;
        }
        .view-mode-btn.active {
            background-color: white;
            color: #059669;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .dark .view-mode-btn.active {
            background-color: #374151;
            color: #34d399;
        }
        .session-row.selected {
            background-color: #ecfdf5;
        }
        .dark .session-row.selected {
            background-color: rgba(16, 185, 129, 0.1);
        }
        .group-card {
            transition: all 0.2s ease;
        }
        .group-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>

    @php
        $sessionsJson = $sessions->map(function($s) {
            return [
                'session_id' => $s->session_id,
                'batch_id' => $s->batch_id,
                'filename' => $s->filename,
                'total_transactions' => $s->total_transactions,
                'total_credits' => $s->total_credits,
                'total_debits' => $s->total_debits,
                'net_flow' => $s->net_flow,
                'api_cost' => $s->api_cost,
                'created_at' => $s->created_at->format('M d, Y H:i'),
                'created_timestamp' => $s->created_at->timestamp
            ];
        })->values();
    @endphp

    <script>
        // Session data for grouped view
        const sessionsData = @json($sessionsJson);

        let currentViewMode = 'individual';

        // Toggle select all checkboxes
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.session-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                updateRowHighlight(cb);
            });
            updateSelection();
        }

        // Update row highlight based on checkbox state
        function updateRowHighlight(checkbox) {
            const row = checkbox.closest('.session-row');
            if (row) {
                if (checkbox.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            }
        }

        // Update selection bar with totals
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.session-checkbox:checked');
            const count = checkboxes.length;
            const selectionBar = document.getElementById('selection-bar');

            // Update row highlights
            document.querySelectorAll('.session-checkbox').forEach(cb => updateRowHighlight(cb));

            // Calculate totals
            let totalTransactions = 0;
            let totalCredits = 0;
            let totalDebits = 0;

            checkboxes.forEach(cb => {
                const row = cb.closest('.session-row');
                totalTransactions += parseInt(row.dataset.transactions) || 0;
                totalCredits += parseFloat(row.dataset.credits) || 0;
                totalDebits += parseFloat(row.dataset.debits) || 0;
            });

            // Update UI
            document.getElementById('selected-count').textContent = count;
            document.getElementById('selected-transactions').textContent = totalTransactions.toLocaleString();
            document.getElementById('selected-credits').textContent = '$' + totalCredits.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('selected-debits').textContent = '$' + totalDebits.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Show/hide selection bar
            if (count > 0) {
                selectionBar.classList.remove('translate-y-full');
            } else {
                selectionBar.classList.add('translate-y-full');
            }

            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.session-checkbox');
            const selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.checked = count > 0 && count === allCheckboxes.length;
                selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
            }
        }

        // Clear all selections
        function clearSelection() {
            document.querySelectorAll('.session-checkbox').forEach(cb => {
                cb.checked = false;
                updateRowHighlight(cb);
            });
            document.getElementById('select-all').checked = false;
            updateSelection();
        }

        // Navigate to combined analysis view
        function viewCombinedAnalysis() {
            const checkboxes = document.querySelectorAll('.session-checkbox:checked');
            if (checkboxes.length === 0) return;

            const sessionIds = Array.from(checkboxes).map(cb => cb.value);
            const params = sessionIds.map(id => `sessions[]=${id}`).join('&');
            window.location.href = `{{ route('bankstatement.view-analysis') }}?${params}`;
        }

        // View mode toggle
        function setViewMode(mode) {
            currentViewMode = mode;

            // Update button styles
            document.querySelectorAll('.view-mode-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('view-' + mode).classList.add('active');

            // Toggle views
            if (mode === 'individual') {
                document.getElementById('individual-view').classList.remove('hidden');
                document.getElementById('grouped-view').classList.add('hidden');
            } else {
                document.getElementById('individual-view').classList.add('hidden');
                document.getElementById('grouped-view').classList.remove('hidden');
                renderGroupedView();
            }
        }

        // Group sessions by batch_id (statements uploaded together)
        function groupSessionsByUpload() {
            const groups = [];
            const batchMap = new Map();

            // Sort by timestamp descending
            const sorted = [...sessionsData].sort((a, b) => b.created_timestamp - a.created_timestamp);

            sorted.forEach(session => {
                // If session has a batch_id, group by it. Otherwise, treat as individual.
                const batchKey = session.batch_id || session.session_id;

                if (!batchMap.has(batchKey)) {
                    // New group
                    const group = {
                        batch_id: batchKey,
                        timestamp: session.created_timestamp,
                        date: session.created_at,
                        sessions: [session],
                        totalTransactions: session.total_transactions,
                        totalCredits: parseFloat(session.total_credits),
                        totalDebits: parseFloat(session.total_debits)
                    };
                    batchMap.set(batchKey, group);
                    groups.push(group);
                } else {
                    // Same batch - add to existing group
                    const group = batchMap.get(batchKey);
                    group.sessions.push(session);
                    group.totalTransactions += session.total_transactions;
                    group.totalCredits += parseFloat(session.total_credits);
                    group.totalDebits += parseFloat(session.total_debits);
                }
            });

            return groups;
        }

        // Render grouped view
        function renderGroupedView() {
            const container = document.getElementById('grouped-view');
            const groups = groupSessionsByUpload();

            if (groups.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-8">No sessions to display</p>';
                return;
            }

            let html = '<div class="space-y-4">';

            groups.forEach((group, index) => {
                const netFlow = group.totalCredits - group.totalDebits;
                const netClass = netFlow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
                const sessionIds = group.sessions.map(s => s.session_id);
                const viewUrl = `{{ route('bankstatement.view-analysis') }}?${sessionIds.map(id => `sessions[]=${id}`).join('&')}`;

                html += `
                    <div class="group-card bg-gray-50 dark:bg-gray-700/50 rounded-xl p-5 border border-gray-200 dark:border-gray-600">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        ${group.sessions.length} ${group.sessions.length === 1 ? 'Statement' : 'Statements'}
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">${group.date}</span>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    ${group.sessions.map(s => s.filename).join(', ')}
                                </p>
                            </div>
                            <a href="${viewUrl}" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm font-medium">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                View Combined
                            </a>
                        </div>
                        <div class="grid grid-cols-4 gap-4 text-sm">
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center">
                                <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Transactions</p>
                                <p class="font-semibold text-gray-900 dark:text-gray-100">${group.totalTransactions.toLocaleString()}</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center">
                                <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Credits</p>
                                <p class="font-semibold text-green-600 dark:text-green-400">$${group.totalCredits.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center">
                                <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Debits</p>
                                <p class="font-semibold text-red-600 dark:text-red-400">$${group.totalDebits.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center">
                                <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Net Flow</p>
                                <p class="font-semibold ${netClass}">$${netFlow.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSelection();
        });
    </script>
</x-app-layout>
