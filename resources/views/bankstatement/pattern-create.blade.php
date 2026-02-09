<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Add Pattern for {{ $lender['name'] }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Add a new transaction pattern</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('bankstatement.lender-detail', $lender['id']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Cancel
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('bankstatement.lenders.pattern.store', $lender['id']) }}" method="POST">
                        @csrf

                        <!-- Lender Info -->
                        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex items-center">
                                <div class="h-12 w-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 dark:text-purple-400 font-semibold text-lg">
                                        {{ strtoupper(substr($lender['name'], 0, 2)) }}
                                    </span>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $lender['name'] }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">ID: {{ $lender['id'] }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Transaction Pattern -->
                        <div class="mb-6">
                            <label for="description_pattern" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Transaction Pattern <span class="text-red-500">*</span>
                            </label>
                            <textarea name="description_pattern" id="description_pattern" rows="3" required autofocus
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                placeholder="e.g., ACH DEBIT - ONDECK CAPITAL">{{ old('description_pattern') }}</textarea>
                            @error('description_pattern')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Enter the transaction description pattern exactly as it appears in bank statements
                            </p>
                        </div>

                        <!-- Info Card -->
                        <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Pattern Normalization</h3>
                                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                                        <p>The system automatically normalizes patterns by:</p>
                                        <ul class="list-disc list-inside mt-1 space-y-1">
                                            <li>Removing dates (e.g., 01/15/2024)</li>
                                            <li>Removing dollar amounts</li>
                                            <li>Removing reference/account numbers (6+ digits)</li>
                                            <li>Cleaning up extra whitespace</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('bankstatement.lender-detail', $lender['id']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition ease-in-out duration-150">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-6 py-3 bg-purple-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-purple-700 active:bg-purple-800 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add Pattern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
