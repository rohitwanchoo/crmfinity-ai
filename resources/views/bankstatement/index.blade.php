<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Bank Statement Analyzer
        </h2>
    </x-slot>

    <div class="py-4 sm:py-12">
        <div class="w-full px-2 sm:px-6 lg:px-8">
            @if(session('error'))
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
            @endif

            <!-- Stats Cards -->
            @if(isset($stats) && $stats['total_sessions'] > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Statements Analyzed</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total_sessions'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Transactions</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_transactions']) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-teal-100 dark:bg-teal-900 rounded-full">
                            <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Banks Analyzed</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ isset($bankStats) ? $bankStats->count() : 0 }}</p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('bankstatement.lenders') }}" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 block">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">MCA Lenders</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_lenders']) }}</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
            </div>

            <!-- Banks Breakdown -->
            @if(isset($bankStats) && $bankStats->count() > 0)
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Banks Analyzed</h3>
                <div class="flex flex-wrap gap-3">
                    @foreach($bankStats as $bank)
                    <div class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-full">
                        <svg class="w-4 h-4 text-teal-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $bank->bank_name }}</span>
                        <span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-teal-100 dark:bg-teal-800 text-teal-700 dark:text-teal-300 rounded-full">{{ $bank->count }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-2 bg-green-600 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Upload Bank Statements</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Powered by LSC AI</p>
                        </div>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Fast and accurate bank statement analysis using advanced AI. Works with any bank format.</p>

                    <form action="{{ route('bankstatement.analyze') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Select PDF Statement(s)
                            </label>
                            <input type="file" name="statements[]" multiple accept=".pdf" required
                                class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 dark:file:bg-gray-700 dark:file:text-gray-300">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Upload multiple PDFs at once | All major banks supported | Max 20MB per file
                            </p>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                AI Model
                            </label>
                            <select name="model" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                <option value="claude-sonnet-4-5" selected>LSC Pro (Balanced)</option>
                                <option value="claude-haiku-4-5">LSC Basic (Fastest & Most Cost-Effective)</option>
                                <option value="claude-opus-4-6">LSC Max (Most Accurate)</option>
                            </select>
                        </div>

                        <div class="flex flex-wrap items-center gap-4">
                            <button type="submit" class="inline-flex items-center px-6 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Analyze Statement
                            </button>

                            <a href="{{ route('bankstatement.history') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                                View History
                            </a>

                            <a href="{{ route('bankstatement.lenders') }}" class="inline-flex items-center px-4 py-2 bg-purple-100 dark:bg-purple-900 border border-transparent rounded-md font-semibold text-xs text-purple-700 dark:text-purple-300 uppercase tracking-widest hover:bg-purple-200 dark:hover:bg-purple-800 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                View Lenders
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Sessions -->
            @if(isset($recentSessions) && $recentSessions->count() > 0)
            <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Analyses</h3>
                        <a href="{{ route('bankstatement.history') }}" class="text-sm text-green-600 dark:text-green-400 hover:underline">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">File</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bank Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Transactions</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Credits</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Debits</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">API Cost</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Processing Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($recentSessions as $session)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ Str::limit($session->filename, 30) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $session->bank_name ?: '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $session->total_transactions }}</td>
                                    <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400">${{ number_format($session->total_credits, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400">${{ number_format($session->total_debits, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${{ number_format($session->api_cost ?? 0, 4) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        @if($session->processing_time !== null)
                                            {{ $session->processing_time }}s
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $session->created_at->diffForHumans() }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('bankstatement.session', $session->session_id) }}" class="text-green-600 dark:text-green-400 hover:underline">View</a>
                                            <a href="{{ route('bankstatement.pdf', $session->session_id) }}" target="_blank" class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 hover:underline">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                                </svg>
                                                PDF
                                            </a>
                                            @if($session->source_type === 'scanned')
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
                </div>
            </div>
            @endif

            <!-- Features -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="text-green-600 dark:text-green-400 mb-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Fast Processing</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Analyze statements in seconds with LSC AI's powerful understanding</p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="text-teal-600 dark:text-teal-400 mb-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">High Accuracy</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Precise transaction extraction with automatic credit/debit classification</p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="text-emerald-600 dark:text-emerald-400 mb-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Universal Support</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Works with Chase, Wells Fargo, Bank of America, and any other bank</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
