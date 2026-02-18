<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    MCA Lenders & Debt Collectors
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View all MCA lenders and debt collectors tracked in the system</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('bankstatement.lenders.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 active:bg-purple-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    New Lender
                </a>
                <a href="{{ route('bankstatement.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="-mx-6 pb-6 px-4 sm:px-6">
        <div class="w-full">

            @if(session('success'))
            <div class="mb-6 bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded relative">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
            @endif

            @if(session('error'))
            <div class="mb-6 bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded relative">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
            @endif

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div id="lendersCard" class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm cursor-pointer transition-all duration-200 hover:shadow-md ring-2 ring-purple-500 ring-offset-2 dark:ring-offset-gray-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Lenders</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_lenders']) }}</p>
                        </div>
                        <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-full">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div id="collectorsCard" class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm cursor-pointer transition-all duration-200 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Debt Collectors</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_debt_collectors']) }}</p>
                        </div>
                        <div class="p-2 bg-orange-100 dark:bg-orange-900 rounded-full">
                            <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">With Guidelines</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_with_guidelines']) }}</p>
                        </div>
                        <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-full">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Patterns</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_patterns']) }}</p>
                        </div>
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900 rounded-full">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Detections</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_usage']) }}</p>
                        </div>
                        <div class="p-2 bg-green-100 dark:bg-green-900 rounded-full">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════ LENDERS TABLE ═══════════════════════ --}}
            <div id="lendersSection" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-full">
                                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </span>
                            MCA Lenders
                        </h3>

                        <div class="flex flex-wrap gap-2 flex-1 sm:justify-end">
                            <!-- Status filter -->
                            <select id="statusFilter" class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-purple-500 focus:border-purple-500">
                                <option value="all">All Status</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Inactive Only</option>
                            </select>
                            <!-- Sort -->
                            <select id="sortFilter" class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-purple-500 focus:border-purple-500">
                                <option value="name-asc">Name A–Z</option>
                                <option value="name-desc">Name Z–A</option>
                                <option value="usage-desc">Most Detected</option>
                                <option value="usage-asc">Least Detected</option>
                                <option value="credit-asc">Credit Score ↑</option>
                                <option value="credit-desc">Credit Score ↓</option>
                            </select>
                            <!-- Search -->
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" id="lenderSearch" placeholder="Search lenders…"
                                    class="pl-9 pr-8 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-purple-500 focus:border-purple-500 w-52">
                                <button id="clearSearch" class="hidden absolute inset-y-0 right-0 pr-2 flex items-center">
                                    <svg class="h-4 w-4 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                        Showing <span id="resultCount">{{ $lenders->count() }}</span> of {{ $lenders->count() }} lenders
                        <span class="ml-2 text-xs text-gray-400">· Click any row to expand guidelines</span>
                    </p>

                    <div id="noResults" class="hidden text-center py-10">
                        <p class="text-gray-500 dark:text-gray-400">No lenders match your search.</p>
                    </div>

                    <div class="overflow-x-auto" id="lendersTable">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-8">#</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lender</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Credit</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Loan Range</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Neg Days</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">NSF</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pos</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bus. Types</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Patterns</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="lendersTbody" class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($lenders as $index => $lender)
                                @php
                                    $g = $guidelines->get($lender->lender_id);
                                    $status = $g ? $g->status : 'ACTIVE';
                                @endphp
                                {{-- Main row --}}
                                <tr class="lender-row hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                                    data-name="{{ strtolower($lender->lender_name) }}"
                                    data-id="{{ strtolower($lender->lender_id) }}"
                                    data-patterns="{{ $lender->pattern_count }}"
                                    data-usage="{{ $lender->total_usage }}"
                                    data-last-used="{{ $lender->last_used ?? '' }}"
                                    data-status="{{ strtolower($status) }}"
                                    data-credit="{{ $g?->min_credit_score ?? 9999 }}"
                                    data-expand-target="expand-{{ $lender->lender_id }}"
                                    onclick="toggleExpand('expand-{{ $lender->lender_id }}', this)">

                                    <td class="px-3 py-3 whitespace-nowrap text-gray-400 dark:text-gray-500 row-number text-xs">{{ $index + 1 }}</td>

                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-shrink-0 h-8 w-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center text-xs font-semibold text-purple-600 dark:text-purple-400">
                                                {{ strtoupper(substr($lender->lender_name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $lender->lender_name }}</div>
                                                <div class="text-xs text-gray-400 dark:text-gray-500">{{ $lender->lender_id }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-center">
                                        @if($g)
                                            @if($g->status === 'ACTIVE')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                                            @endif
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                        @endif
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-center text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $g?->min_credit_score ?? '—' }}
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                        @if($g?->min_time_in_business)
                                            {{ $g->min_time_in_business }}mo
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        @if($g && ($g->min_loan_amount || $g->max_loan_amount))
                                            <span class="text-xs">
                                                @if($g->min_loan_amount) ${{ number_format($g->min_loan_amount / 1000, 0) }}k @endif
                                                –
                                                @if($g->max_loan_amount) ${{ number_format($g->max_loan_amount / 1000, 0) }}k @endif
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                        {{ $g?->max_negative_days ?? '—' }}
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                        {{ $g?->max_nsfs ?? '—' }}
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                        {{ $g?->max_positions ?? '—' }}
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap">
                                        @if($g)
                                        <div class="flex gap-1 flex-wrap">
                                            @foreach([
                                                ['SP', $g->sole_proprietors, 'Sole Prop'],
                                                ['HB', $g->home_based_business, 'Home-Based'],
                                                ['CO', $g->consolidation_deals, 'Consolidation'],
                                                ['NP', $g->non_profits, 'Non-Profit'],
                                            ] as [$abbr, $val, $label])
                                            @if($val)
                                            <span title="{{ $label }}: {{ $val }}"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                                    {{ $val === 'YES' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : ($val === 'NO' ? 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300') }}">
                                                {{ $abbr }}
                                            </span>
                                            @endif
                                            @endforeach
                                        </div>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                        @endif
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">
                                            {{ $lender->pattern_count }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-right" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('bankstatement.lenders.edit', $lender->lender_id) }}"
                                               title="Edit guidelines"
                                               class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 hover:bg-purple-100 dark:hover:bg-purple-900/60 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <button type="button"
                                               title="Expand details"
                                               onclick="toggleExpand('expand-{{ $lender->lender_id }}', this.closest('tr').previousElementSibling)"
                                               class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-gray-50 dark:bg-gray-700 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                                <svg id="chevron-{{ $lender->lender_id }}" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Expanded guideline row --}}
                                <tr id="expand-{{ $lender->lender_id }}" class="hidden">
                                    <td colspan="12" class="p-0">
                                        <div class="border-t border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/30 px-6 py-5">
                                        @if($g)
                                        @php
                                            $badge = fn($val) =>
                                                $val === null ? '<span class="text-gray-400 dark:text-gray-500 text-xs">—</span>' :
                                                ($val === 'YES'
                                                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300">YES</span>'
                                                    : ($val === 'NO'
                                                        ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300">NO</span>'
                                                        : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300">'.$val.'</span>'));
                                        @endphp

                                        {{-- Header --}}
                                        <div class="flex items-center justify-between mb-4">
                                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Guideline Details</span>
                                            <div class="flex items-center gap-2">
                                                @if($lender->pattern_count > 0)
                                                <a href="{{ route('bankstatement.lender-detail', $lender->lender_id) }}"
                                                    onclick="event.stopPropagation()"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors shadow-sm">
                                                    Patterns ({{ $lender->pattern_count }})
                                                </a>
                                                @endif
                                                <a href="{{ route('bankstatement.lenders.edit', $lender->lender_id) }}"
                                                    onclick="event.stopPropagation()"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-purple-600 text-white hover:bg-purple-700 transition-colors shadow-sm">
                                                    Edit Guidelines
                                                </a>
                                            </div>
                                        </div>

                                        {{-- 5-section grid --}}
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">

                                            {{-- ── FINANCIAL ── --}}
                                            <div class="rounded-xl bg-blue-50/60 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/40 overflow-hidden">
                                                <div class="px-4 py-2.5 border-b border-blue-100 dark:border-blue-900/40">
                                                    <p class="text-[10px] font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Financial</p>
                                                </div>
                                                <div class="px-4 py-3 space-y-2.5">
                                                    @foreach([
                                                        ['Credit Score',  $g->min_credit_score ?? null,            null],
                                                        ['Min Time',      $g->min_time_in_business ?? null,        'mo'],
                                                        ['Min Deposits',  $g->min_monthly_deposits ?? null,        '$', true],
                                                        ['Loan Min',      $g->min_loan_amount ?? null,             '$', true],
                                                        ['Loan Max',      $g->max_loan_amount ?? null,             '$', true],
                                                        ['Min ADB',       $g->min_avg_daily_balance ?? null,       '$', true],
                                                        ['Neg Days',      $g->max_negative_days ?? null,           null],
                                                        ['NSFs/mo',       $g->max_nsfs ?? null,                   null],
                                                        ['Max Positions', $g->max_positions ?? null,              null],
                                                    ] as $row)
                                                    @php
                                                        [$rowLabel, $rowVal, $suffix, $isMoney] = array_pad($row, 4, false);
                                                        $display = $rowVal === null ? '—'
                                                            : ($isMoney ? '$'.number_format($rowVal).($suffix === 'mo' ? '/mo' : '') : $rowVal.($suffix && $suffix !== '$' ? ' '.$suffix : ''));
                                                    @endphp
                                                    <div class="flex justify-between items-center gap-3">
                                                        <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ $rowLabel }}</span>
                                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200 text-right">{{ $display }}</span>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            {{-- ── FUNDING TERMS ── --}}
                                            <div class="rounded-xl bg-purple-50/60 dark:bg-purple-900/10 border border-purple-100 dark:border-purple-900/40 overflow-hidden">
                                                <div class="px-4 py-2.5 border-b border-purple-100 dark:border-purple-900/40">
                                                    <p class="text-[10px] font-bold uppercase tracking-widest text-purple-600 dark:text-purple-400">Funding Terms</p>
                                                </div>
                                                <div class="px-4 py-3 space-y-2.5">
                                                    <div class="flex justify-between items-center gap-3"><span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">Product</span><span class="text-xs font-semibold text-gray-700 dark:text-gray-200 text-right">{{ $g->product_type ?? '—' }}</span></div>
                                                    <div class="flex justify-between items-center gap-3"><span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">Factor Rate</span><span class="text-xs font-semibold text-gray-700 dark:text-gray-200 text-right">{{ $g->factor_rate ?? '—' }}</span></div>
                                                    <div class="flex justify-between items-center gap-3"><span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">Max Term</span><span class="text-xs font-semibold text-gray-700 dark:text-gray-200 text-right">{{ $g->max_term ?? '—' }}</span></div>
                                                    <div class="flex justify-between items-center gap-3"><span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">Speed</span><span class="text-xs font-semibold text-gray-700 dark:text-gray-200 text-right">{{ $g->funding_speed ?? '—' }}</span></div>
                                                    <div class="flex justify-between items-center gap-3"><span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">Payment</span><span class="text-xs font-semibold text-gray-700 dark:text-gray-200 text-right">{{ $g->payment_frequency ?? '—' }}</span></div>
                                                    <div class="flex justify-between items-center gap-3"><span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">Bonus</span><span>{!! $badge($g->bonus_available !== null ? ($g->bonus_available ? 'YES' : 'NO') : null) !!}</span></div>
                                                    <div class="flex justify-between items-center gap-3"><span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">Bonus Info</span><span class="text-xs font-semibold text-gray-700 dark:text-gray-200 text-right truncate max-w-[100px]" title="{{ $g->bonus_details }}">{{ $g->bonus_details ?? '—' }}</span></div>
                                                    <div class="flex justify-between items-center gap-3"><span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">White Label</span><span>{!! $badge($g->white_label !== null ? ($g->white_label ? 'YES' : 'NO') : null) !!}</span></div>
                                                </div>
                                            </div>

                                            {{-- ── ELIGIBILITY ── --}}
                                            <div class="rounded-xl bg-green-50/60 dark:bg-green-900/10 border border-green-100 dark:border-green-900/40 overflow-hidden">
                                                <div class="px-4 py-2.5 border-b border-green-100 dark:border-green-900/40">
                                                    <p class="text-[10px] font-bold uppercase tracking-widest text-green-600 dark:text-green-400">Eligibility</p>
                                                </div>
                                                <div class="px-4 py-3 space-y-2.5">
                                                    @foreach([
                                                        ['Sole Proprietors', $g->sole_proprietors],
                                                        ['Home-Based',       $g->home_based_business],
                                                        ['Consolidation',    $g->consolidation_deals],
                                                        ['Non-Profit',       $g->non_profits],
                                                    ] as [$label, $val])
                                                    <div class="flex justify-between items-center gap-3">
                                                        <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ $label }}</span>
                                                        <span>{!! $badge($val) !!}</span>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            {{-- ── SPECIAL CIRCUMSTANCES ── --}}
                                            <div class="rounded-xl bg-orange-50/60 dark:bg-orange-900/10 border border-orange-100 dark:border-orange-900/40 overflow-hidden">
                                                <div class="px-4 py-2.5 border-b border-orange-100 dark:border-orange-900/40">
                                                    <p class="text-[10px] font-bold uppercase tracking-widest text-orange-600 dark:text-orange-400">Special</p>
                                                </div>
                                                <div class="px-4 py-3 space-y-2.5">
                                                    @foreach([
                                                        ['Bankruptcy',    $g->bankruptcy],
                                                        ['Tax Lien',      $g->tax_lien],
                                                        ['Prior Default', $g->prior_default],
                                                        ['Criminal',      $g->criminal_history],
                                                    ] as [$label, $val])
                                                    <div class="flex justify-between items-center gap-3">
                                                        <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ $label }}</span>
                                                        <span>{!! $badge($val) !!}</span>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            {{-- ── GEOGRAPHIC ── --}}
                                            <div class="rounded-xl bg-red-50/60 dark:bg-red-900/10 border border-red-100 dark:border-red-900/40 overflow-hidden">
                                                <div class="px-4 py-2.5 border-b border-red-100 dark:border-red-900/40 flex items-center gap-1.5">
                                                    <svg class="w-3 h-3 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                    <p class="text-[10px] font-bold uppercase tracking-widest text-red-600 dark:text-red-400">Geographic</p>
                                                </div>
                                                <div class="px-4 py-3 space-y-3 text-xs">
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Restricted States</p>
                                                        @if(!empty($g->restricted_states))
                                                            <div class="flex flex-wrap gap-1">
                                                                @foreach($g->restricted_states as $state)
                                                                <span class="px-2 py-0.5 rounded-md bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 text-[11px] font-bold">{{ $state }}</span>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 text-[11px] font-semibold">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                                Nationwide
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Excluded Industries</p>
                                                        @if(!empty($g->excluded_industries))
                                                            <div class="flex flex-wrap gap-1 max-h-20 overflow-y-auto pr-1">
                                                                @foreach($g->excluded_industries as $industry)
                                                                <span class="px-2 py-0.5 rounded-md bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 text-[11px] font-medium">{{ str_replace('_', ' ', ucwords(str_replace('_', ' ', $industry))) }}</span>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 text-[11px] font-semibold">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                                All Industries
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                        </div>{{-- /grid --}}

                                        {{-- ── NOTES (full-width strip below grid) ── --}}
                                        @if($g->notes)
                                        <div class="mt-4 rounded-xl bg-amber-50/70 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/40 overflow-hidden">
                                            <div class="px-4 py-2.5 border-b border-amber-200 dark:border-amber-800/40 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <p class="text-[10px] font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400">Notes</p>
                                            </div>
                                            <div class="px-5 py-3">
                                                <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ $g->notes }}</p>
                                            </div>
                                        </div>
                                        @endif

                                        @else
                                        {{-- No guidelines yet --}}
                                        <div class="flex items-center gap-3 py-1 flex-wrap">
                                            <p class="text-sm text-gray-400 dark:text-gray-500 italic">No guidelines recorded for this lender.</p>
                                            <a href="{{ route('bankstatement.lenders.edit', $lender->lender_id) }}"
                                                onclick="event.stopPropagation()"
                                                class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-purple-600 text-white hover:bg-purple-700 transition-colors">
                                                Add Guidelines
                                            </a>
                                            @if($lender->pattern_count > 0)
                                            <a href="{{ route('bankstatement.lender-detail', $lender->lender_id) }}"
                                                onclick="event.stopPropagation()"
                                                class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                                                View Patterns ({{ $lender->pattern_count }})
                                            </a>
                                            @endif
                                        </div>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>{{-- /lendersTable --}}


                </div>
            </div>

            {{-- ═══════════════════════ DEBT COLLECTORS TABLE ═══════════════════════ --}}
            <div id="collectorsSection" class="hidden bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-full">
                                <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            Debt Collectors
                        </h3>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" id="collectorSearch" placeholder="Search collectors…"
                                class="pl-9 pr-8 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-orange-500 focus:border-orange-500 w-52">
                        </div>
                    </div>

                    @if($debtCollectors->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8">#</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Collector Name</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Patterns</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Detections</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Used</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($debtCollectors as $index => $collector)
                                <tr class="collector-row hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                    data-name="{{ strtolower($collector->lender_name) }}"
                                    data-id="{{ strtolower($collector->lender_id) }}">
                                    <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-400 collector-row-number">{{ $index + 1 }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <a href="{{ route('bankstatement.lender-detail', $collector->lender_id) }}" class="flex items-center gap-2 group" onclick="event.stopPropagation()">
                                            <div class="flex-shrink-0 h-8 w-8 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center text-xs font-semibold text-orange-600 dark:text-orange-400">
                                                {{ strtoupper(substr($collector->lender_name, 0, 2)) }}
                                            </div>
                                            <span class="font-medium text-gray-900 dark:text-gray-100 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">{{ $collector->lender_name }}</span>
                                        </a>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-300">{{ $collector->lender_id }}</span>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">{{ $collector->pattern_count }}</span>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-center font-semibold text-gray-800 dark:text-gray-200">
                                        {{ number_format($collector->total_usage) }}
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $collector->last_used ? \Carbon\Carbon::parse($collector->last_used)->diffForHumans() : 'Never' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-center py-8 text-gray-400">No debt collectors found.</p>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── Tab switching ──────────────────────────────────────────────────────
        const lendersCard      = document.getElementById('lendersCard');
        const collectorsCard   = document.getElementById('collectorsCard');
        const lendersSection   = document.getElementById('lendersSection');
        const collectorsSection= document.getElementById('collectorsSection');

        lendersCard.addEventListener('click', () => {
            lendersSection.classList.remove('hidden');
            collectorsSection.classList.add('hidden');
            lendersCard.classList.add('ring-2','ring-purple-500','ring-offset-2','dark:ring-offset-gray-900');
            collectorsCard.classList.remove('ring-2','ring-orange-500','ring-offset-2','dark:ring-offset-gray-900');
        });
        collectorsCard.addEventListener('click', () => {
            collectorsSection.classList.remove('hidden');
            lendersSection.classList.add('hidden');
            collectorsCard.classList.add('ring-2','ring-orange-500','ring-offset-2','dark:ring-offset-gray-900');
            lendersCard.classList.remove('ring-2','ring-purple-500','ring-offset-2','dark:ring-offset-gray-900');
        });

        // ── Expand/collapse guideline rows ────────────────────────────────────
        window.toggleExpand = function(rowId, triggerRow) {
            const expandRow = document.getElementById(rowId);
            const chevron   = document.getElementById('chevron-' + rowId.replace('expand-',''));
            if (!expandRow) return;
            const isHidden = expandRow.classList.contains('hidden');
            expandRow.classList.toggle('hidden', !isHidden);
            chevron && chevron.classList.toggle('rotate-180', isHidden);
        };

        // ── Lender search + filter + sort ─────────────────────────────────────
        const lenderSearch  = document.getElementById('lenderSearch');
        const clearSearch   = document.getElementById('clearSearch');
        const statusFilter  = document.getElementById('statusFilter');
        const sortFilter    = document.getElementById('sortFilter');
        const resultCount   = document.getElementById('resultCount');
        const noResults     = document.getElementById('noResults');
        const lendersTable  = document.getElementById('lendersTable');

        function applyFilters() {
            const term   = lenderSearch.value.toLowerCase().trim();
            const status = statusFilter.value; // 'all' | 'active' | 'inactive'
            let visible  = 0;

            document.querySelectorAll('.lender-row').forEach(row => {
                const name   = row.dataset.name;
                const id     = row.dataset.id;
                const rowSt  = row.dataset.status; // 'active' | 'inactive'
                const matchSearch = !term || name.includes(term) || id.includes(term);
                const matchStatus = status === 'all' || rowSt === status;
                const show   = matchSearch && matchStatus;
                row.style.display = show ? '' : 'none';
                // Also hide the expand row if the main row is hidden
                const expandRow = document.getElementById(row.dataset.expandTarget);
                if (expandRow && !show) expandRow.style.display = 'none';
                if (expandRow && show) expandRow.style.display = ''; // restore (it may have been hidden by filter)
                if (show) visible++;
            });

            resultCount.textContent = visible;
            noResults.classList.toggle('hidden', visible > 0);
            lendersTable.classList.toggle('hidden', visible === 0);
            clearSearch.classList.toggle('hidden', !lenderSearch.value);
            updateRowNumbers();
        }

        function updateRowNumbers() {
            let n = 1;
            document.querySelectorAll('.lender-row').forEach(row => {
                if (row.style.display !== 'none') {
                    const cell = row.querySelector('.row-number');
                    if (cell) cell.textContent = n++;
                }
            });
        }

        function applySort() {
            const val  = sortFilter.value;
            const tbody= document.getElementById('lendersTbody');
            // Collect pairs [mainRow, expandRow]
            const pairs = [];
            let rows = Array.from(tbody.querySelectorAll('tr'));
            for (let i = 0; i < rows.length; i++) {
                if (rows[i].classList.contains('lender-row')) {
                    pairs.push([rows[i], rows[i+1] || null]);
                }
            }
            pairs.sort((a, b) => {
                const ra = a[0], rb = b[0];
                switch (val) {
                    case 'name-asc':   return ra.dataset.name.localeCompare(rb.dataset.name);
                    case 'name-desc':  return rb.dataset.name.localeCompare(ra.dataset.name);
                    case 'usage-desc': return +rb.dataset.usage - +ra.dataset.usage;
                    case 'usage-asc':  return +ra.dataset.usage - +rb.dataset.usage;
                    case 'credit-asc': return +ra.dataset.credit - +rb.dataset.credit;
                    case 'credit-desc':return +rb.dataset.credit - +ra.dataset.credit;
                    default: return 0;
                }
            });
            pairs.forEach(([main, exp]) => {
                tbody.appendChild(main);
                if (exp) tbody.appendChild(exp);
            });
            updateRowNumbers();
        }

        lenderSearch.addEventListener('input', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
        sortFilter.addEventListener('change', () => { applySort(); applyFilters(); });
        clearSearch.addEventListener('click', () => { lenderSearch.value = ''; applyFilters(); lenderSearch.focus(); });
        lenderSearch.addEventListener('keyup', e => { if (e.key === 'Escape') { lenderSearch.value = ''; applyFilters(); }});

        // ── Collector search ───────────────────────────────────────────────────
        const collSearch = document.getElementById('collectorSearch');
        if (collSearch) {
            collSearch.addEventListener('input', function () {
                const term = this.value.toLowerCase().trim();
                let n = 1;
                document.querySelectorAll('.collector-row').forEach(row => {
                    const show = !term || row.dataset.name.includes(term) || row.dataset.id.includes(term);
                    row.style.display = show ? '' : 'none';
                    if (show) { const c = row.querySelector('.collector-row-number'); if(c) c.textContent = n++; }
                });
            });
        }
    });
    </script>
</x-app-layout>
