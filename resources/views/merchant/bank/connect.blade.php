<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connect Your Bank Account - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo/Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Connect Your Bank Account</h1>
                <p class="text-gray-600 mt-2">Securely link your bank account to complete your application</p>
            </div>

            <!-- Main Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8" id="connect-card">
                <div class="text-center mb-6">
                    <div class="text-sm text-gray-500 mb-2">Connecting account for</div>
                    <div class="font-semibold text-lg text-gray-900">{{ $linkRequest->business_name }}</div>
                    <div class="text-sm text-gray-600">{{ $linkRequest->merchant_name }}</div>
                </div>

                <div class="border-t border-b border-gray-100 py-6 my-6">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Bank-Level Security</h3>
                            <p class="text-sm text-gray-500 mt-1">Your credentials are encrypted and never shared. We use Plaid, a secure service used by thousands of financial institutions.</p>
                        </div>
                    </div>
                </div>

                <button
                    id="connect-button"
                    onclick="initPlaidLink()"
                    class="w-full bg-indigo-600 text-white py-4 px-6 rounded-xl font-semibold text-lg hover:bg-indigo-700 transition-colors flex items-center justify-center"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    Connect Bank Account
                </button>

                <p class="text-xs text-gray-400 text-center mt-4">
                    By connecting your account, you agree to our Terms of Service and Privacy Policy.
                </p>
            </div>

            <!-- Success Card (hidden initially) -->
            <div class="bg-white rounded-2xl shadow-xl p-8 hidden" id="success-card">
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Bank Connected Successfully!</h2>
                    <p class="text-gray-600 mb-6">Your bank account has been securely linked to your application.</p>
                    <div id="connected-bank-info" class="bg-gray-50 rounded-lg p-4 text-left mb-6">
                        <!-- Bank info will be inserted here -->
                    </div>
                    <p class="text-sm text-gray-500">You can close this window now. We'll review your application and be in touch soon.</p>
                </div>
            </div>

            <!-- Error Card (hidden initially) -->
            <div class="bg-white rounded-2xl shadow-xl p-8 hidden" id="error-card">
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Connection Failed</h2>
                    <p class="text-gray-600 mb-6" id="error-message">Something went wrong while connecting your bank account.</p>
                    <button onclick="retryConnection()" class="bg-indigo-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-indigo-700 transition-colors">
                        Try Again
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-sm text-gray-400">
                <p>Powered by Plaid</p>
                <p class="mt-1">{{ config('app.name') }}</p>
            </div>
        </div>
    </div>

    <script>
        let linkHandler = null;

        async function initPlaidLink() {
            const button = document.getElementById('connect-button');
            button.disabled = true;
            button.innerHTML = `
                <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Initializing...
            `;

            try {
                // Get link token from server
                const response = await fetch('{{ route("merchant.bank.create-link-token", $linkRequest->token) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to initialize');
                }

                // Initialize Plaid Link
                linkHandler = Plaid.create({
                    token: data.link_token,
                    onSuccess: async (public_token, metadata) => {
                        await handleSuccess(public_token, metadata);
                    },
                    onExit: (err, metadata) => {
                        if (err) {
                            console.error('Plaid Link exited with error:', err);
                        }
                        resetButton();
                    },
                    onEvent: (eventName, metadata) => {
                        console.log('Plaid event:', eventName);
                    }
                });

                linkHandler.open();
            } catch (error) {
                console.error('Error initializing Plaid Link:', error);
                showError(error.message);
            }
        }

        async function handleSuccess(publicToken, metadata) {
            const button = document.getElementById('connect-button');
            button.innerHTML = `
                <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Connecting...
            `;

            try {
                const response = await fetch('{{ route("merchant.bank.exchange-token", $linkRequest->token) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        public_token: publicToken,
                        institution: metadata.institution,
                        accounts: metadata.accounts
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.institution, data.accounts_count);
                } else {
                    throw new Error(data.message || 'Failed to connect bank');
                }
            } catch (error) {
                console.error('Error exchanging token:', error);
                showError(error.message);
            }
        }

        function showSuccess(institution, accountsCount) {
            document.getElementById('connect-card').classList.add('hidden');
            document.getElementById('success-card').classList.remove('hidden');
            document.getElementById('connected-bank-info').innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-gray-900">${institution}</div>
                        <div class="text-sm text-gray-500">${accountsCount} account(s) connected</div>
                    </div>
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            `;
        }

        function showError(message) {
            document.getElementById('connect-card').classList.add('hidden');
            document.getElementById('error-card').classList.remove('hidden');
            document.getElementById('error-message').textContent = message;
        }

        function retryConnection() {
            document.getElementById('error-card').classList.add('hidden');
            document.getElementById('connect-card').classList.remove('hidden');
            resetButton();
        }

        function resetButton() {
            const button = document.getElementById('connect-button');
            button.disabled = false;
            button.innerHTML = `
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                Connect Bank Account
            `;
        }
    </script>
</body>
</html>
