<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="page-title">Bank Connections</h1>
                <p class="page-subtitle">Connect your bank accounts via Plaid for automatic transaction analysis</p>
            </div>
            <div class="flex gap-3">
                <button id="link-button" class="btn btn-primary" disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Connect Bank
                </button>
            </div>
        </div>
    </x-slot>

    <!-- Connected Accounts -->
    @if($linkedAccounts->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            @foreach($linkedAccounts as $item)
                <div class="card" data-item-id="{{ $item->id }}">
                    <div class="card-header flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
                                <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-secondary-800">{{ $item->institution_name }}</h3>
                                <p class="text-sm text-secondary-500">Connected {{ $item->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <span class="badge badge-{{ $item->status_badge }}">{{ $item->status_label }}</span>
                    </div>
                    <div class="card-body">
                        <!-- Accounts List -->
                        <div class="space-y-3 mb-4">
                            @foreach($item->accounts as $account)
                                <div class="flex items-center justify-between p-3 bg-secondary-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                               class="account-checkbox form-checkbox"
                                               value="{{ $account->id }}"
                                               data-item-id="{{ $item->id }}">
                                        <div>
                                            <p class="font-medium text-secondary-800">{{ $account->display_name }}</p>
                                            <p class="text-xs text-secondary-500">{{ $account->type_label }} - {{ $account->subtype }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-secondary-800">${{ number_format($account->current_balance, 2) }}</p>
                                        @if($account->available_balance)
                                            <p class="text-xs text-secondary-500">Available: ${{ number_format($account->available_balance, 2) }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap gap-2">
                            <button class="btn btn-primary btn-sm sync-btn" data-item-id="{{ $item->id }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Sync Transactions
                            </button>
                            <button class="btn btn-success btn-sm analyze-btn" data-item-id="{{ $item->id }}" disabled>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Analyze Selected
                            </button>
                            @if($item->needsReauth())
                                <button class="btn btn-warning btn-sm reauth-btn" data-item-id="{{ $item->id }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Re-authenticate
                                </button>
                            @endif
                            <button class="btn btn-danger btn-sm disconnect-btn" data-item-id="{{ $item->id }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Disconnect
                            </button>
                        </div>

                        @if($item->last_synced_at)
                            <p class="text-xs text-secondary-400 mt-3">Last synced: {{ $item->last_synced_at->diffForHumans() }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Empty State -->
    @if($linkedAccounts->isEmpty())
        <div class="card">
            <div class="card-body text-center py-12">
                <div class="w-20 h-20 mx-auto mb-6 bg-primary-100 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-secondary-800 mb-2">No Bank Accounts Connected</h3>
                <p class="text-secondary-500 mb-6 max-w-md mx-auto">
                    Connect your bank accounts to automatically import and analyze transactions without uploading PDFs.
                </p>
                <button id="link-button-empty" class="btn btn-primary" disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Connect Your First Bank
                </button>
            </div>
        </div>
    @endif

    <!-- Features Info -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <div class="card">
            <div class="card-body text-center">
                <div class="w-12 h-12 mx-auto mb-4 bg-accent-teal/20 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-accent-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h4 class="font-semibold text-secondary-800 mb-2">Bank-Level Security</h4>
                <p class="text-sm text-secondary-500">256-bit encryption protects your data. We never store login credentials.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <div class="w-12 h-12 mx-auto mb-4 bg-primary-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h4 class="font-semibold text-secondary-800 mb-2">Instant Access</h4>
                <p class="text-sm text-secondary-500">Get up to 24 months of transaction history instantly.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <div class="w-12 h-12 mx-auto mb-4 bg-accent-green/20 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-accent-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h4 class="font-semibold text-secondary-800 mb-2">Accurate Analysis</h4>
                <p class="text-sm text-secondary-500">Pre-categorized transactions for faster underwriting decisions.</p>
            </div>
        </div>
    </div>

    <!-- Analyze Form (Hidden) -->
    <form id="analyze-form" action="{{ route('plaid.analyze') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="account_ids" id="account-ids">
    </form>

    <!-- Plaid Link SDK -->
    <script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let linkHandler = null;
            const linkButtons = document.querySelectorAll('#link-button, #link-button-empty');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Initialize Plaid Link
            async function initPlaidLink(itemId = null) {
                try {
                    const body = itemId ? { item_id: itemId } : {};
                    const response = await fetch('{{ route("plaid.create-link-token") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(body),
                    });

                    const data = await response.json();

                    if (!data.success) {
                        alert(data.message || 'Failed to initialize Plaid');
                        return;
                    }

                    linkHandler = Plaid.create({
                        token: data.link_token,
                        onSuccess: async (publicToken, metadata) => {
                            await handlePlaidSuccess(publicToken, metadata);
                        },
                        onExit: (err, metadata) => {
                            if (err) {
                                console.error('Plaid Link error:', err);
                            }
                        },
                        onEvent: (eventName, metadata) => {
                            console.log('Plaid event:', eventName);
                        },
                    });

                    // Enable link buttons
                    linkButtons.forEach(btn => {
                        btn.disabled = false;
                        btn.addEventListener('click', () => linkHandler.open());
                    });

                } catch (error) {
                    console.error('Failed to initialize Plaid Link:', error);
                }
            }

            // Handle successful link
            async function handlePlaidSuccess(publicToken, metadata) {
                try {
                    const response = await fetch('{{ route("plaid.exchange-token") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            public_token: publicToken,
                            metadata: metadata,
                        }),
                    });

                    const data = await response.json();

                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to connect bank');
                    }
                } catch (error) {
                    console.error('Failed to exchange token:', error);
                    alert('Failed to connect bank account');
                }
            }

            // Initialize
            initPlaidLink();

            // Sync button handlers
            document.querySelectorAll('.sync-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const itemId = this.dataset.itemId;
                    this.disabled = true;
                    this.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Syncing...';

                    try {
                        const response = await fetch('{{ route("plaid.sync") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ item_id: itemId }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to sync transactions');
                        }
                    } catch (error) {
                        alert('Failed to sync transactions');
                    }

                    this.disabled = false;
                    this.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg> Sync Transactions';
                });
            });

            // Disconnect button handlers
            document.querySelectorAll('.disconnect-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    if (!confirm('Are you sure you want to disconnect this bank account?')) return;

                    const itemId = this.dataset.itemId;

                    try {
                        const response = await fetch('{{ route("plaid.disconnect") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ item_id: itemId }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to disconnect');
                        }
                    } catch (error) {
                        alert('Failed to disconnect bank account');
                    }
                });
            });

            // Re-auth button handlers
            document.querySelectorAll('.reauth-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const itemId = this.dataset.itemId;
                    await initPlaidLink(itemId);
                    linkHandler.open();
                });
            });

            // Account checkbox handlers
            document.querySelectorAll('.account-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const itemId = this.dataset.itemId;
                    const card = document.querySelector(`[data-item-id="${itemId}"]`);
                    const analyzeBtn = card.querySelector('.analyze-btn');
                    const checkedBoxes = card.querySelectorAll('.account-checkbox:checked');

                    analyzeBtn.disabled = checkedBoxes.length === 0;
                });
            });

            // Analyze button handlers
            document.querySelectorAll('.analyze-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const itemId = this.dataset.itemId;
                    const card = document.querySelector(`[data-item-id="${itemId}"]`);
                    const checkedBoxes = card.querySelectorAll('.account-checkbox:checked');

                    if (checkedBoxes.length === 0) {
                        alert('Please select at least one account to analyze');
                        return;
                    }

                    const accountIds = Array.from(checkedBoxes).map(cb => cb.value);
                    document.getElementById('account-ids').value = JSON.stringify(accountIds);
                    document.getElementById('analyze-form').submit();
                });
            });
        });
    </script>
</x-app-layout>
