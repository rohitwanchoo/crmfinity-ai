<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center">
            <a href="{{ route('applications.index') }}" class="mr-4 text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                New MCA Application
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('applications.store') }}" class="space-y-6">
                @csrf

                <!-- Business Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold">Business Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Legal Business Name *</label>
                                <input type="text" name="business_name" value="{{ old('business_name') }}"
                                       class="form-input w-full @error('business_name') border-red-500 @enderror" required>
                                @error('business_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">DBA (if different)</label>
                                <input type="text" name="dba_name" value="{{ old('dba_name') }}"
                                       class="form-input w-full">
                            </div>
                            <div>
                                <label class="form-label">EIN / Tax ID</label>
                                <input type="text" name="ein" value="{{ old('ein') }}"
                                       placeholder="XX-XXXXXXX"
                                       class="form-input w-full @error('ein') border-red-500 @enderror">
                                @error('ein')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Industry *</label>
                                <select name="industry" class="form-select w-full @error('industry') border-red-500 @enderror" required>
                                    <option value="">Select Industry</option>
                                    <option value="Restaurant" {{ old('industry') == 'Restaurant' ? 'selected' : '' }}>Restaurant</option>
                                    <option value="Retail" {{ old('industry') == 'Retail' ? 'selected' : '' }}>Retail</option>
                                    <option value="Healthcare" {{ old('industry') == 'Healthcare' ? 'selected' : '' }}>Healthcare</option>
                                    <option value="Construction" {{ old('industry') == 'Construction' ? 'selected' : '' }}>Construction</option>
                                    <option value="Transportation" {{ old('industry') == 'Transportation' ? 'selected' : '' }}>Transportation</option>
                                    <option value="Professional Services" {{ old('industry') == 'Professional Services' ? 'selected' : '' }}>Professional Services</option>
                                    <option value="Manufacturing" {{ old('industry') == 'Manufacturing' ? 'selected' : '' }}>Manufacturing</option>
                                    <option value="Wholesale" {{ old('industry') == 'Wholesale' ? 'selected' : '' }}>Wholesale</option>
                                    <option value="Auto Repair" {{ old('industry') == 'Auto Repair' ? 'selected' : '' }}>Auto Repair</option>
                                    <option value="Other" {{ old('industry') == 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('industry')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Business Phone *</label>
                                <input type="tel" name="business_phone" value="{{ old('business_phone') }}"
                                       class="form-input w-full @error('business_phone') border-red-500 @enderror" required>
                                @error('business_phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Business Email *</label>
                                <input type="email" name="business_email" value="{{ old('business_email') }}"
                                       class="form-input w-full @error('business_email') border-red-500 @enderror" required>
                                @error('business_email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Business Start Date *</label>
                                <input type="date" name="business_start_date" value="{{ old('business_start_date') }}"
                                       class="form-input w-full @error('business_start_date') border-red-500 @enderror" required>
                                @error('business_start_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Monthly Revenue *</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                    <input type="number" name="monthly_revenue" value="{{ old('monthly_revenue') }}"
                                           step="0.01" min="0"
                                           class="form-input w-full pl-7 @error('monthly_revenue') border-red-500 @enderror" required>
                                </div>
                                @error('monthly_revenue')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Business Address *</label>
                                <input type="text" name="business_address" value="{{ old('business_address') }}"
                                       class="form-input w-full @error('business_address') border-red-500 @enderror" required>
                                @error('business_address')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">City *</label>
                                <input type="text" name="business_city" value="{{ old('business_city') }}"
                                       class="form-input w-full @error('business_city') border-red-500 @enderror" required>
                                @error('business_city')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">State *</label>
                                <select name="business_state" class="form-select w-full @error('business_state') border-red-500 @enderror" required>
                                    <option value="">Select State</option>
                                    @foreach(['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'] as $state)
                                        <option value="{{ $state }}" {{ old('business_state') == $state ? 'selected' : '' }}>{{ $state }}</option>
                                    @endforeach
                                </select>
                                @error('business_state')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">ZIP Code *</label>
                                <input type="text" name="business_zip" value="{{ old('business_zip') }}"
                                       class="form-input w-full @error('business_zip') border-red-500 @enderror" required>
                                @error('business_zip')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Owner Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold">Owner Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">First Name *</label>
                                <input type="text" name="owner_first_name" value="{{ old('owner_first_name') }}"
                                       class="form-input w-full @error('owner_first_name') border-red-500 @enderror" required>
                                @error('owner_first_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="owner_last_name" value="{{ old('owner_last_name') }}"
                                       class="form-input w-full @error('owner_last_name') border-red-500 @enderror" required>
                                @error('owner_last_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Email *</label>
                                <input type="email" name="owner_email" value="{{ old('owner_email') }}"
                                       class="form-input w-full @error('owner_email') border-red-500 @enderror" required>
                                @error('owner_email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Phone *</label>
                                <input type="tel" name="owner_phone" value="{{ old('owner_phone') }}"
                                       class="form-input w-full @error('owner_phone') border-red-500 @enderror" required>
                                @error('owner_phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">SSN Last 4 Digits</label>
                                <input type="text" name="owner_ssn_last4" value="{{ old('owner_ssn_last4') }}"
                                       placeholder="XXXX" maxlength="4"
                                       class="form-input w-full @error('owner_ssn_last4') border-red-500 @enderror">
                                @error('owner_ssn_last4')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" name="owner_dob" value="{{ old('owner_dob') }}"
                                       class="form-input w-full @error('owner_dob') border-red-500 @enderror" required>
                                @error('owner_dob')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Ownership Percentage *</label>
                                <div class="relative">
                                    <input type="number" name="ownership_percentage" value="{{ old('ownership_percentage', 100) }}"
                                           min="0" max="100" step="0.01"
                                           class="form-input w-full pr-8 @error('ownership_percentage') border-red-500 @enderror" required>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">%</span>
                                </div>
                                @error('ownership_percentage')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Home Address *</label>
                                <input type="text" name="owner_address" value="{{ old('owner_address') }}"
                                       class="form-input w-full @error('owner_address') border-red-500 @enderror" required>
                                @error('owner_address')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">City *</label>
                                <input type="text" name="owner_city" value="{{ old('owner_city') }}"
                                       class="form-input w-full @error('owner_city') border-red-500 @enderror" required>
                                @error('owner_city')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">State *</label>
                                <select name="owner_state" class="form-select w-full @error('owner_state') border-red-500 @enderror" required>
                                    <option value="">Select State</option>
                                    @foreach(['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'] as $state)
                                        <option value="{{ $state }}" {{ old('owner_state') == $state ? 'selected' : '' }}>{{ $state }}</option>
                                    @endforeach
                                </select>
                                @error('owner_state')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">ZIP Code *</label>
                                <input type="text" name="owner_zip" value="{{ old('owner_zip') }}"
                                       class="form-input w-full @error('owner_zip') border-red-500 @enderror" required>
                                @error('owner_zip')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Funding Request -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold">Funding Request</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Requested Amount *</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                    <input type="number" name="requested_amount" value="{{ old('requested_amount') }}"
                                           step="0.01" min="0"
                                           class="form-input w-full pl-7 @error('requested_amount') border-red-500 @enderror" required>
                                </div>
                                @error('requested_amount')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Use of Funds</label>
                                <select name="use_of_funds" class="form-select w-full">
                                    <option value="">Select Use</option>
                                    <option value="Working Capital" {{ old('use_of_funds') == 'Working Capital' ? 'selected' : '' }}>Working Capital</option>
                                    <option value="Equipment Purchase" {{ old('use_of_funds') == 'Equipment Purchase' ? 'selected' : '' }}>Equipment Purchase</option>
                                    <option value="Inventory" {{ old('use_of_funds') == 'Inventory' ? 'selected' : '' }}>Inventory</option>
                                    <option value="Expansion" {{ old('use_of_funds') == 'Expansion' ? 'selected' : '' }}>Expansion</option>
                                    <option value="Marketing" {{ old('use_of_funds') == 'Marketing' ? 'selected' : '' }}>Marketing</option>
                                    <option value="Payroll" {{ old('use_of_funds') == 'Payroll' ? 'selected' : '' }}>Payroll</option>
                                    <option value="Debt Consolidation" {{ old('use_of_funds') == 'Debt Consolidation' ? 'selected' : '' }}>Debt Consolidation</option>
                                    <option value="Other" {{ old('use_of_funds') == 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-4">
                    <a href="{{ route('applications.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Application</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
