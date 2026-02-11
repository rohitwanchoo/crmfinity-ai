<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Welcome back! Here's your MCA overview.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('bankstatement.index') }}" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Analyze Statement
                </a>
                <a href="{{ route('applications.create') }}" class="btn btn-outline">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    New Application
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
        <!-- Bank Statements Analyzed -->
        <a href="{{ route('bankstatement.index') }}" class="stat-card hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">Statements Analyzed</p>
                    <p class="stat-card-value mt-1">{{ number_format($stats['bank_statements_analyzed']) }}</p>
                </div>
                <div class="stat-card-icon bg-blue-100">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-xs">
                <span class="text-secondary-500">{{ number_format($stats['total_transactions']) }} transactions</span>
                <svg class="w-4 h-4 text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </a>

        <!-- Total Applications -->
        <a href="{{ route('applications.index') }}" class="stat-card hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">Total Applications</p>
                    <p class="stat-card-value mt-1">{{ number_format($uwStats['total_applications']) }}</p>
                </div>
                <div class="stat-card-icon bg-primary-100">
                    <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-xs">
                <span class="text-secondary-500">Click to view all</span>
                <svg class="w-4 h-4 text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </a>

        <!-- MCA Lenders -->
        <a href="{{ route('bankstatement.lenders') }}" class="stat-card hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">MCA Lenders</p>
                    <p class="stat-card-value mt-1">{{ number_format($stats['total_lenders']) }}</p>
                </div>
                <div class="stat-card-icon bg-purple-100">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-xs">
                <span class="text-secondary-500">{{ $stats['detected_lenders'] }} detected</span>
                <svg class="w-4 h-4 text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </a>

        <!-- Debt Collectors -->
        <a href="{{ route('bankstatement.lenders') }}" class="stat-card hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">Debt Collectors</p>
                    <p class="stat-card-value mt-1">{{ number_format($stats['total_debt_collectors']) }}</p>
                </div>
                <div class="stat-card-icon bg-orange-100">
                    <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-xs">
                <span class="text-secondary-500">Collection agencies</span>
                <svg class="w-4 h-4 text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </a>

        <!-- API Usage Cost -->
        <a href="{{ route('api-usage.index') }}" class="stat-card hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">API Cost (30d)</p>
                    <p class="stat-card-value mt-1">${{ number_format($stats['api_cost'] ?? 0, 2) }}</p>
                    <!-- DEBUG: {{ $stats['api_cost'] ?? 'NULL' }} -->
                </div>
                <div class="stat-card-icon bg-green-100">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-xs">
                <span class="text-secondary-500">{{ number_format($stats['api_requests']) }} requests</span>
                <svg class="w-4 h-4 text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </a>
    </div>

    <!-- Application Status Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Application Stats Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="text-lg font-semibold text-secondary-800">Application Status</h3>
            </div>
            <div class="card-body">
                <div class="space-y-4">
                    <!-- Pending -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
                            <span class="text-sm text-gray-600">Pending Review</span>
                        </div>
                        <span class="text-lg font-semibold text-gray-900">{{ $uwStats['pending'] }}</span>
                    </div>
                    <!-- Approved -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-600">Approved</span>
                        </div>
                        <span class="text-lg font-semibold text-gray-900">{{ $uwStats['approved'] }}</span>
                    </div>
                    <!-- Declined -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                            <span class="text-sm text-gray-600">Declined</span>
                        </div>
                        <span class="text-lg font-semibold text-gray-900">{{ $uwStats['declined'] }}</span>
                    </div>
                    <!-- Scored -->
                    <div class="flex items-center justify-between pt-3 border-t">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                            <span class="text-sm text-gray-600">Total Scored</span>
                        </div>
                        <span class="text-lg font-semibold text-gray-900">{{ $uwStats['scored'] }}</span>
                    </div>
                </div>
                <div class="mt-6">
                    <a href="{{ route('applications.index') }}" class="btn btn-outline btn-sm w-full">
                        View All Applications
                    </a>
                </div>
            </div>
        </div>

        <!-- Top Lenders Card -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="text-lg font-semibold text-secondary-800">Top Lenders</h3>
                <a href="{{ route('bankstatement.lenders') }}" class="text-sm text-primary-500 hover:text-primary-600">View All</a>
            </div>
            <div class="card-body">
                @if($topLenders->count() > 0)
                <div class="space-y-3">
                    @foreach($topLenders as $index => $lender)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-6 h-6 bg-purple-100 text-purple-600 rounded-full text-xs font-semibold">{{ $index + 1 }}</span>
                            <span class="text-sm text-gray-700 truncate" style="max-width: 140px;">{{ $lender->lender_name }}</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900">{{ number_format($lender->total_detections) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-6">
                    <p class="text-sm text-gray-500">No lenders detected yet</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Top Debt Collectors Card -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="text-lg font-semibold text-secondary-800">Top Debt Collectors</h3>
                <a href="{{ route('bankstatement.lenders') }}" class="text-sm text-orange-500 hover:text-orange-600">View All</a>
            </div>
            <div class="card-body">
                @if($topDebtCollectors->count() > 0)
                <div class="space-y-3">
                    @foreach($topDebtCollectors as $index => $collector)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-6 h-6 bg-orange-100 text-orange-600 rounded-full text-xs font-semibold">{{ $index + 1 }}</span>
                            <span class="text-sm text-gray-700 truncate" style="max-width: 140px;">{{ $collector->lender_name }}</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900">{{ number_format($collector->total_detections) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-6">
                    <p class="text-sm text-gray-500">No debt collectors detected yet</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Bank Statement Analysis Card -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-secondary-800">Bank Statement Analysis</h3>
                        <p class="text-secondary-500 mt-1 text-sm">Upload and analyze bank statements to detect MCA positions.</p>
                        <a href="{{ route('bankstatement.index') }}" class="btn btn-primary btn-sm mt-3">
                            Analyze Statement
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plaid Connections Card -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-secondary-800">Bank Connections</h3>
                        <p class="text-secondary-500 mt-1 text-sm">Connect bank accounts via Plaid for automatic data import.</p>
                        <a href="{{ route('plaid.index') }}" class="btn btn-success btn-sm mt-3">
                            Manage Connections
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Applications Table -->
    <div class="card mb-6">
        <div class="card-header flex items-center justify-between">
            <h3 class="text-lg font-semibold text-secondary-800">Recent Applications</h3>
            <a href="{{ route('applications.index') }}" class="text-sm text-primary-500 hover:text-primary-600 font-medium">
                View All
            </a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Business Name</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($uwStats['recent_applications'] as $app)
                    <tr>
                        <td>
                            <span class="font-medium text-secondary-800">{{ $app->business_name ?? 'N/A' }}</span>
                        </td>
                        <td>
                            @if($app->status === 'approved')
                                <span class="badge badge-success">Approved</span>
                            @elseif($app->status === 'declined')
                                <span class="badge badge-danger">Declined</span>
                            @elseif($app->status === 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @else
                                <span class="badge">{{ ucfirst($app->status ?? 'New') }}</span>
                            @endif
                        </td>
                        <td class="text-secondary-500">
                            {{ $app->created_at->format('M d, Y') }}
                        </td>
                        <td class="text-right">
                            <a href="{{ route('applications.show', $app->id) }}" class="btn btn-outline btn-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-8">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-secondary-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-secondary-500 font-medium">No applications yet</p>
                                <p class="text-secondary-400 text-sm mt-1">Create your first application to get started</p>
                                <a href="{{ route('applications.create') }}" class="btn btn-primary btn-sm mt-4">
                                    New Application
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Patterns Detected -->
    @if($recentPatterns->count() > 0)
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="text-lg font-semibold text-secondary-800">Recently Detected Patterns</h3>
            <a href="{{ route('bankstatement.lenders') }}" class="text-sm text-primary-500 hover:text-primary-600 font-medium">
                View All Lenders
            </a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Lender/Collector</th>
                        <th>Detection Count</th>
                        <th>Last Detected</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentPatterns as $pattern)
                    <tr>
                        <td>
                            <a href="{{ route('bankstatement.lender-detail', $pattern->lender_id) }}" class="flex items-center gap-3 hover:text-primary-600">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 font-semibold text-xs">{{ strtoupper(substr($pattern->lender_name, 0, 2)) }}</span>
                                </div>
                                <span class="font-medium">{{ $pattern->lender_name }}</span>
                            </a>
                        </td>
                        <td>
                            <span class="font-semibold">{{ number_format($pattern->usage_count) }}</span>
                        </td>
                        <td class="text-secondary-500">
                            {{ $pattern->updated_at->diffForHumans() }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</x-app-layout>
