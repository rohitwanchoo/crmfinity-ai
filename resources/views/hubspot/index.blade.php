<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                HubSpot Integration
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Success/Error Messages --}}
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Connection Status Card --}}
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center mb-4">
                                <img src="https://www.hubspot.com/hubfs/HubSpot_Logos/HubSpot-Inversed-Favicon.png" alt="HubSpot" class="w-10 h-10 mr-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">HubSpot</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">CRM Integration</p>
                                </div>
                            </div>

                            @if($connection)
                                {{-- Connected State --}}
                                <div class="mb-4">
                                    <div class="flex items-center text-green-600 dark:text-green-400 mb-2">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="font-medium">Connected</span>
                                    </div>

                                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                        @if($portalInfo)
                                            <p><span class="font-medium">Portal ID:</span> {{ $portalInfo['hub_id'] ?? $connection->hubspot_portal_id }}</p>
                                            <p><span class="font-medium">User:</span> {{ $portalInfo['user'] ?? 'N/A' }}</p>
                                        @else
                                            <p><span class="font-medium">Portal ID:</span> {{ $connection->hubspot_portal_id }}</p>
                                        @endif
                                        <p><span class="font-medium">Connected:</span> {{ $connection->created_at->diffForHumans() }}</p>
                                        @if($connection->last_synced_at)
                                            <p><span class="font-medium">Last Sync:</span> {{ $connection->last_synced_at->diffForHumans() }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <form action="{{ route('hubspot.refresh') }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Refresh Connection
                                        </button>
                                    </form>

                                    <form action="{{ route('hubspot.disconnect') }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to disconnect from HubSpot?');">
                                        @csrf
                                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                            </svg>
                                            Disconnect
                                        </button>
                                    </form>
                                </div>
                            @else
                                {{-- Disconnected State --}}
                                <div class="mb-4">
                                    <div class="flex items-center text-gray-500 dark:text-gray-400 mb-2">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                        </svg>
                                        <span class="font-medium">Not Connected</span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                        Connect your HubSpot account to sync MCA offers and display them in HubSpot CRM cards.
                                    </p>
                                </div>

                                <a href="{{ route('hubspot.connect') }}" class="w-full inline-flex justify-center items-center px-4 py-2 bg-orange-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-600 focus:bg-orange-600 active:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                    </svg>
                                    Connect to HubSpot
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Setup Instructions --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Setup Instructions</h4>

                            <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                                <div class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-bold mr-3">1</span>
                                    <p>Create a HubSpot app at <a href="https://developers.hubspot.com/" target="_blank" class="text-blue-600 hover:underline">developers.hubspot.com</a></p>
                                </div>
                                <div class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-bold mr-3">2</span>
                                    <p>Add your Client ID and Secret to <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">.env</code></p>
                                </div>
                                <div class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-bold mr-3">3</span>
                                    <p>Set redirect URI to: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded text-xs">{{ url('/hubspot/callback') }}</code></p>
                                </div>
                                <div class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-bold mr-3">4</span>
                                    <p>Create a CRM Card with fetch URL: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded text-xs">{{ url('/api/hubspot/crm-card') }}</code></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Synced Offers & Actions --}}
                <div class="lg:col-span-2">
                    @if($connection)
                        {{-- Sync New Offer Card --}}
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                            <div class="p-6">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Sync MCA Offer to HubSpot</h4>

                                <form id="sync-offer-form" class="space-y-4">
                                    @csrf
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select MCA Offer</label>
                                        <select name="offer_id" id="offer_id" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm" required>
                                            <option value="">-- Select an offer --</option>
                                            @php
                                                $offers = \App\Models\McaOffer::orderBy('created_at', 'desc')->limit(50)->get();
                                            @endphp
                                            @foreach($offers as $offer)
                                                <option value="{{ $offer->offer_id }}">
                                                    {{ $offer->offer_name ?? 'Offer ' . substr($offer->offer_id, 0, 8) }}
                                                    - ${{ number_format($offer->advance_amount, 0) }}
                                                    ({{ $offer->created_at->format('M d, Y') }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">HubSpot Contact (Optional)</label>
                                            <select name="contact_id" id="contact_id" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">
                                                <option value="">-- Select contact --</option>
                                            </select>
                                            <button type="button" onclick="loadContacts()" class="mt-1 text-xs text-blue-600 hover:underline">Load contacts from HubSpot</button>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">HubSpot Company (Optional)</label>
                                            <select name="company_id" id="company_id" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">
                                                <option value="">-- Select company --</option>
                                            </select>
                                            <button type="button" onclick="loadCompanies()" class="mt-1 text-xs text-blue-600 hover:underline">Load companies from HubSpot</button>
                                        </div>
                                    </div>

                                    <button type="submit" id="sync-btn" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                        </svg>
                                        Sync to HubSpot
                                    </button>
                                </form>

                                <div id="sync-result" class="mt-4 hidden"></div>
                            </div>
                        </div>

                        {{-- Recently Synced Offers --}}
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Recently Synced Offers</h4>

                                @if($syncedOffers->count() > 0)
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Offer</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">HubSpot Deal</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Last Synced</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($syncedOffers as $syncedOffer)
                                                    <tr>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                            @if($syncedOffer->mcaOffer)
                                                                {{ $syncedOffer->mcaOffer->offer_name ?? 'Offer ' . substr($syncedOffer->mca_offer_id, 0, 8) }}
                                                            @else
                                                                {{ substr($syncedOffer->mca_offer_id, 0, 8) }}
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            @if($syncedOffer->hubspot_deal_id)
                                                                <a href="https://app.hubspot.com/contacts/{{ $connection->hubspot_portal_id }}/deal/{{ $syncedOffer->hubspot_deal_id }}" target="_blank" class="text-blue-600 hover:underline">
                                                                    {{ $syncedOffer->hubspot_deal_id }}
                                                                </a>
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-sm">
                                                            @if($syncedOffer->sync_status === 'synced')
                                                                <span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">Synced</span>
                                                            @elseif($syncedOffer->sync_status === 'pending')
                                                                <span class="px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-full">Pending</span>
                                                            @else
                                                                <span class="px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full" title="{{ $syncedOffer->sync_error }}">Failed</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            {{ $syncedOffer->last_synced_at->diffForHumans() }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-gray-500 dark:text-gray-400 text-center py-8">No offers synced yet. Use the form above to sync your first offer.</p>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-12 text-center">
                                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                </svg>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Connect to HubSpot</h3>
                                <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-4">
                                    Connect your HubSpot account to start syncing MCA offers and displaying them in your HubSpot CRM.
                                </p>
                                <a href="{{ route('hubspot.connect') }}" class="inline-flex items-center px-4 py-2 bg-orange-500 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-orange-600">
                                    Connect Now
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($connection)
    <script>
        // Load contacts from HubSpot
        async function loadContacts() {
            const select = document.getElementById('contact_id');
            select.innerHTML = '<option value="">Loading...</option>';

            try {
                const response = await fetch('{{ route("hubspot.contacts") }}');
                const data = await response.json();

                if (data.success) {
                    select.innerHTML = '<option value="">-- Select contact --</option>';
                    data.data.forEach(contact => {
                        const option = document.createElement('option');
                        option.value = contact.id;
                        option.textContent = `${contact.name} (${contact.email})`;
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">Failed to load</option>';
                }
            } catch (error) {
                select.innerHTML = '<option value="">Error loading</option>';
            }
        }

        // Load companies from HubSpot
        async function loadCompanies() {
            const select = document.getElementById('company_id');
            select.innerHTML = '<option value="">Loading...</option>';

            try {
                const response = await fetch('{{ route("hubspot.companies") }}');
                const data = await response.json();

                if (data.success) {
                    select.innerHTML = '<option value="">-- Select company --</option>';
                    data.data.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.name;
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">Failed to load</option>';
                }
            } catch (error) {
                select.innerHTML = '<option value="">Error loading</option>';
            }
        }

        // Handle sync form submission
        document.getElementById('sync-offer-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('sync-btn');
            const resultDiv = document.getElementById('sync-result');
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Syncing...';

            try {
                const formData = new FormData(this);
                const response = await fetch('{{ route("hubspot.sync-offer") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        offer_id: formData.get('offer_id'),
                        contact_id: formData.get('contact_id') || null,
                        company_id: formData.get('company_id') || null
                    })
                });

                const data = await response.json();

                resultDiv.classList.remove('hidden');
                if (data.success) {
                    resultDiv.className = 'mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded';
                    resultDiv.innerHTML = `<p>${data.message}</p><p class="text-sm mt-1">Deal ID: ${data.data.deal_id}</p>`;
                    // Reload page to show new synced offer
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.className = 'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                    resultDiv.textContent = data.message;
                }
            } catch (error) {
                resultDiv.classList.remove('hidden');
                resultDiv.className = 'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded';
                resultDiv.textContent = 'An error occurred while syncing.';
            }

            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg> Sync to HubSpot';
        });
    </script>
    @endif
</x-app-layout>
