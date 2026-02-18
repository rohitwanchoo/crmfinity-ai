<x-app-layout>
    @php
        $isEdit = isset($guideline);
        $v = fn($field, $default = null) => old($field, $isEdit ? ($guideline->$field ?? $default) : $default);
        $inp = 'block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700/60 dark:text-gray-100 shadow-sm focus:border-purple-500 focus:ring-purple-500 text-sm py-2';
        $sel = 'block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700/60 dark:text-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 text-sm py-2';
        $lbl = 'block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wide';
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $isEdit ? 'Edit: '.$guideline->lender_name : 'New Lender' }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $isEdit ? $guideline->lender_id : 'Add a new MCA lender' }}
                </p>
            </div>
            <a href="{{ route('bankstatement.lenders') }}"
               class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="-mx-6 pb-8 px-4 sm:px-6">

        @if ($errors->any())
        <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl px-5 py-3">
            <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-400 space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        @if($isEdit)
            <form action="{{ route('bankstatement.lenders.update', $guideline->lender_id) }}" method="POST">
            @csrf @method('PUT')
        @else
            <form action="{{ route('bankstatement.lenders.store') }}" method="POST">
            @csrf
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

            {{-- ── Card header ── --}}
            <div class="flex items-center justify-between px-7 py-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Lender Guidelines</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Fill in the criteria and terms for this lender</p>
                </div>
                <div class="flex gap-3">
                    @if($isEdit && isset($guideline->lender_id))
                    <a href="{{ route('bankstatement.lender-detail', $guideline->lender_id) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                        View Patterns
                    </a>
                    @endif
                    <a href="{{ route('bankstatement.lenders') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-6 py-2 bg-purple-600 rounded-lg text-sm font-semibold text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors shadow-sm">
                        {{ $isEdit ? 'Save Changes' : 'Create Lender' }}
                    </button>
                </div>
            </div>

            <div class="p-7 space-y-8">

                {{-- ── Quick fill ── --}}
                @if(!$isEdit)
                <div>
                    <label for="lender_select" class="{{ $lbl }}">Quick-fill from known lender</label>
                    <select id="lender_select" class="{{ $sel }} max-w-sm">
                        <option value="">Select to pre-fill…</option>
                        <optgroup label="── MCA Lenders ──">
                            @foreach($knownLenders as $id => $name)
                                <option value="{{ $id }}" data-name="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="── Debt Collectors ──">
                            @foreach($knownDebtCollectors as $id => $name)
                                <option value="{{ $id }}" data-name="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </optgroup>
                        <option value="custom">+ Enter custom</option>
                    </select>
                </div>
                @endif

                {{-- ── IDENTITY ── --}}
                <div class="rounded-xl bg-gray-50 dark:bg-gray-700/30 border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-4">Identity</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-5">
                        <div>
                            <label for="lender_id" class="{{ $lbl }}">Lender ID <span class="text-red-500 normal-case">*</span></label>
                            <input type="text" name="lender_id" id="lender_id" required
                                {{ $isEdit ? 'readonly' : '' }}
                                class="{{ $inp }} {{ $isEdit ? 'bg-gray-100 dark:bg-gray-600 cursor-not-allowed opacity-70' : '' }}"
                                placeholder="e.g. ondeck" value="{{ $v('lender_id') }}">
                            @error('lender_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="col-span-2">
                            <label for="lender_name" class="{{ $lbl }}">Lender Name <span class="text-red-500 normal-case">*</span></label>
                            <input type="text" name="lender_name" id="lender_name" required
                                class="{{ $inp }}" placeholder="e.g. OnDeck Capital" value="{{ $v('lender_name') }}">
                            @error('lender_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="status" class="{{ $lbl }}">Status</label>
                            @php $curStatus = $v('status', 'ACTIVE'); @endphp
                            <select name="status" id="status" class="{{ $sel }}">
                                <option value="ACTIVE"   {{ $curStatus === 'ACTIVE'   ? 'selected' : '' }}>ACTIVE</option>
                                <option value="INACTIVE" {{ $curStatus === 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label for="description_pattern" class="{{ $lbl }}">Transaction Pattern <span class="text-gray-400 normal-case font-normal">(optional)</span></label>
                            <input type="text" name="description_pattern" id="description_pattern"
                                class="{{ $inp }}" placeholder="e.g. ACH DEBIT - ONDECK" value="{{ old('description_pattern') }}">
                        </div>
                        <div class="flex items-center pt-5">
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="hidden" name="white_label" value="0">
                                <input type="checkbox" name="white_label" id="white_label" value="1"
                                    class="w-4 h-4 rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500"
                                    {{ $v('white_label') ? 'checked' : '' }}>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">White Label</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ── FINANCIAL + FUNDING TERMS ── --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                    <div class="lg:col-span-2 rounded-xl bg-blue-50/50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/40 p-5">
                        <p class="text-xs font-bold uppercase tracking-widest text-blue-500 dark:text-blue-400 mb-4">Financial Criteria</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-5">
                            <div>
                                <label class="{{ $lbl }}">Min Credit Score</label>
                                <input type="number" name="min_credit_score" class="{{ $inp }}" min="0" max="900" placeholder="550" value="{{ $v('min_credit_score') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Min Time in Biz (mo)</label>
                                <input type="number" name="min_time_in_business" class="{{ $inp }}" min="0" placeholder="6" value="{{ $v('min_time_in_business') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Min Monthly Deposits</label>
                                <input type="number" name="min_monthly_deposits" class="{{ $inp }}" min="0" step="1" placeholder="$10,000" value="{{ $v('min_monthly_deposits') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Min Loan Amount</label>
                                <input type="number" name="min_loan_amount" class="{{ $inp }}" min="0" step="1" placeholder="$5,000" value="{{ $v('min_loan_amount') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Max Loan Amount</label>
                                <input type="number" name="max_loan_amount" class="{{ $inp }}" min="0" step="1" placeholder="$500,000" value="{{ $v('max_loan_amount') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Min Avg Daily Bal.</label>
                                <input type="number" name="min_avg_daily_balance" class="{{ $inp }}" min="0" step="1" placeholder="$1,000" value="{{ $v('min_avg_daily_balance') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Max Negative Days</label>
                                <input type="number" name="max_negative_days" class="{{ $inp }}" min="0" placeholder="5" value="{{ $v('max_negative_days') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Max NSFs / Month</label>
                                <input type="number" name="max_nsfs" class="{{ $inp }}" min="0" placeholder="3" value="{{ $v('max_nsfs') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Max Open Positions</label>
                                <input type="number" name="max_positions" class="{{ $inp }}" min="0" placeholder="3" value="{{ $v('max_positions') }}">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-purple-50/50 dark:bg-purple-900/10 border border-purple-100 dark:border-purple-900/40 p-5">
                        <p class="text-xs font-bold uppercase tracking-widest text-purple-500 dark:text-purple-400 mb-4">Funding Terms</p>
                        <div class="space-y-4">
                            <div>
                                <label class="{{ $lbl }}">Product Type</label>
                                <input type="text" name="product_type" class="{{ $inp }}" placeholder="MCA, Term Loan, LOC…" value="{{ $v('product_type') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Factor Rate</label>
                                <input type="text" name="factor_rate" class="{{ $inp }}" placeholder="1.18 – 1.49" value="{{ $v('factor_rate') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Max Term</label>
                                <input type="text" name="max_term" class="{{ $inp }}" placeholder="12 months" value="{{ $v('max_term') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Funding Speed</label>
                                <input type="text" name="funding_speed" class="{{ $inp }}" placeholder="SAME DAY, 24 HRS" value="{{ $v('funding_speed') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Payment Frequency</label>
                                @php $curFreq = $v('payment_frequency'); @endphp
                                <select name="payment_frequency" class="{{ $sel }}">
                                    <option value="" {{ !$curFreq ? 'selected' : '' }}>—</option>
                                    <option value="DAILY"        {{ $curFreq === 'DAILY'        ? 'selected' : '' }}>Daily</option>
                                    <option value="WEEKLY"       {{ $curFreq === 'WEEKLY'       ? 'selected' : '' }}>Weekly</option>
                                    <option value="DAILY/WEEKLY" {{ $curFreq === 'DAILY/WEEKLY' ? 'selected' : '' }}>Daily / Weekly</option>
                                </select>
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Bonus Available</label>
                                @php $curBonus = $v('bonus_available'); $curBonus = $curBonus === null ? '' : ($curBonus ? '1' : '0'); @endphp
                                <select name="bonus_available" class="{{ $sel }}">
                                    <option value="" {{ $curBonus === '' ? 'selected' : '' }}>—</option>
                                    <option value="1" {{ $curBonus === '1' ? 'selected' : '' }}>YES</option>
                                    <option value="0" {{ $curBonus === '0' ? 'selected' : '' }}>NO</option>
                                </select>
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Bonus Details</label>
                                <input type="text" name="bonus_details" class="{{ $inp }}" placeholder="e.g. 2 pts on funded deals" value="{{ $v('bonus_details') }}">
                            </div>
                        </div>
                    </div>

                </div>

                {{-- ── ELIGIBILITY + SPECIAL ── --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                    <div class="rounded-xl bg-green-50/50 dark:bg-green-900/10 border border-green-100 dark:border-green-900/40 p-5">
                        <p class="text-xs font-bold uppercase tracking-widest text-green-600 dark:text-green-400 mb-4">Eligibility</p>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach([
                                ['sole_proprietors',    'Sole Proprietors'],
                                ['home_based_business', 'Home-Based Business'],
                                ['consolidation_deals', 'Consolidation Deals'],
                                ['non_profits',         'Non-Profits'],
                            ] as [$field, $label])
                            @php $cur = $v($field); @endphp
                            <div>
                                <label class="{{ $lbl }}">{{ $label }}</label>
                                <select name="{{ $field }}" class="{{ $sel }}">
                                    <option value="" {{ !$cur ? 'selected' : '' }}>—</option>
                                    <option value="YES"   {{ $cur === 'YES'   ? 'selected' : '' }}>YES</option>
                                    <option value="NO"    {{ $cur === 'NO'    ? 'selected' : '' }}>NO</option>
                                    <option value="MAYBE" {{ $cur === 'MAYBE' ? 'selected' : '' }}>MAYBE</option>
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-xl bg-orange-50/50 dark:bg-orange-900/10 border border-orange-100 dark:border-orange-900/40 p-5">
                        <p class="text-xs font-bold uppercase tracking-widest text-orange-600 dark:text-orange-400 mb-4">Special Circumstances</p>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach([
                                ['bankruptcy',       'Bankruptcy'],
                                ['tax_lien',         'Tax Lien'],
                                ['prior_default',    'Prior Default'],
                                ['criminal_history', 'Criminal History'],
                            ] as [$field, $label])
                            @php $cur = $v($field); @endphp
                            <div>
                                <label class="{{ $lbl }}">{{ $label }}</label>
                                <select name="{{ $field }}" class="{{ $sel }}">
                                    <option value="" {{ !$cur ? 'selected' : '' }}>—</option>
                                    <option value="YES"   {{ $cur === 'YES'   ? 'selected' : '' }}>YES</option>
                                    <option value="NO"    {{ $cur === 'NO'    ? 'selected' : '' }}>NO</option>
                                    <option value="MAYBE" {{ $cur === 'MAYBE' ? 'selected' : '' }}>MAYBE</option>
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                {{-- ── GEOGRAPHIC + NOTES ── --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                    <div class="rounded-xl bg-red-50/50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/40 p-5">
                        <p class="text-xs font-bold uppercase tracking-widest text-red-500 dark:text-red-400 mb-4">Geographic Restrictions</p>
                        <div class="space-y-4">
                            <div>
                                <label class="{{ $lbl }}">Restricted States</label>
                                <input type="text" name="restricted_states" class="{{ $inp }}"
                                    placeholder="CA, NY, FL  (comma-separated)"
                                    value="{{ old('restricted_states', $restrictedStatesStr ?? '') }}">
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Excluded Industries</label>
                                <textarea name="excluded_industries" rows="4" class="{{ $inp }}"
                                    placeholder="cannabis, adult_entertainment  (comma-separated)">{{ old('excluded_industries', $excludedIndustriesStr ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 rounded-xl bg-gray-50 dark:bg-gray-700/30 border border-gray-100 dark:border-gray-700 p-5">
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-4">Notes</p>
                        <textarea name="notes" rows="7" class="{{ $inp }} h-[calc(100%-2rem)]"
                            placeholder="Additional guidelines, notes, or comments about this lender…">{{ $v('notes') }}</textarea>
                    </div>

                </div>

            </div>{{-- /p-7 --}}

            {{-- ── Footer actions ── --}}
            <div class="flex items-center justify-end gap-3 px-7 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40">
                <a href="{{ route('bankstatement.lenders') }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center px-7 py-2.5 bg-purple-600 rounded-lg text-sm font-semibold text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors shadow-sm">
                    {{ $isEdit ? 'Save Changes' : 'Create Lender' }}
                </button>
            </div>

        </div>{{-- /card --}}

        </form>
    </div>

    @if(!$isEdit)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const lenderSelect    = document.getElementById('lender_select');
            const lenderIdInput   = document.getElementById('lender_id');
            const lenderNameInput = document.getElementById('lender_name');
            lenderSelect.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                const value = opt.value;
                if (value && value !== 'custom') {
                    lenderIdInput.value      = value;
                    lenderNameInput.value    = opt.dataset.name;
                    lenderIdInput.readOnly   = true;
                    lenderNameInput.readOnly = true;
                } else {
                    lenderIdInput.value      = '';
                    lenderNameInput.value    = '';
                    lenderIdInput.readOnly   = false;
                    lenderNameInput.readOnly = false;
                    if (value === 'custom') lenderIdInput.focus();
                }
            });
        });
    </script>
    @endif
</x-app-layout>
