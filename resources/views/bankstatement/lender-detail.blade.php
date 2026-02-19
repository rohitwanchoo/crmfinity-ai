<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                        <span class="text-purple-600 dark:text-purple-400 font-semibold text-lg">
                            {{ strtoupper(substr($lender['name'], 0, 2)) }}
                        </span>
                    </div>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            {{ $lender['name'] }}
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Lender ID: {{ $lender['id'] }}</p>
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('bankstatement.lenders.pattern.create', $lender['id']) }}" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 active:bg-purple-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Pattern
                </a>
                <a href="{{ route('bankstatement.lenders') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Lenders
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
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Patterns</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($lender['total_patterns']) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Detections</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($lender['total_usage']) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">First Seen</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $lender['first_seen']->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-full">
                            <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Last Used</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $lender['last_used']->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lender Guidelines Panel -->
            @if($guideline)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Lender Guidelines</h3>
                        <div class="flex items-center gap-3">
                            @if($guideline->status === 'ACTIVE')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">ACTIVE</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">INACTIVE</span>
                            @endif
                            @if($guideline->white_label)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">White Label</span>
                            @endif
                            <a href="{{ route('bankstatement.lenders.edit', $lender['id']) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-purple-600 text-white hover:bg-purple-700 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit Guidelines
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                        {{-- Financial Criteria --}}
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Financial Criteria</h4>
                            <dl class="space-y-2 text-sm">
                                @if($guideline->min_credit_score)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Min Credit Score</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->min_credit_score }}</dd></div>
                                @endif
                                @if($guideline->min_time_in_business)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Min Time in Business</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->min_time_in_business }} months</dd></div>
                                @endif
                                @if($guideline->min_monthly_deposits)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Min Monthly Deposits</dt><dd class="font-medium text-gray-900 dark:text-gray-100">${{ number_format($guideline->min_monthly_deposits) }}</dd></div>
                                @endif
                                @if($guideline->min_loan_amount || $guideline->max_loan_amount)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Loan Amount</dt>
                                    <dd class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $guideline->min_loan_amount ? '$'.number_format($guideline->min_loan_amount) : '—' }}
                                        –
                                        {{ $guideline->max_loan_amount ? '$'.number_format($guideline->max_loan_amount) : '—' }}
                                    </dd></div>
                                @endif
                                @if($guideline->max_negative_days !== null)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Max Negative Days</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->max_negative_days }}</dd></div>
                                @endif
                                @if($guideline->max_nsfs !== null)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Max NSFs/Month</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->max_nsfs }}</dd></div>
                                @endif
                                @if($guideline->max_positions !== null)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Max Positions</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->max_positions }}</dd></div>
                                @endif
                                @if($guideline->min_avg_daily_balance)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Min Avg Daily Balance</dt><dd class="font-medium text-gray-900 dark:text-gray-100">${{ number_format($guideline->min_avg_daily_balance) }}</dd></div>
                                @endif
                            </dl>
                        </div>

                        {{-- Funding Terms + Business Type --}}
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Funding Terms</h4>
                            <dl class="space-y-2 text-sm">
                                @if($guideline->product_type)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Product</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->product_type }}</dd></div>
                                @endif
                                @if($guideline->factor_rate)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Factor Rate</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->factor_rate }}</dd></div>
                                @endif
                                @if($guideline->max_term)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Max Term</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->max_term }}</dd></div>
                                @endif
                                @if($guideline->funding_speed)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Funding Speed</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->funding_speed }}</dd></div>
                                @endif
                                @if($guideline->payment_frequency)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Payment</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $guideline->payment_frequency }}</dd></div>
                                @endif
                                @if($guideline->bonus_available !== null)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Bonus</dt>
                                    <dd class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $guideline->bonus_available ? 'YES' : 'NO' }}
                                        @if($guideline->bonus_details) — {{ $guideline->bonus_details }} @endif
                                    </dd></div>
                                @endif
                            </dl>

                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 mt-5">Business Types</h4>
                            <dl class="space-y-2 text-sm">
                                @foreach([
                                    ['Sole Proprietors',    $guideline->sole_proprietors],
                                    ['Home-Based',         $guideline->home_based_business],
                                    ['Consolidations',     $guideline->consolidation_deals],
                                    ['Non-Profits',        $guideline->non_profits],
                                ] as [$label, $val])
                                @if($val)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="font-medium {{ $val === 'YES' ? 'text-green-600 dark:text-green-400' : ($val === 'NO' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400') }}">{{ $val }}</dd>
                                </div>
                                @endif
                                @endforeach
                            </dl>
                        </div>

                        {{-- Special Circumstances + Restrictions --}}
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Special Circumstances</h4>
                            <dl class="space-y-2 text-sm">
                                @foreach([
                                    ['Bankruptcy',       $guideline->bankruptcy],
                                    ['Tax Lien',         $guideline->tax_lien],
                                    ['Prior Default',    $guideline->prior_default],
                                    ['Criminal History', $guideline->criminal_history],
                                ] as [$label, $val])
                                @if($val)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="font-medium {{ $val === 'NO' ? 'text-red-600 dark:text-red-400' : ($val === 'YES' ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400') }}">{{ $val }}</dd>
                                </div>
                                @endif
                                @endforeach
                            </dl>

                            @if(!empty($guideline->restricted_states))
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 mt-5">Restricted States</h4>
                            <div class="flex flex-wrap gap-1">
                                @foreach($guideline->restricted_states as $state)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ $state }}</span>
                                @endforeach
                            </div>
                            @endif

                            @if(!empty($guideline->excluded_industries))
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 mt-5">Excluded Industries</h4>
                            <div class="flex flex-wrap gap-1">
                                @foreach($guideline->excluded_industries as $industry)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">{{ $industry }}</span>
                                @endforeach
                            </div>
                            @endif

                            @if($guideline->notes)
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 mt-5">Notes</h4>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $guideline->notes }}</p>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
            @endif

            <!-- Patterns Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Learned Patterns</h3>
                        @if($patterns->isEmpty())
                        <a href="{{ route('bankstatement.lenders.pattern.create', $lender['id']) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium bg-purple-600 text-white hover:bg-purple-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Pattern
                        </a>
                        @endif

                        <!-- Search Pattern -->
                        <div class="sm:max-w-md">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" id="patternSearch" placeholder="Search patterns..." class="block w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                                <button type="button" id="clearPatternSearch" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" title="Clear search">
                                    <svg class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    @if($patterns->isEmpty())
                    <!-- Empty patterns state -->
                    <div class="text-center py-16">
                        <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No patterns yet</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">This lender has no learned transaction patterns. Patterns are detected automatically when bank statements are analyzed, or you can add one manually.</p>
                        <a href="{{ route('bankstatement.lenders.pattern.create', $lender['id']) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-md text-sm font-medium hover:bg-purple-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Pattern Manually
                        </a>
                    </div>
                    @else

                    <!-- Results Count -->
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Showing <span id="resultCount">{{ $patterns->count() }}</span> of <span id="totalCount">{{ $patterns->count() }}</span> patterns
                        </p>
                    </div>

                    <!-- No Results Message (from search filter) -->
                    <div id="noResults" class="hidden text-center py-12">
                        <div class="flex flex-col items-center">
                            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No patterns found</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">Try adjusting your search</p>
                            <button onclick="document.getElementById('patternSearch').value = ''; document.getElementById('patternSearch').dispatchEvent(new Event('input'));" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition ease-in-out duration-150">
                                Clear Search
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto" id="patternsTable">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        #
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Transaction Pattern
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Usage Count
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Created
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Last Used
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($patterns as $index => $pattern)
                                <tr class="pattern-row hover:bg-gray-50 dark:hover:bg-gray-700"
                                    data-pattern="{{ strtolower($pattern->description_pattern) }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 row-number">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                        <div class="max-w-md">
                                            <code class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ $pattern->description_pattern }}</code>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <div class="flex items-center">
                                            <div class="mr-2 w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                @php
                                                    $maxUsage = $patterns->max('usage_count') ?? 0;
                                                    $percentage = ($maxUsage > 0 && $pattern->usage_count > 0) ? ($pattern->usage_count / $maxUsage) * 100 : 0;
                                                @endphp
                                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                            </div>
                                            <span class="font-semibold">{{ number_format($pattern->usage_count) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $pattern->created_at->format('M d, Y') }}
                                        <span class="block text-xs">{{ $pattern->created_at->format('h:i A') }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $pattern->updated_at->diffForHumans() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('bankstatement.lenders.pattern.edit', [$lender['id'], $pattern->id]) }}" class="inline-flex items-center px-3 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif {{-- end @else (patterns not empty) --}}
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
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">About Patterns</h3>
                        <div class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                            <p>These patterns represent transaction descriptions that have been identified as belonging to {{ $lender['name'] }}. The system normalizes transaction descriptions by removing dates and amounts to create reusable patterns.</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li><strong>Transaction Pattern:</strong> The normalized description pattern used to match transactions</li>
                                <li><strong>Usage Count:</strong> Number of times this pattern has been matched in analyzed statements</li>
                                <li><strong>Created:</strong> When this pattern was first learned</li>
                                <li><strong>Last Used:</strong> When this pattern was last matched</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('patternSearch');
            const clearSearchBtn = document.getElementById('clearPatternSearch');
            const patternRows = document.querySelectorAll('.pattern-row');
            const resultCount = document.getElementById('resultCount');
            const totalCount = document.getElementById('totalCount');
            const noResults = document.getElementById('noResults');
            const patternsTable = document.getElementById('patternsTable');

            // Check if elements exist
            if (!searchInput || !clearSearchBtn || !patternRows.length) {
                return;
            }

            function filterPatterns() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                patternRows.forEach(row => {
                    const pattern = row.dataset.pattern;
                    const matchesSearch = pattern.includes(searchTerm);

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
                if (visibleCount === 0) {
                    noResults.classList.remove('hidden');
                    patternsTable.classList.add('hidden');
                } else {
                    noResults.classList.add('hidden');
                    patternsTable.classList.remove('hidden');
                }

                // Update row numbers
                updateRowNumbers();
            }

            function updateRowNumbers() {
                let visibleIndex = 1;
                patternRows.forEach(row => {
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
                if (searchInput.value.length > 0) {
                    clearSearchBtn.classList.remove('hidden');
                } else {
                    clearSearchBtn.classList.add('hidden');
                }
                filterPatterns();
            });

            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                clearSearchBtn.classList.add('hidden');
                filterPatterns();
                searchInput.focus();
            });

            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    clearSearchBtn.classList.add('hidden');
                    filterPatterns();
                }
            });
        });
    </script>
</x-app-layout>
