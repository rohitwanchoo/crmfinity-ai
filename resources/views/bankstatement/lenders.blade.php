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

    <div class="py-12">
        <div class="w-full sm:px-6 lg:px-8">
            <!-- Success/Error Messages -->
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div id="lendersCard" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm cursor-pointer transition-all duration-200 hover:shadow-md ring-2 ring-purple-500 ring-offset-2 dark:ring-offset-gray-900">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Total Lenders</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_lenders']) }}</p>
                            </div>
                        </div>
                        <span class="text-xs text-purple-600 dark:text-purple-400 font-medium">Click to view</span>
                    </div>
                </div>
                <div id="collectorsCard" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm cursor-pointer transition-all duration-200 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-full">
                                <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Debt Collectors</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_debt_collectors']) }}</p>
                            </div>
                        </div>
                        <span class="text-xs text-orange-600 dark:text-orange-400 font-medium">Click to view</span>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Learned Patterns</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_patterns']) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Detections</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_usage']) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lenders Table -->
            <div id="lendersSection" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-full mr-3">
                                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </span>
                            MCA Lenders
                        </h3>

                        <!-- Search and Filter Controls -->
                        <div class="flex flex-col sm:flex-row gap-3 flex-1 sm:max-w-xl">
                            <!-- Search Input -->
                            <div class="flex-1">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" id="lenderSearch" placeholder="Search by name or ID..." class="block w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                                    <button type="button" id="clearSearch" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" title="Clear search">
                                        <svg class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Sort Filter -->
                            <div class="sm:w-48">
                                <select id="sortFilter" class="block w-full pl-3 pr-10 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                                    <option value="usage-desc">Most Detected</option>
                                    <option value="usage-asc">Least Detected</option>
                                    <option value="name-asc">Name (A-Z)</option>
                                    <option value="name-desc">Name (Z-A)</option>
                                    <option value="patterns-desc">Most Patterns</option>
                                    <option value="patterns-asc">Least Patterns</option>
                                    <option value="recent">Recently Used</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Results Count -->
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Showing <span id="resultCount">{{ $lenders->count() }}</span> of <span id="totalCount">{{ $lenders->count() }}</span> lenders
                        </p>
                    </div>

                    @if($lenders->count() > 0)
                    <!-- No Results Message -->
                    <div id="noResults" class="hidden text-center py-12">
                        <div class="flex flex-col items-center">
                            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No lenders found</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">Try adjusting your search or filter criteria</p>
                            <button onclick="document.getElementById('lenderSearch').value = ''; document.getElementById('lenderSearch').dispatchEvent(new Event('input'));" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition ease-in-out duration-150">
                                Clear Search
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto" id="lendersTable">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        #
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Lender Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Lender ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Patterns
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Detections
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Last Used
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($lenders as $index => $lender)
                                <tr class="lender-row hover:bg-gray-50 dark:hover:bg-gray-700"
                                    data-name="{{ strtolower($lender->lender_name) }}"
                                    data-id="{{ strtolower($lender->lender_id) }}"
                                    data-patterns="{{ $lender->pattern_count }}"
                                    data-usage="{{ $lender->total_usage }}"
                                    data-last-used="{{ $lender->last_used ?? '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 row-number">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('bankstatement.lender-detail', $lender->lender_id) }}" class="flex items-center group">
                                            <div class="flex-shrink-0 h-10 w-10 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center group-hover:bg-purple-200 dark:group-hover:bg-purple-800 transition-colors">
                                                <span class="text-purple-600 dark:text-purple-400 font-semibold text-sm">
                                                    {{ strtoupper(substr($lender->lender_name, 0, 2)) }}
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                                                    {{ $lender->lender_name }}
                                                    <svg class="inline-block w-4 h-4 ml-1 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                            {{ $lender->lender_id }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('bankstatement.lender-detail', $lender->lender_id) }}" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                                            {{ number_format($lender->pattern_count) }} {{ Str::plural('pattern', $lender->pattern_count) }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="font-semibold">{{ number_format($lender->total_usage) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $lender->last_used ? \Carbon\Carbon::parse($lender->last_used)->diffForHumans() : 'Never' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-12">
                        <div class="flex flex-col items-center">
                            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No lenders found</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">Start analyzing bank statements to detect MCA lenders</p>
                            <a href="{{ route('bankstatement.index') }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition ease-in-out duration-150">
                                Analyze Statement
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Debt Collectors Table -->
            <div id="collectorsSection" class="hidden bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-full mr-3">
                                <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            Debt Collectors
                        </h3>

                        <!-- Search and Filter Controls -->
                        <div class="flex flex-col sm:flex-row gap-3 flex-1 sm:max-w-xl">
                            <!-- Search Input -->
                            <div class="flex-1">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" id="collectorSearch" placeholder="Search collectors..." class="block w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 sm:text-sm">
                                    <button type="button" id="clearCollectorSearch" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" title="Clear search">
                                        <svg class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Sort Filter -->
                            <div class="sm:w-48">
                                <select id="collectorSortFilter" class="block w-full pl-3 pr-10 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 sm:text-sm">
                                    <option value="name-asc">Name (A-Z)</option>
                                    <option value="name-desc">Name (Z-A)</option>
                                    <option value="usage-desc">Most Detected</option>
                                    <option value="usage-asc">Least Detected</option>
                                    <option value="patterns-desc">Most Patterns</option>
                                    <option value="patterns-asc">Least Patterns</option>
                                    <option value="recent">Recently Used</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Results Count -->
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Showing <span id="collectorResultCount">{{ $debtCollectors->count() }}</span> of <span id="collectorTotalCount">{{ $debtCollectors->count() }}</span> collectors
                        </p>
                    </div>

                    @if($debtCollectors->count() > 0)
                    <!-- No Results Message -->
                    <div id="noCollectorResults" class="hidden text-center py-12">
                        <div class="flex flex-col items-center">
                            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No collectors found</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">Try adjusting your search criteria</p>
                            <button onclick="document.getElementById('collectorSearch').value = ''; document.getElementById('collectorSearch').dispatchEvent(new Event('input'));" class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 transition ease-in-out duration-150">
                                Clear Search
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto" id="collectorsTable">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        #
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Collector Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Collector ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Patterns
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Detections
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Last Used
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($debtCollectors as $index => $collector)
                                <tr class="collector-row hover:bg-gray-50 dark:hover:bg-gray-700"
                                    data-name="{{ strtolower($collector->lender_name) }}"
                                    data-id="{{ strtolower($collector->lender_id) }}"
                                    data-patterns="{{ $collector->pattern_count }}"
                                    data-usage="{{ $collector->total_usage }}"
                                    data-last-used="{{ $collector->last_used ?? '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 collector-row-number">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('bankstatement.lender-detail', $collector->lender_id) }}" class="flex items-center group">
                                            <div class="flex-shrink-0 h-10 w-10 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center group-hover:bg-orange-200 dark:group-hover:bg-orange-800 transition-colors">
                                                <span class="text-orange-600 dark:text-orange-400 font-semibold text-sm">
                                                    {{ strtoupper(substr($collector->lender_name, 0, 2)) }}
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">
                                                    {{ $collector->lender_name }}
                                                    <svg class="inline-block w-4 h-4 ml-1 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-300">
                                            {{ $collector->lender_id }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('bankstatement.lender-detail', $collector->lender_id) }}" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                                            {{ number_format($collector->pattern_count) }} {{ Str::plural('pattern', $collector->pattern_count) }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="font-semibold">{{ number_format($collector->total_usage) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $collector->last_used ? \Carbon\Carbon::parse($collector->last_used)->diffForHumans() : 'Never' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-8">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-1">No debt collectors found</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Debt collectors will appear here when detected in bank statements</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Information Card -->
            <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">About MCA Lenders & Debt Collectors</h3>
                        <div class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                            <p>The system automatically learns and tracks MCA (Merchant Cash Advance) lenders and debt collectors from bank statement transactions. Each time a transaction is identified, the pattern is learned and tracked for future detections.</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li><strong>Lenders:</strong> MCA providers who fund merchant cash advances</li>
                                <li><strong>Debt Collectors:</strong> Collection agencies that collect on defaulted MCAs</li>
                                <li><strong>Patterns:</strong> Number of unique transaction patterns learned for each entity</li>
                                <li><strong>Detections:</strong> Total times this entity has been detected across all statements</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Click JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const lendersCard = document.getElementById('lendersCard');
            const collectorsCard = document.getElementById('collectorsCard');
            const lendersSection = document.getElementById('lendersSection');
            const collectorsSection = document.getElementById('collectorsSection');

            if (lendersCard && collectorsCard && lendersSection && collectorsSection) {
                lendersCard.addEventListener('click', function() {
                    // Show lenders, hide collectors
                    lendersSection.classList.remove('hidden');
                    collectorsSection.classList.add('hidden');

                    // Update card styles
                    lendersCard.classList.add('ring-2', 'ring-purple-500', 'ring-offset-2', 'dark:ring-offset-gray-900');
                    collectorsCard.classList.remove('ring-2', 'ring-orange-500', 'ring-offset-2', 'dark:ring-offset-gray-900');
                });

                collectorsCard.addEventListener('click', function() {
                    // Show collectors, hide lenders
                    collectorsSection.classList.remove('hidden');
                    lendersSection.classList.add('hidden');

                    // Update card styles
                    collectorsCard.classList.add('ring-2', 'ring-orange-500', 'ring-offset-2', 'dark:ring-offset-gray-900');
                    lendersCard.classList.remove('ring-2', 'ring-purple-500', 'ring-offset-2', 'dark:ring-offset-gray-900');
                });
            }
        });
    </script>

    <!-- Collector Search and Sort JavaScript -->
    @if($debtCollectors->count() > 0)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const collectorSearchInput = document.getElementById('collectorSearch');
            const collectorSortFilter = document.getElementById('collectorSortFilter');
            const clearCollectorSearchBtn = document.getElementById('clearCollectorSearch');
            const collectorRows = document.querySelectorAll('.collector-row');
            const collectorResultCount = document.getElementById('collectorResultCount');

            if (!collectorSearchInput || !collectorRows.length) {
                return;
            }

            function filterCollectors() {
                const searchTerm = collectorSearchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                collectorRows.forEach(row => {
                    const name = row.dataset.name;
                    const id = row.dataset.id;
                    const matchesSearch = name.includes(searchTerm) || id.includes(searchTerm);

                    if (matchesSearch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                collectorResultCount.textContent = visibleCount;

                const noResults = document.getElementById('noCollectorResults');
                const collectorsTable = document.getElementById('collectorsTable');

                if (visibleCount === 0) {
                    noResults.classList.remove('hidden');
                    collectorsTable.classList.add('hidden');
                } else {
                    noResults.classList.add('hidden');
                    collectorsTable.classList.remove('hidden');
                }

                updateCollectorRowNumbers();
            }

            function sortCollectors() {
                const sortValue = collectorSortFilter.value;
                const tbody = document.querySelector('.collector-row').parentElement;
                const rowsArray = Array.from(collectorRows);

                rowsArray.sort((a, b) => {
                    switch(sortValue) {
                        case 'usage-desc':
                            return parseInt(b.dataset.usage) - parseInt(a.dataset.usage);
                        case 'usage-asc':
                            return parseInt(a.dataset.usage) - parseInt(b.dataset.usage);
                        case 'name-asc':
                            return a.dataset.name.localeCompare(b.dataset.name);
                        case 'name-desc':
                            return b.dataset.name.localeCompare(a.dataset.name);
                        case 'patterns-desc':
                            return parseInt(b.dataset.patterns) - parseInt(a.dataset.patterns);
                        case 'patterns-asc':
                            return parseInt(a.dataset.patterns) - parseInt(b.dataset.patterns);
                        case 'recent':
                            const dateA = a.dataset.lastUsed || '1970-01-01';
                            const dateB = b.dataset.lastUsed || '1970-01-01';
                            return new Date(dateB) - new Date(dateA);
                        default:
                            return 0;
                    }
                });

                rowsArray.forEach(row => tbody.appendChild(row));
                updateCollectorRowNumbers();
            }

            function updateCollectorRowNumbers() {
                let visibleIndex = 1;
                collectorRows.forEach(row => {
                    if (row.style.display !== 'none') {
                        const numberCell = row.querySelector('.collector-row-number');
                        if (numberCell) {
                            numberCell.textContent = visibleIndex++;
                        }
                    }
                });
            }

            collectorSearchInput.addEventListener('input', function() {
                if (collectorSearchInput.value.length > 0) {
                    clearCollectorSearchBtn.classList.remove('hidden');
                } else {
                    clearCollectorSearchBtn.classList.add('hidden');
                }
                filterCollectors();
            });

            collectorSortFilter.addEventListener('change', sortCollectors);

            clearCollectorSearchBtn.addEventListener('click', function() {
                collectorSearchInput.value = '';
                clearCollectorSearchBtn.classList.add('hidden');
                filterCollectors();
                collectorSearchInput.focus();
            });

            collectorSearchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Escape') {
                    collectorSearchInput.value = '';
                    clearCollectorSearchBtn.classList.add('hidden');
                    filterCollectors();
                }
            });
        });
    </script>
    @endif

    <!-- Search and Filter JavaScript -->
    @if($lenders->count() > 0)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('lenderSearch');
            const sortFilter = document.getElementById('sortFilter');
            const clearSearchBtn = document.getElementById('clearSearch');
            const lenderRows = document.querySelectorAll('.lender-row');
            const resultCount = document.getElementById('resultCount');
            const totalCount = document.getElementById('totalCount');
            const totalLenders = {{ $lenders->count() }};

            // Check if elements exist
            if (!searchInput || !sortFilter || !clearSearchBtn || !lenderRows.length) {
                return;
            }

            // Search functionality
            function filterLenders() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                lenderRows.forEach(row => {
                    const name = row.dataset.name;
                    const id = row.dataset.id;
                    const matchesSearch = name.includes(searchTerm) || id.includes(searchTerm);

                    if (matchesSearch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update result count
                resultCount.textContent = visibleCount;

                // Show/hide no results message
                const noResults = document.getElementById('noResults');
                const lendersTable = document.getElementById('lendersTable');

                if (visibleCount === 0) {
                    noResults.classList.remove('hidden');
                    lendersTable.classList.add('hidden');
                } else {
                    noResults.classList.add('hidden');
                    lendersTable.classList.remove('hidden');
                }

                // Update row numbers for visible rows
                updateRowNumbers();
            }

            // Sort functionality
            function sortLenders() {
                const sortValue = sortFilter.value;
                const tbody = document.querySelector('.lender-row').parentElement;
                const rowsArray = Array.from(lenderRows);

                rowsArray.sort((a, b) => {
                    switch(sortValue) {
                        case 'usage-desc':
                            return parseInt(b.dataset.usage) - parseInt(a.dataset.usage);
                        case 'usage-asc':
                            return parseInt(a.dataset.usage) - parseInt(b.dataset.usage);
                        case 'name-asc':
                            return a.dataset.name.localeCompare(b.dataset.name);
                        case 'name-desc':
                            return b.dataset.name.localeCompare(a.dataset.name);
                        case 'patterns-desc':
                            return parseInt(b.dataset.patterns) - parseInt(a.dataset.patterns);
                        case 'patterns-asc':
                            return parseInt(a.dataset.patterns) - parseInt(b.dataset.patterns);
                        case 'recent':
                            const dateA = a.dataset.lastUsed || '1970-01-01';
                            const dateB = b.dataset.lastUsed || '1970-01-01';
                            return new Date(dateB) - new Date(dateA);
                        default:
                            return 0;
                    }
                });

                // Reorder DOM elements
                rowsArray.forEach(row => tbody.appendChild(row));

                // Update row numbers
                updateRowNumbers();
            }

            // Update row numbers based on visible rows
            function updateRowNumbers() {
                let visibleIndex = 1;
                lenderRows.forEach(row => {
                    if (row.style.display !== 'none') {
                        const numberCell = row.querySelector('.row-number');
                        if (numberCell) {
                            numberCell.textContent = visibleIndex++;
                        }
                    }
                });
            }

            // Event listeners
            searchInput.addEventListener('input', function() {
                // Show/hide clear button
                if (searchInput.value.length > 0) {
                    clearSearchBtn.classList.remove('hidden');
                } else {
                    clearSearchBtn.classList.add('hidden');
                }
                filterLenders();
            });

            sortFilter.addEventListener('change', sortLenders);

            // Clear search button click
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                clearSearchBtn.classList.add('hidden');
                filterLenders();
                searchInput.focus();
            });

            // Clear search with Escape key
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    clearSearchBtn.classList.add('hidden');
                    filterLenders();
                }
            });
        });
    </script>
    @endif
</x-app-layout>
