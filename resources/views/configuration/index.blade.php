<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="page-title">Configuration</h1>
                <p class="page-subtitle">Manage verification integrations and API settings</p>
            </div>
        </div>
    </x-slot>

    <!-- Integration Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($integrations as $key => $integration)
        <div class="card" id="integration-{{ $key }}">
            <div class="card-body">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center
                            {{ $integration['enabled'] ? 'bg-accent-green/20' : 'bg-secondary-100' }}">
                            @if($integration['icon'] === 'bank')
                            <svg class="w-6 h-6 {{ $integration['enabled'] ? 'text-accent-green' : 'text-secondary-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            @elseif($integration['icon'] === 'credit-card')
                            <svg class="w-6 h-6 {{ $integration['enabled'] ? 'text-accent-green' : 'text-secondary-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            @elseif($integration['icon'] === 'user-check')
                            <svg class="w-6 h-6 {{ $integration['enabled'] ? 'text-accent-green' : 'text-secondary-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11l2 2 4-4" />
                            </svg>
                            @elseif($integration['icon'] === 'database')
                            <svg class="w-6 h-6 {{ $integration['enabled'] ? 'text-accent-green' : 'text-secondary-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            @elseif($integration['icon'] === 'file-text')
                            <svg class="w-6 h-6 {{ $integration['enabled'] ? 'text-accent-green' : 'text-secondary-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            @endif
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-secondary-800">{{ $integration['name'] }}</h3>
                            <p class="text-sm text-secondary-500">{{ $integration['description'] }}</p>
                        </div>
                    </div>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap gap-2 mb-4">
                    @if($integration['enabled'])
                        <span class="badge badge-success">Enabled</span>
                    @else
                        <span class="badge">Disabled</span>
                    @endif

                    @if($integration['configured'])
                        <span class="badge badge-primary">Configured</span>
                    @else
                        <span class="badge badge-warning">Not Configured</span>
                    @endif

                    <span class="badge">{{ ucfirst($integration['environment']) }}</span>

                    @if($integration['last_test_status'] === 'success')
                        <span class="badge badge-success">Connected</span>
                    @elseif($integration['last_test_status'] === 'failed')
                        <span class="badge badge-danger">Connection Failed</span>
                    @endif
                </div>

                @if($integration['last_tested_at'])
                <p class="text-xs text-secondary-400 mb-4">
                    Last tested: {{ $integration['last_tested_at']->diffForHumans() }}
                </p>
                @endif

                <!-- Actions -->
                <div class="flex items-center gap-2 pt-4 border-t border-secondary-100">
                    <a href="{{ route('configuration.edit', $key) }}" class="btn btn-primary btn-sm flex-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Configure
                    </a>
                    @if($integration['configured'])
                    <button onclick="testIntegration('{{ $key }}')" class="btn btn-outline btn-sm" id="test-btn-{{ $key }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Test
                    </button>
                    <button onclick="toggleIntegration('{{ $key }}')" class="btn {{ $integration['enabled'] ? 'btn-danger' : 'btn-success' }} btn-sm" id="toggle-btn-{{ $key }}">
                        @if($integration['enabled'])
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        @endif
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Help Section -->
    <div class="card mt-6">
        <div class="card-body">
            <h3 class="text-lg font-semibold text-secondary-800 mb-4">Integration Setup Guide</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="p-4 bg-secondary-50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-6 h-6 rounded-full bg-primary-500 text-white text-sm flex items-center justify-center font-medium">1</span>
                        <h4 class="font-medium text-secondary-800">Configure Credentials</h4>
                    </div>
                    <p class="text-sm text-secondary-500">Enter your API keys and credentials for each integration you want to use.</p>
                </div>
                <div class="p-4 bg-secondary-50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-6 h-6 rounded-full bg-primary-500 text-white text-sm flex items-center justify-center font-medium">2</span>
                        <h4 class="font-medium text-secondary-800">Test Connection</h4>
                    </div>
                    <p class="text-sm text-secondary-500">Use the Test button to verify your credentials are working correctly.</p>
                </div>
                <div class="p-4 bg-secondary-50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-6 h-6 rounded-full bg-primary-500 text-white text-sm flex items-center justify-center font-medium">3</span>
                        <h4 class="font-medium text-secondary-800">Enable Integration</h4>
                    </div>
                    <p class="text-sm text-secondary-500">Toggle the integration on to start using it in your verification workflows.</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function testIntegration(integration) {
            const btn = document.getElementById(`test-btn-${integration}`);
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg> Testing...`;

            fetch(`/configuration/${integration}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalContent;

                if (data.success) {
                    showNotification('success', data.message);
                } else {
                    showNotification('error', data.message);
                }

                // Reload to update status
                setTimeout(() => location.reload(), 1500);
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalContent;
                showNotification('error', 'Test failed: ' + error.message);
            });
        }

        function toggleIntegration(integration) {
            const btn = document.getElementById(`toggle-btn-${integration}`);
            btn.disabled = true;

            fetch(`/configuration/${integration}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message);
                    setTimeout(() => location.reload(), 500);
                } else {
                    showNotification('error', data.message);
                    btn.disabled = false;
                }
            })
            .catch(error => {
                btn.disabled = false;
                showNotification('error', 'Toggle failed: ' + error.message);
            });
        }

        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-accent-green text-white' : 'bg-red-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center gap-2">
                    ${type === 'success'
                        ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'
                        : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>'
                    }
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }
    </script>
    @endpush
</x-app-layout>
