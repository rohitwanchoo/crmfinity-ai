<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            MCA Pricing Calculator
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header with navigation -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pricing & Offer Calculator</h3>
                    <p class="text-gray-600 dark:text-gray-400">Calculate MCA offers with 20% withhold cap enforcement</p>
                </div>
                <a href="{{ route('smartmca.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Calculator Form -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                Input Parameters
                            </h4>

                            <form id="pricing-form" class="space-y-4">
                                @csrf
                                <!-- Monthly True Revenue -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Monthly True Revenue *
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                        <input type="number" name="monthly_true_revenue" id="monthly_true_revenue"
                                               class="pl-7 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="100,000" min="1000" step="1000" required
                                               value="{{ $trueRevenue ?? '' }}">
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Average monthly revenue from bank statements</p>
                                </div>

                                <!-- Existing Daily Payment -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Existing Daily MCA Payment
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                        <input type="number" name="existing_daily_payment" id="existing_daily_payment"
                                               class="pl-7 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="0" min="0" step="10"
                                               value="{{ $existingPayment ?? 0 }}">
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total daily payment to existing MCA positions</p>
                                </div>

                                <!-- Requested Amount -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Requested Amount *
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                        <input type="number" name="requested_amount" id="requested_amount"
                                               class="pl-7 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="50,000" min="5000" max="500000" step="1000" required>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">$5,000 - $500,000</p>
                                </div>

                                <!-- Position -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Position
                                    </label>
                                    <select name="position" id="position"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="1">1st Position</option>
                                        <option value="2">2nd Position</option>
                                        <option value="3">3rd Position</option>
                                        <option value="4">4th Position</option>
                                    </select>
                                </div>

                                <!-- Term Months -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Term (Months)
                                    </label>
                                    <input type="range" name="term_months" id="term_months"
                                           class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
                                           min="2" max="12" value="6">
                                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <span>2 mo</span>
                                        <span id="term_display" class="font-semibold text-blue-600 dark:text-blue-400">6 months</span>
                                        <span>12 mo</span>
                                    </div>
                                </div>

                                <!-- Factor Rate (optional) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Factor Rate (Optional)
                                    </label>
                                    <input type="number" name="factor_rate" id="factor_rate"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="Auto-calculated" min="1.10" max="1.75" step="0.01">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to auto-calculate based on risk</p>
                                </div>

                                <!-- Industry -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Industry
                                    </label>
                                    <select name="industry" id="industry"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">-- Select Industry --</option>
                                        <option value="restaurant">Restaurant</option>
                                        <option value="retail">Retail</option>
                                        <option value="healthcare">Healthcare</option>
                                        <option value="construction">Construction</option>
                                        <option value="transportation">Transportation</option>
                                        <option value="professional_services">Professional Services</option>
                                        <option value="manufacturing">Manufacturing</option>
                                        <option value="auto_repair">Auto Repair</option>
                                        <option value="beauty_salon">Beauty Salon</option>
                                        <option value="cannabis">Cannabis (High Risk)</option>
                                    </select>
                                </div>

                                <!-- Credit Score -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Credit Score
                                    </label>
                                    <input type="number" name="credit_score" id="credit_score"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="680" min="300" max="850">
                                </div>

                                <!-- Risk Score -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Risk Score (0-100)
                                    </label>
                                    <input type="number" name="risk_score" id="risk_score"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="65" min="0" max="100" value="65">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Higher = lower risk</p>
                                </div>

                                <!-- Volatility Level -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Revenue Volatility
                                    </label>
                                    <select name="volatility_level" id="volatility_level"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="low">Low (Steady revenue)</option>
                                        <option value="medium" selected>Medium (Normal fluctuation)</option>
                                        <option value="high">High (Significant swings)</option>
                                    </select>
                                </div>

                                <!-- Calculate Button -->
                                <div class="pt-4">
                                    <button type="submit" id="calculate-btn"
                                            class="w-full inline-flex justify-center items-center px-4 py-3 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                        Calculate Offer
                                    </button>
                                </div>

                                <!-- Generate Scenarios Button -->
                                <div>
                                    <button type="button" id="scenarios-btn"
                                            class="w-full inline-flex justify-center items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:bg-purple-700 active:bg-purple-800 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                        </svg>
                                        Compare Scenarios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Results Display -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Offer Summary Card -->
                    <div id="offer-summary" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hidden">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Offer Summary
                                </h4>
                                <span id="status-badge" class="px-3 py-1 text-sm font-semibold rounded-full"></span>
                            </div>

                            <!-- Main Offer Details -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                                    <p class="text-sm text-green-600 dark:text-green-400 font-medium">Approved Amount</p>
                                    <p id="approved-amount" class="text-2xl font-bold text-green-700 dark:text-green-300">$0</p>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
                                    <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Factor Rate</p>
                                    <p id="factor-rate-display" class="text-2xl font-bold text-blue-700 dark:text-blue-300">1.00</p>
                                </div>
                                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 text-center">
                                    <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">Payback Amount</p>
                                    <p id="payback-amount" class="text-2xl font-bold text-purple-700 dark:text-purple-300">$0</p>
                                </div>
                                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 text-center">
                                    <p class="text-sm text-orange-600 dark:text-orange-400 font-medium">Daily Payment</p>
                                    <p id="daily-payment" class="text-2xl font-bold text-orange-700 dark:text-orange-300">$0</p>
                                </div>
                            </div>

                            <!-- Additional Details Row -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Term</p>
                                    <p id="term-display" class="text-lg font-semibold text-gray-700 dark:text-gray-200">6 mo</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Weekly Payment</p>
                                    <p id="weekly-payment" class="text-lg font-semibold text-gray-700 dark:text-gray-200">$0</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Monthly Payment</p>
                                    <p id="monthly-payment" class="text-lg font-semibold text-gray-700 dark:text-gray-200">$0</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Position</p>
                                    <p id="position-display" class="text-lg font-semibold text-gray-700 dark:text-gray-200">1st</p>
                                </div>
                            </div>

                            <!-- Cost of Capital Card -->
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                                <h5 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-2 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Cost of Capital
                                </h5>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-2xl font-bold text-red-700 dark:text-red-300" id="cost-of-capital">$0</span>
                                        <span class="text-gray-500 dark:text-gray-400 ml-2">total cost</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xl font-bold text-red-700 dark:text-red-300" id="cost-percentage">0%</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">of funded amount</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Explanation Text -->
                            <div id="explanation-text" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <p class="text-sm text-blue-700 dark:text-blue-300"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Withhold Capacity Visualization -->
                    <div id="capacity-section" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hidden">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Withhold Capacity (20% Max)
                            </h4>

                            <!-- Capacity Progress Bar -->
                            <div class="mb-6">
                                <div class="flex justify-between text-sm mb-2">
                                    <span class="text-gray-600 dark:text-gray-400">Daily Revenue Allocation</span>
                                    <span id="total-withhold-percent" class="font-semibold text-gray-700 dark:text-gray-200">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6 overflow-hidden">
                                    <div class="h-full flex">
                                        <div id="existing-bar" class="bg-orange-500 transition-all duration-500" style="width: 0%"></div>
                                        <div id="new-bar" class="bg-blue-500 transition-all duration-500" style="width: 0%"></div>
                                        <div id="remaining-bar" class="bg-green-200 dark:bg-green-800 transition-all duration-500" style="width: 100%"></div>
                                    </div>
                                </div>
                                <div class="flex justify-between mt-2 text-xs">
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 bg-orange-500 rounded mr-1"></span>
                                        <span class="text-gray-600 dark:text-gray-400">Existing (<span id="existing-percent">0</span>%)</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 bg-blue-500 rounded mr-1"></span>
                                        <span class="text-gray-600 dark:text-gray-400">New (<span id="new-percent">0</span>%)</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 bg-green-200 dark:bg-green-800 rounded mr-1"></span>
                                        <span class="text-gray-600 dark:text-gray-400">Available (<span id="remaining-percent">20</span>%)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Withhold Breakdown Table -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-700">
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Category</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Daily Payment</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">% of Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Existing MCA Payments</td>
                                            <td id="existing-daily" class="px-4 py-2 text-sm text-right font-medium text-orange-600 dark:text-orange-400">$0</td>
                                            <td id="existing-withhold" class="px-4 py-2 text-sm text-right text-gray-600 dark:text-gray-400">0%</td>
                                        </tr>
                                        <tr class="bg-blue-50 dark:bg-blue-900/10">
                                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 font-medium">+ New Offer Payment</td>
                                            <td id="new-daily" class="px-4 py-2 text-sm text-right font-medium text-blue-600 dark:text-blue-400">$0</td>
                                            <td id="new-withhold" class="px-4 py-2 text-sm text-right text-gray-600 dark:text-gray-400">0%</td>
                                        </tr>
                                        <tr class="bg-gray-100 dark:bg-gray-700 font-semibold">
                                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">= Total Daily Payment</td>
                                            <td id="total-daily" class="px-4 py-2 text-sm text-right text-gray-700 dark:text-gray-200">$0</td>
                                            <td id="total-withhold" class="px-4 py-2 text-sm text-right text-gray-700 dark:text-gray-200">0%</td>
                                        </tr>
                                        <tr class="border-t-2 border-green-500">
                                            <td class="px-4 py-2 text-sm text-green-600 dark:text-green-400">Remaining Capacity</td>
                                            <td id="remaining-daily" class="px-4 py-2 text-sm text-right font-medium text-green-600 dark:text-green-400">$0</td>
                                            <td id="remaining-withhold" class="px-4 py-2 text-sm text-right text-green-600 dark:text-green-400">20%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Math Breakdown (Explainability) -->
                    <div id="math-section" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hidden">
                        <div class="p-6">
                            <button type="button" id="toggle-math" class="w-full flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <span class="flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    Step-by-Step Math Breakdown
                                </span>
                                <svg id="math-arrow" class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div id="math-content" class="mt-4 space-y-4 hidden">
                                <!-- Step 1: Revenue -->
                                <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 rounded-r-lg p-4">
                                    <h5 class="font-semibold text-green-700 dark:text-green-400 mb-2">Step 1: Revenue Calculation</h5>
                                    <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                        <p>Monthly True Revenue: <span id="math-monthly-revenue" class="font-mono font-semibold">$0</span></p>
                                        <p>Business Days/Month: <span id="math-business-days" class="font-mono">21.67</span></p>
                                        <p class="pt-1 border-t border-green-300 dark:border-green-700">
                                            Daily True Revenue: <span id="math-daily-revenue" class="font-mono font-semibold text-green-700 dark:text-green-400">$0</span>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">Formula: Monthly Revenue / Business Days</p>
                                    </div>
                                </div>

                                <!-- Step 2: Capacity -->
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 rounded-r-lg p-4">
                                    <h5 class="font-semibold text-yellow-700 dark:text-yellow-400 mb-2">Step 2: Capacity Calculation</h5>
                                    <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                        <p>Max Withhold %: <span id="math-max-withhold" class="font-mono">20%</span></p>
                                        <p>Max Daily Payment: <span id="math-max-daily" class="font-mono font-semibold">$0</span></p>
                                        <p>Existing Daily Payment: <span id="math-existing-daily" class="font-mono">$0</span></p>
                                        <p class="pt-1 border-t border-yellow-300 dark:border-yellow-700">
                                            Remaining Capacity: <span id="math-remaining-capacity" class="font-mono font-semibold text-yellow-700 dark:text-yellow-400">$0/day</span>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">Formula: (Daily Revenue x Max %) - Existing Payments</p>
                                    </div>
                                </div>

                                <!-- Step 3: Max Funding -->
                                <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 rounded-r-lg p-4">
                                    <h5 class="font-semibold text-blue-700 dark:text-blue-400 mb-2">Step 3: Maximum Funding</h5>
                                    <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                        <p>Remaining Daily Capacity: <span id="math-remaining-daily" class="font-mono">$0</span></p>
                                        <p>Term (Business Days): <span id="math-term-days" class="font-mono">0</span></p>
                                        <p>Factor Rate: <span id="math-factor-rate" class="font-mono">1.00</span></p>
                                        <p>Max Payback Possible: <span id="math-max-payback" class="font-mono">$0</span></p>
                                        <p class="pt-1 border-t border-blue-300 dark:border-blue-700">
                                            Max Funding by Capacity: <span id="math-max-funding" class="font-mono font-semibold text-blue-700 dark:text-blue-400">$0</span>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">Formula: (Remaining Capacity x Term Days) / Factor Rate</p>
                                    </div>
                                </div>

                                <!-- Step 4: Approval -->
                                <div class="bg-purple-50 dark:bg-purple-900/20 border-l-4 border-purple-500 rounded-r-lg p-4">
                                    <h5 class="font-semibold text-purple-700 dark:text-purple-400 mb-2">Step 4: Approval Calculation</h5>
                                    <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                        <p>Requested Amount: <span id="math-requested" class="font-mono">$0</span></p>
                                        <p>Max by Capacity: <span id="math-capacity-max" class="font-mono">$0</span></p>
                                        <p>Approval %: <span id="math-approval-percent" class="font-mono">100%</span></p>
                                        <p>Max by Approval: <span id="math-approval-max" class="font-mono">$0</span></p>
                                        <p class="pt-1 border-t border-purple-300 dark:border-purple-700">
                                            Approved Amount: <span id="math-approved" class="font-mono font-semibold text-purple-700 dark:text-purple-400">$0</span>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">Formula: MIN(Capacity Max, Request x Approval %)</p>
                                    </div>
                                </div>

                                <!-- Step 5: Final -->
                                <div class="bg-indigo-50 dark:bg-indigo-900/20 border-l-4 border-indigo-500 rounded-r-lg p-4">
                                    <h5 class="font-semibold text-indigo-700 dark:text-indigo-400 mb-2">Step 5: Final Payment</h5>
                                    <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                        <p>Approved Amount: <span id="math-final-approved" class="font-mono">$0</span></p>
                                        <p>Factor Rate: <span id="math-final-factor" class="font-mono">1.00</span></p>
                                        <p>Payback Amount: <span id="math-final-payback" class="font-mono">$0</span></p>
                                        <p>Term (Business Days): <span id="math-final-days" class="font-mono">0</span></p>
                                        <p class="pt-1 border-t border-indigo-300 dark:border-indigo-700">
                                            Daily Payment: <span id="math-final-daily" class="font-mono font-semibold text-indigo-700 dark:text-indigo-400">$0</span>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">Formula: (Approved x Factor Rate) / Term Days</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Adjustments Applied -->
                    <div id="adjustments-section" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hidden">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                                </svg>
                                Pricing Adjustments Applied
                            </h4>
                            <div id="adjustments-list" class="space-y-2">
                                <!-- Adjustments will be populated here -->
                            </div>
                        </div>
                    </div>

                    <!-- Scenario Comparison -->
                    <div id="scenarios-section" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hidden">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                                Scenario Comparison
                            </h4>
                            <div id="scenarios-grid" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Scenarios will be populated here -->
                            </div>
                            <div id="scenario-recommendation" class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg hidden">
                                <p class="text-sm text-green-700 dark:text-green-300"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Declined Card -->
                    <div id="declined-card" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hidden">
                        <div class="p-6">
                            <div class="flex items-center mb-4">
                                <svg class="w-10 h-10 text-red-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <h4 class="text-lg font-semibold text-red-700 dark:text-red-400">Offer Declined</h4>
                                    <p id="decline-reason" class="text-sm text-gray-600 dark:text-gray-400"></p>
                                </div>
                            </div>
                            <div id="decline-explanation" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                                <p class="text-sm text-red-700 dark:text-red-300"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Initial State / Empty State -->
                    <div id="empty-state" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-12 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Enter Parameters to Calculate</h3>
                            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                                Fill in the merchant's financial details on the left to calculate an MCA offer with full transparency on pricing and withhold capacity.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 flex items-center">
            <svg class="animate-spin h-8 w-8 text-blue-600 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-700 dark:text-gray-200 font-medium">Calculating offer...</span>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-4 right-4 z-50 hidden">
        <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span id="toast-message"></span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('pricing-form');
            const termSlider = document.getElementById('term_months');
            const termDisplay = document.getElementById('term_display');
            const calculateBtn = document.getElementById('calculate-btn');
            const scenariosBtn = document.getElementById('scenarios-btn');
            const loadingOverlay = document.getElementById('loading-overlay');
            const toggleMathBtn = document.getElementById('toggle-math');
            const mathContent = document.getElementById('math-content');
            const mathArrow = document.getElementById('math-arrow');

            // Update term display on slider change
            termSlider.addEventListener('input', function() {
                termDisplay.textContent = this.value + ' months';
            });

            // Toggle math breakdown
            toggleMathBtn.addEventListener('click', function() {
                mathContent.classList.toggle('hidden');
                mathArrow.classList.toggle('rotate-180');
            });

            // Calculate offer
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                await calculateOffer();
            });

            // Generate scenarios
            scenariosBtn.addEventListener('click', async function() {
                await generateScenarios();
            });

            async function calculateOffer() {
                loadingOverlay.classList.remove('hidden');

                const formData = new FormData(form);
                const data = {};
                formData.forEach((value, key) => {
                    if (value !== '') {
                        data[key] = isNaN(value) ? value : parseFloat(value);
                    }
                });

                try {
                    const response = await fetch('{{ route("smartmca.pricing.calculate") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    displayResults(result);
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Error calculating offer', true);
                }

                loadingOverlay.classList.add('hidden');
            }

            async function generateScenarios() {
                loadingOverlay.classList.remove('hidden');

                const formData = new FormData(form);
                const data = {};
                formData.forEach((value, key) => {
                    if (value !== '' && key !== 'factor_rate' && key !== 'term_months') {
                        data[key] = isNaN(value) ? value : parseFloat(value);
                    }
                });

                try {
                    const response = await fetch('{{ route("smartmca.pricing.scenarios") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    displayScenarios(result);
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Error generating scenarios', true);
                }

                loadingOverlay.classList.add('hidden');
            }

            function displayResults(result) {
                // Hide empty state
                document.getElementById('empty-state').classList.add('hidden');

                // Reset all sections
                document.getElementById('offer-summary').classList.add('hidden');
                document.getElementById('capacity-section').classList.add('hidden');
                document.getElementById('math-section').classList.add('hidden');
                document.getElementById('adjustments-section').classList.add('hidden');
                document.getElementById('declined-card').classList.add('hidden');

                if (!result.success || result.status === 'declined') {
                    // Show declined card
                    const declinedCard = document.getElementById('declined-card');
                    declinedCard.classList.remove('hidden');

                    document.getElementById('decline-reason').textContent = formatDeclineReason(result.data?.decline_reason || 'Unknown');
                    document.getElementById('decline-explanation').querySelector('p').textContent = result.data?.explanation || 'Unable to calculate offer.';

                    // Still show capacity if available
                    if (result.data?.capacity) {
                        displayCapacity(result.data.capacity, null);
                    }
                    return;
                }

                const data = result.data;
                const offer = data.offer;

                // Show offer summary
                const summaryCard = document.getElementById('offer-summary');
                summaryCard.classList.remove('hidden');

                // Status badge
                const statusBadge = document.getElementById('status-badge');
                if (data.status === 'approved') {
                    statusBadge.textContent = 'Approved';
                    statusBadge.className = 'px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                } else if (data.status === 'approved_reduced') {
                    statusBadge.textContent = 'Approved (Reduced)';
                    statusBadge.className = 'px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                }

                // Main offer details
                document.getElementById('approved-amount').textContent = formatCurrency(offer.funding_amount);
                document.getElementById('factor-rate-display').textContent = offer.factor_rate.toFixed(2);
                document.getElementById('payback-amount').textContent = formatCurrency(offer.payback_amount);
                document.getElementById('daily-payment').textContent = formatCurrency(offer.daily_payment);
                document.getElementById('term-display').textContent = offer.term_months + ' mo';
                document.getElementById('weekly-payment').textContent = formatCurrency(offer.weekly_payment);
                document.getElementById('monthly-payment').textContent = formatCurrency(offer.monthly_payment);
                document.getElementById('position-display').textContent = getPositionText(offer.position);

                // Cost of capital
                document.getElementById('cost-of-capital').textContent = formatCurrency(offer.cost_of_capital);
                document.getElementById('cost-percentage').textContent = offer.cost_percentage.toFixed(1) + '%';

                // Explanation
                document.getElementById('explanation-text').querySelector('p').textContent = data.explanation;

                // Capacity visualization
                if (data.capacity) {
                    displayCapacity(data.capacity, offer.withhold_breakdown);
                }

                // Math breakdown
                if (data.math_breakdown) {
                    displayMathBreakdown(data.math_breakdown);
                }

                // Adjustments
                if (data.adjustments) {
                    displayAdjustments(data.adjustments);
                }
            }

            function displayCapacity(capacity, withholdBreakdown) {
                const section = document.getElementById('capacity-section');
                section.classList.remove('hidden');

                const maxWithhold = capacity.max_withhold_percent || 20;
                let existingPercent = capacity.current_withhold_percent || 0;
                let newPercent = 0;
                let totalPercent = existingPercent;

                if (withholdBreakdown) {
                    newPercent = withholdBreakdown.new_withhold_percent || 0;
                    totalPercent = withholdBreakdown.total_withhold_percent || existingPercent;
                }

                const remainingPercent = Math.max(0, maxWithhold - totalPercent);

                // Update progress bars (scale to 100% based on 20% max)
                const existingWidth = (existingPercent / maxWithhold) * 100;
                const newWidth = (newPercent / maxWithhold) * 100;
                const remainingWidth = (remainingPercent / maxWithhold) * 100;

                document.getElementById('existing-bar').style.width = existingWidth + '%';
                document.getElementById('new-bar').style.width = newWidth + '%';
                document.getElementById('remaining-bar').style.width = remainingWidth + '%';

                // Update labels
                document.getElementById('existing-percent').textContent = existingPercent.toFixed(1);
                document.getElementById('new-percent').textContent = newPercent.toFixed(1);
                document.getElementById('remaining-percent').textContent = remainingPercent.toFixed(1);
                document.getElementById('total-withhold-percent').textContent = totalPercent.toFixed(1) + '% of ' + maxWithhold + '% max';

                // Update table
                document.getElementById('existing-daily').textContent = formatCurrency(capacity.existing_daily_payment || 0);
                document.getElementById('existing-withhold').textContent = existingPercent.toFixed(1) + '%';

                if (withholdBreakdown) {
                    document.getElementById('new-daily').textContent = formatCurrency(withholdBreakdown.new_daily_payment || 0);
                    document.getElementById('new-withhold').textContent = newPercent.toFixed(1) + '%';
                    document.getElementById('total-daily').textContent = formatCurrency(withholdBreakdown.total_daily_payment || 0);
                    document.getElementById('total-withhold').textContent = totalPercent.toFixed(1) + '%';
                    document.getElementById('remaining-daily').textContent = formatCurrency(withholdBreakdown.remaining_capacity_after || 0);
                    document.getElementById('remaining-withhold').textContent = remainingPercent.toFixed(1) + '%';
                }
            }

            function displayMathBreakdown(math) {
                const section = document.getElementById('math-section');
                section.classList.remove('hidden');

                // Step 1
                if (math.step_1_revenue) {
                    document.getElementById('math-monthly-revenue').textContent = formatCurrency(math.step_1_revenue.monthly_true_revenue);
                    document.getElementById('math-business-days').textContent = math.step_1_revenue.business_days_per_month;
                    document.getElementById('math-daily-revenue').textContent = formatCurrency(math.step_1_revenue.daily_true_revenue);
                }

                // Step 2
                if (math.step_2_capacity) {
                    document.getElementById('math-max-withhold').textContent = math.step_2_capacity.max_withhold_percent + '%';
                    document.getElementById('math-max-daily').textContent = formatCurrency(math.step_2_capacity.max_daily_payment);
                    document.getElementById('math-existing-daily').textContent = formatCurrency(math.step_2_capacity.existing_daily_payment);
                    document.getElementById('math-remaining-capacity').textContent = formatCurrency(math.step_2_capacity.remaining_daily_capacity) + '/day';
                }

                // Step 3
                if (math.step_3_max_funding) {
                    document.getElementById('math-remaining-daily').textContent = formatCurrency(math.step_3_max_funding.remaining_daily_capacity);
                    document.getElementById('math-term-days').textContent = math.step_3_max_funding.term_business_days;
                    document.getElementById('math-factor-rate').textContent = math.step_3_max_funding.factor_rate;
                    document.getElementById('math-max-payback').textContent = formatCurrency(math.step_3_max_funding.max_payback);
                    document.getElementById('math-max-funding').textContent = formatCurrency(math.step_3_max_funding.max_funding);
                }

                // Step 4
                if (math.step_4_approved) {
                    document.getElementById('math-requested').textContent = formatCurrency(math.step_4_approved.requested_amount);
                    document.getElementById('math-capacity-max').textContent = formatCurrency(math.step_4_approved.max_by_capacity);
                    document.getElementById('math-approval-percent').textContent = math.step_4_approved.approval_percentage + '%';
                    document.getElementById('math-approval-max').textContent = formatCurrency(math.step_4_approved.max_by_approval);
                    document.getElementById('math-approved').textContent = formatCurrency(math.step_4_approved.approved_amount);
                }

                // Step 5
                if (math.step_5_final) {
                    document.getElementById('math-final-approved').textContent = formatCurrency(math.step_5_final.approved_amount);
                    document.getElementById('math-final-factor').textContent = math.step_5_final.factor_rate;
                    document.getElementById('math-final-payback').textContent = formatCurrency(math.step_5_final.payback_amount);
                    document.getElementById('math-final-days').textContent = math.step_5_final.term_business_days;
                    document.getElementById('math-final-daily').textContent = formatCurrency(math.step_5_final.daily_payment);
                }
            }

            function displayAdjustments(adjustments) {
                const section = document.getElementById('adjustments-section');
                const list = document.getElementById('adjustments-list');
                list.innerHTML = '';

                let hasAdjustments = false;

                // Factor rate adjustments
                if (adjustments.factor_rate && adjustments.factor_rate.length > 0) {
                    adjustments.factor_rate.forEach(adj => {
                        hasAdjustments = true;
                        const div = document.createElement('div');
                        div.className = 'flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded';
                        div.innerHTML = `
                            <span class="text-sm text-gray-600 dark:text-gray-300">${adj.description || adj.reason}</span>
                            <span class="text-sm font-mono font-semibold ${adj.adjustment > 0 ? 'text-red-600' : 'text-green-600'}">
                                ${adj.adjustment > 0 ? '+' : ''}${(adj.adjustment * 100).toFixed(2)}%
                            </span>
                        `;
                        list.appendChild(div);
                    });
                }

                if (hasAdjustments) {
                    section.classList.remove('hidden');
                }
            }

            function displayScenarios(result) {
                if (!result.success) {
                    showToast('Error generating scenarios', true);
                    return;
                }

                const section = document.getElementById('scenarios-section');
                section.classList.remove('hidden');

                const grid = document.getElementById('scenarios-grid');
                grid.innerHTML = '';

                const scenarios = result.data?.results || {};
                const colors = {
                    'conservative_short': 'green',
                    'standard_medium': 'blue',
                    'aggressive_long': 'purple'
                };
                const labels = {
                    'conservative_short': 'Conservative (Short)',
                    'standard_medium': 'Standard (Medium)',
                    'aggressive_long': 'Aggressive (Long)'
                };

                Object.entries(scenarios).forEach(([key, scenario]) => {
                    if (!scenario.offer) return;

                    const color = colors[key] || 'gray';
                    const label = labels[key] || key;
                    const offer = scenario.offer;

                    const card = document.createElement('div');
                    card.className = `bg-${color}-50 dark:bg-${color}-900/20 border border-${color}-200 dark:border-${color}-800 rounded-lg p-4`;
                    card.innerHTML = `
                        <h5 class="font-semibold text-${color}-700 dark:text-${color}-400 mb-3">${label}</h5>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Funding:</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">${formatCurrency(offer.funding_amount)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Factor:</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">${offer.factor_rate.toFixed(2)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Term:</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">${offer.term_months} mo</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Daily:</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">${formatCurrency(offer.daily_payment)}</span>
                            </div>
                            <div class="flex justify-between border-t border-${color}-200 dark:border-${color}-700 pt-2 mt-2">
                                <span class="text-gray-600 dark:text-gray-400">Cost:</span>
                                <span class="font-semibold text-red-600 dark:text-red-400">${formatCurrency(offer.cost_of_capital)} (${offer.cost_percentage.toFixed(1)}%)</span>
                            </div>
                        </div>
                    `;
                    grid.appendChild(card);
                });

                // Show recommendation if available
                if (result.data?.recommendation) {
                    const recDiv = document.getElementById('scenario-recommendation');
                    recDiv.classList.remove('hidden');
                    recDiv.querySelector('p').textContent = result.data.recommendation;
                }
            }

            function formatCurrency(amount) {
                return '$' + (amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function getPositionText(pos) {
                const positions = { 1: '1st', 2: '2nd', 3: '3rd', 4: '4th' };
                return positions[pos] || pos + 'th';
            }

            function formatDeclineReason(reason) {
                const reasons = {
                    'at_capacity': 'At Withhold Capacity',
                    'too_many_positions': 'Too Many Positions',
                    'low_risk_score': 'Risk Score Too Low',
                    'below_minimum': 'Below Minimum Funding',
                    'high_volatility': 'Revenue Too Volatile'
                };
                return reasons[reason] || reason;
            }

            function showToast(message, isError = false) {
                const toast = document.getElementById('toast');
                const toastMessage = document.getElementById('toast-message');
                toastMessage.textContent = message;
                toast.querySelector('div').className = isError
                    ? 'bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center'
                    : 'bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center';
                toast.classList.remove('hidden');
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 3000);
            }
        });
    </script>
</x-app-layout>
