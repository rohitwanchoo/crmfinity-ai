<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="page-title">API Usage Tracking</h1>
                <p class="page-subtitle">Monitor your LSC API usage, costs, and token consumption</p>
            </div>
            <div class="flex gap-3">
                <form method="GET" action="{{ route('api-usage.index') }}" class="flex items-center gap-2">
                    <select name="days" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 days</option>
                        <option value="30" {{ $days == 30 ? 'selected' : '' }}>Last 30 days</option>
                        <option value="90" {{ $days == 90 ? 'selected' : '' }}>Last 90 days</option>
                    </select>
                </form>
                <a href="{{ route('api-usage.export', ['days' => $days]) }}" class="btn btn-outline">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export CSV
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Cost -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">Total Cost</p>
                    <p class="stat-card-value mt-1">${{ number_format($totalCost, 2) }}</p>
                </div>
                <div class="stat-card-icon bg-green-100">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-xs text-secondary-500">Last {{ $days }} days</span>
            </div>
        </div>

        <!-- Total Tokens -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">Total Tokens</p>
                    <p class="stat-card-value mt-1">{{ number_format($totalTokens) }}</p>
                </div>
                <div class="stat-card-icon bg-blue-100">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                @if($totalTokens > 0)
                    <span class="text-xs text-secondary-500">{{ number_format($avgTokensPerRequest, 0) }} per request</span>
                @else
                    <span class="text-xs text-yellow-600">Run new analysis to track tokens</span>
                @endif
            </div>
        </div>

        <!-- Total Requests -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">API Requests</p>
                    <p class="stat-card-value mt-1">{{ number_format($totalRequests) }}</p>
                </div>
                <div class="stat-card-icon bg-purple-100">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-xs text-secondary-500">${{ number_format($avgCostPerRequest, 4) }} per request</span>
            </div>
        </div>

        <!-- Cost Efficiency -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">Cost per 1M Tokens</p>
                    <p class="stat-card-value mt-1">${{ $totalTokens > 0 ? number_format(($totalCost / $totalTokens) * 1000000, 2) : '0.00' }}</p>
                </div>
                <div class="stat-card-icon bg-orange-100">
                    <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-xs text-secondary-500">Average rate</span>
            </div>
        </div>
    </div>

    <!-- Cost by Model -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Cost by Model</h3>
        </div>
        <div class="card-body">
            @if($costByModel->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Model</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requests</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Tokens</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Cost/Request</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($costByModel as $model)
                                <tr class="{{ ($model->display_name ?? $model->model_used) === 'LSC Basic' ? 'bg-green-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $model->display_name ?? $model->model_used }}</span>
                                            @if(($model->display_name ?? $model->model_used) === 'LSC Basic')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    Most Cost-Effective
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($model->request_count) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($model->total_tokens) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${{ number_format($model->total_cost, 4) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${{ number_format($model->total_cost / $model->request_count, 4) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-8">No API usage data available for the selected period.</p>
            @endif
        </div>
    </div>

    <!-- Cost by User -->
    @if($costByUser->count() > 0)
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Cost by User</h3>
        </div>
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requests</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Tokens</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($costByUser as $user)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $user->user->username ?? 'Unknown' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($user->request_count) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($user->total_tokens) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${{ number_format($user->total_cost, 4) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Recent Requests -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent API Requests</h3>
        </div>
        <div class="card-body">
            @if($recentRequests->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Model</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tokens (In/Out)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($recentRequests as $request)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->created_at->format('M d, Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->user->username ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->model_used }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($request->input_tokens) }} / {{ number_format($request->output_tokens) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${{ number_format($request->total_cost, 4) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            {{ $request->status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ ucfirst($request->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-8">No recent API requests found.</p>
            @endif
        </div>
    </div>
</x-app-layout>
