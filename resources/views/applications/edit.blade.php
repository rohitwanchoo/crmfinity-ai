<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center">
            <a href="{{ route('applications.show', $application) }}" class="mr-4 text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Application #{{ $application->id }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('applications.update', $application) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Business Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold">Business Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Legal Business Name *</label>
                                <input type="text" name="business_name" value="{{ old('business_name', $application->business_name) }}"
                                       class="form-input w-full @error('business_name') border-red-500 @enderror" required>
                                @error('business_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">DBA (if different)</label>
                                <input type="text" name="dba_name" value="{{ old('dba_name', $application->dba_name) }}"
                                       class="form-input w-full">
                            </div>
                            <div>
                                <label class="form-label">EIN / Tax ID</label>
                                <input type="text" name="ein" value="{{ old('ein', $application->ein) }}"
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
                                    @foreach(['Restaurant', 'Retail', 'Healthcare', 'Construction', 'Transportation', 'Professional Services', 'Manufacturing', 'Wholesale', 'Auto Repair', 'Other'] as $ind)
                                        <option value="{{ $ind }}" {{ old('industry', $application->industry) == $ind ? 'selected' : '' }}>{{ $ind }}</option>
                                    @endforeach
                                </select>
                                @error('industry')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Business Phone *</label>
                                <input type="tel" name="business_phone" value="{{ old('business_phone', $application->business_phone) }}"
                                       class="form-input w-full @error('business_phone') border-red-500 @enderror" required>
                                @error('business_phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Business Email *</label>
                                <input type="email" name="business_email" value="{{ old('business_email', $application->business_email) }}"
                                       class="form-input w-full @error('business_email') border-red-500 @enderror" required>
                                @error('business_email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Business Start Date *</label>
                                <input type="date" name="business_start_date" value="{{ old('business_start_date', $application->business_start_date) }}"
                                       class="form-input w-full @error('business_start_date') border-red-500 @enderror" required>
                                @error('business_start_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Monthly Revenue *</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                    <input type="number" name="monthly_revenue" value="{{ old('monthly_revenue', $application->monthly_revenue) }}"
                                           step="0.01" min="0"
                                           class="form-input w-full pl-7 @error('monthly_revenue') border-red-500 @enderror" required>
                                </div>
                                @error('monthly_revenue')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Business Address *</label>
                                <input type="text" name="business_address" value="{{ old('business_address', $application->business_address) }}"
                                       class="form-input w-full @error('business_address') border-red-500 @enderror" required>
                                @error('business_address')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">City *</label>
                                <input type="text" name="business_city" value="{{ old('business_city', $application->business_city) }}"
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
                                        <option value="{{ $state }}" {{ old('business_state', $application->business_state) == $state ? 'selected' : '' }}>{{ $state }}</option>
                                    @endforeach
                                </select>
                                @error('business_state')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">ZIP Code *</label>
                                <input type="text" name="business_zip" value="{{ old('business_zip', $application->business_zip) }}"
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
                                <input type="text" name="owner_first_name" value="{{ old('owner_first_name', $application->owner_first_name) }}"
                                       class="form-input w-full @error('owner_first_name') border-red-500 @enderror" required>
                                @error('owner_first_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="owner_last_name" value="{{ old('owner_last_name', $application->owner_last_name) }}"
                                       class="form-input w-full @error('owner_last_name') border-red-500 @enderror" required>
                                @error('owner_last_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Email *</label>
                                <input type="email" name="owner_email" value="{{ old('owner_email', $application->owner_email) }}"
                                       class="form-input w-full @error('owner_email') border-red-500 @enderror" required>
                                @error('owner_email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Phone *</label>
                                <input type="tel" name="owner_phone" value="{{ old('owner_phone', $application->owner_phone) }}"
                                       class="form-input w-full @error('owner_phone') border-red-500 @enderror" required>
                                @error('owner_phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" name="owner_dob" value="{{ old('owner_dob', $application->owner_dob) }}"
                                       class="form-input w-full @error('owner_dob') border-red-500 @enderror" required>
                                @error('owner_dob')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Ownership Percentage *</label>
                                <div class="relative">
                                    <input type="number" name="ownership_percentage" value="{{ old('ownership_percentage', $application->ownership_percentage) }}"
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
                                <input type="text" name="owner_address" value="{{ old('owner_address', $application->owner_address) }}"
                                       class="form-input w-full @error('owner_address') border-red-500 @enderror" required>
                                @error('owner_address')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">City *</label>
                                <input type="text" name="owner_city" value="{{ old('owner_city', $application->owner_city) }}"
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
                                        <option value="{{ $state }}" {{ old('owner_state', $application->owner_state) == $state ? 'selected' : '' }}>{{ $state }}</option>
                                    @endforeach
                                </select>
                                @error('owner_state')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">ZIP Code *</label>
                                <input type="text" name="owner_zip" value="{{ old('owner_zip', $application->owner_zip) }}"
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
                                    <input type="number" name="requested_amount" value="{{ old('requested_amount', $application->requested_amount) }}"
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
                                    @foreach(['Working Capital', 'Equipment Purchase', 'Inventory', 'Expansion', 'Marketing', 'Payroll', 'Debt Consolidation', 'Other'] as $use)
                                        <option value="{{ $use }}" {{ old('use_of_funds', $application->use_of_funds) == $use ? 'selected' : '' }}>{{ $use }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-4">
                    <a href="{{ route('applications.show', $application) }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Application</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
