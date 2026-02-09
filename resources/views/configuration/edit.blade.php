<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-sm text-secondary-500 mb-2">
                    <a href="{{ route('configuration.index') }}" class="hover:text-primary-500">Configuration</a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="text-secondary-800">{{ $integrationConfig['name'] }}</span>
                </nav>
                <h1 class="page-title">Configure {{ $integrationConfig['name'] }}</h1>
                <p class="page-subtitle">{{ $integrationConfig['description'] }}</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('configuration.index') }}" class="btn btn-outline">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <form action="{{ route('configuration.update', $integration) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Settings -->
            <div class="lg:col-span-2 space-y-6">
                <!-- General Settings Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-secondary-800">General Settings</h3>
                    </div>
                    <div class="card-body space-y-4">
                        <!-- Enable Toggle -->
                        <div class="flex items-center justify-between p-4 bg-secondary-50 rounded-lg">
                            <div>
                                <h4 class="font-medium text-secondary-800">Enable Integration</h4>
                                <p class="text-sm text-secondary-500">Turn this integration on or off</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="enabled" value="1" class="sr-only peer"
                                    {{ old('enabled', $setting?->enabled) ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-secondary-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-secondary-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                            </label>
                        </div>

                        <!-- Environment Selection -->
                        <div>
                            <label class="block text-sm font-medium text-secondary-700 mb-2">Environment</label>
                            <select name="environment" class="form-select w-full">
                                @foreach($integrationConfig['environments'] as $env)
                                <option value="{{ $env }}" {{ old('environment', $setting?->environment ?? 'sandbox') === $env ? 'selected' : '' }}>
                                    {{ ucfirst($env) }}
                                </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-secondary-500 mt-1">Select the API environment to use</p>
                        </div>
                    </div>
                </div>

                <!-- Credentials Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-secondary-800">API Credentials</h3>
                        <p class="text-sm text-secondary-500">Credentials are encrypted before storage</p>
                    </div>
                    <div class="card-body space-y-4">
                        @foreach($integrationConfig['credentials_schema'] as $key => $field)
                        <div>
                            <label class="block text-sm font-medium text-secondary-700 mb-2">
                                {{ $field['label'] }}
                                @if($field['required'])
                                <span class="text-red-500">*</span>
                                @endif
                            </label>
                            @if($field['type'] === 'password')
                            <div class="relative">
                                <input type="password"
                                    name="credentials[{{ $key }}]"
                                    class="form-input w-full pr-10"
                                    placeholder="{{ $setting?->credentials[$key] ?? false ? '••••••••' : 'Enter ' . strtolower($field['label']) }}"
                                    autocomplete="off">
                                <button type="button" onclick="togglePassword(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-secondary-400 hover:text-secondary-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            @elseif($field['type'] === 'url')
                            <input type="url"
                                name="credentials[{{ $key }}]"
                                value="{{ old("credentials.{$key}", $setting?->credentials[$key] ?? '') }}"
                                class="form-input w-full"
                                placeholder="https://...">
                            @else
                            <input type="text"
                                name="credentials[{{ $key }}]"
                                value="{{ old("credentials.{$key}", $setting?->credentials[$key] ?? '') }}"
                                class="form-input w-full"
                                placeholder="Enter {{ strtolower($field['label']) }}">
                            @endif
                            @error("credentials.{$key}")
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endforeach

                        @if(empty($integrationConfig['credentials_schema']))
                        <p class="text-secondary-500 text-center py-4">No credentials required for this integration.</p>
                        @endif
                    </div>
                </div>

                <!-- Additional Settings Card -->
                @if(!empty($integrationConfig['settings_schema']))
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-secondary-800">Additional Settings</h3>
                    </div>
                    <div class="card-body space-y-4">
                        @foreach($integrationConfig['settings_schema'] as $key => $field)
                        <div>
                            <label class="block text-sm font-medium text-secondary-700 mb-2">{{ $field['label'] }}</label>
                            @if($field['type'] === 'multiselect')
                            <div class="flex flex-wrap gap-2">
                                @foreach($field['options'] as $option)
                                <label class="inline-flex items-center">
                                    <input type="checkbox"
                                        name="settings[{{ $key }}][]"
                                        value="{{ $option }}"
                                        class="form-checkbox"
                                        {{ in_array($option, old("settings.{$key}", $setting?->settings[$key] ?? [])) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-secondary-700">{{ ucfirst($option) }}</span>
                                </label>
                                @endforeach
                            </div>
                            @elseif($field['type'] === 'number')
                            <input type="number"
                                name="settings[{{ $key }}]"
                                value="{{ old("settings.{$key}", $setting?->settings[$key] ?? $field['default'] ?? '') }}"
                                class="form-input w-full">
                            @else
                            <input type="text"
                                name="settings[{{ $key }}]"
                                value="{{ old("settings.{$key}", $setting?->settings[$key] ?? $field['default'] ?? '') }}"
                                class="form-input w-full">
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Status Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-secondary-800">Status</h3>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-secondary-600">Status</span>
                            @if($setting?->enabled)
                            <span class="badge badge-success">Enabled</span>
                            @else
                            <span class="badge">Disabled</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-secondary-600">Configured</span>
                            @if($setting?->isConfigured())
                            <span class="badge badge-primary">Yes</span>
                            @else
                            <span class="badge badge-warning">No</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-secondary-600">Environment</span>
                            <span class="badge">{{ ucfirst($setting?->environment ?? 'sandbox') }}</span>
                        </div>
                        @if($setting?->last_tested_at)
                        <div class="pt-4 border-t border-secondary-100">
                            <p class="text-sm text-secondary-600 mb-1">Last Test</p>
                            <p class="text-sm font-medium {{ $setting->last_test_status === 'success' ? 'text-accent-green' : 'text-red-500' }}">
                                {{ ucfirst($setting->last_test_status) }}
                            </p>
                            <p class="text-xs text-secondary-400">{{ $setting->last_tested_at->diffForHumans() }}</p>
                            @if($setting->last_test_message)
                            <p class="text-xs text-secondary-500 mt-2 p-2 bg-secondary-50 rounded">{{ $setting->last_test_message }}</p>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="card">
                    <div class="card-body space-y-3">
                        <button type="submit" class="btn btn-primary w-full">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Save Configuration
                        </button>
                        @if($setting)
                        <button type="button" onclick="testIntegration('{{ $integration }}')" class="btn btn-outline w-full" id="test-btn">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Test Connection
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Help Card -->
                <div class="card bg-primary-50 border-primary-100">
                    <div class="card-body">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-medium text-primary-800">Need Help?</h4>
                                <p class="text-sm text-primary-600 mt-1">
                                    Get your API credentials from the {{ $integrationConfig['name'] }} dashboard.
                                    Make sure to use the correct environment credentials.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
        function togglePassword(button) {
            const input = button.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }

        function testIntegration(integration) {
            const btn = document.getElementById('test-btn');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
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

                setTimeout(() => location.reload(), 1500);
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalContent;
                showNotification('error', 'Test failed: ' + error.message);
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
