<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                MCA Applications
            </h2>
            <a href="{{ route('applications.create') }}" class="btn btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Application
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="stat-card">
                    <div class="stat-card-icon bg-primary-100">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Total</span>
                        <span class="stat-card-value">{{ $stats['total'] }}</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon bg-warning-100">
                        <svg class="w-6 h-6 text-warning-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Submitted</span>
                        <span class="stat-card-value">{{ $stats['submitted'] }}</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon bg-info-100">
                        <svg class="w-6 h-6 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Under Review</span>
                        <span class="stat-card-value">{{ $stats['under_review'] }}</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon bg-success-100">
                        <svg class="w-6 h-6 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Approved</span>
                        <span class="stat-card-value">{{ $stats['approved'] }}</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon bg-danger-100">
                        <svg class="w-6 h-6 text-danger-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Declined</span>
                        <span class="stat-card-value">{{ $stats['declined'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-6">
                <div class="card-body">
                    <form method="GET" action="{{ route('applications.index') }}" class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search by business name, email, owner..."
                                   class="form-input w-full">
                        </div>
                        <div class="w-40">
                            <select name="status" class="form-select w-full">
                                <option value="">All Statuses</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                                <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                                <option value="under_review" {{ request('status') == 'under_review' ? 'selected' : '' }}>Under Review</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="declined" {{ request('status') == 'declined' ? 'selected' : '' }}>Declined</option>
                                <option value="funded" {{ request('status') == 'funded' ? 'selected' : '' }}>Funded</option>
                            </select>
                        </div>
                        <div class="w-36">
                            <select name="uw_score" class="form-select w-full">
                                <option value="">UW Score</option>
                                <option value="high" {{ request('uw_score') == 'high' ? 'selected' : '' }}>High (75+)</option>
                                <option value="medium" {{ request('uw_score') == 'medium' ? 'selected' : '' }}>Medium (45-74)</option>
                                <option value="low" {{ request('uw_score') == 'low' ? 'selected' : '' }}>Low (&lt;45)</option>
                                <option value="none" {{ request('uw_score') == 'none' ? 'selected' : '' }}>Not Scored</option>
                            </select>
                        </div>
                        <div class="w-36">
                            <select name="uw_decision" class="form-select w-full">
                                <option value="">UW Decision</option>
                                <option value="APPROVE" {{ request('uw_decision') == 'APPROVE' ? 'selected' : '' }}>Approve</option>
                                <option value="CONDITIONAL_APPROVE" {{ request('uw_decision') == 'CONDITIONAL_APPROVE' ? 'selected' : '' }}>Conditional</option>
                                <option value="REVIEW" {{ request('uw_decision') == 'REVIEW' ? 'selected' : '' }}>Review</option>
                                <option value="HIGH_RISK" {{ request('uw_decision') == 'HIGH_RISK' ? 'selected' : '' }}>High Risk</option>
                                <option value="DECLINE" {{ request('uw_decision') == 'DECLINE' ? 'selected' : '' }}>Decline</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('applications.index') }}" class="btn btn-secondary">Clear</a>
                    </form>
                </div>
            </div>

            <!-- Applications Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Business</th>
                                    <th>Owner</th>
                                    <th>Requested</th>
                                    <th>Monthly Rev</th>
                                    <th>Status</th>
                                    <th>UW Score</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applications as $application)
                                    <tr>
                                        <td>
                                            <a href="{{ route('applications.show', $application) }}" class="text-primary-600 font-medium">
                                                #{{ $application->id }}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="font-medium">{{ $application->business_name }}</div>
                                            <div class="text-sm text-gray-500">{{ $application->industry }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $application->owner_first_name }} {{ $application->owner_last_name }}</div>
                                            <div class="text-sm text-gray-500">{{ $application->owner_email }}</div>
                                        </td>
                                        <td>${{ number_format($application->requested_amount, 0) }}</td>
                                        <td>${{ number_format($application->monthly_revenue, 0) }}</td>
                                        <td>
                                            @php
                                                $statusColors = [
                                                    'draft' => 'badge-secondary',
                                                    'submitted' => 'badge-info',
                                                    'processing' => 'badge-warning',
                                                    'under_review' => 'badge-warning',
                                                    'approved' => 'badge-success',
                                                    'declined' => 'badge-danger',
                                                    'funded' => 'badge-success',
                                                    'closed' => 'badge-secondary',
                                                ];
                                            @endphp
                                            <span class="badge {{ $statusColors[$application->status] ?? 'badge-secondary' }}">
                                                {{ ucfirst(str_replace('_', ' ', $application->status)) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($application->underwriting_score)
                                                @php
                                                    $uwScore = $application->underwriting_score;
                                                    $uwClass = $uwScore >= 75 ? 'bg-success-100 text-success-800' :
                                                               ($uwScore >= 45 ? 'bg-warning-100 text-warning-800' : 'bg-danger-100 text-danger-800');
                                                    $uwDecision = $application->underwriting_decision;
                                                    $decisionShort = match($uwDecision) {
                                                        'APPROVE' => 'APR',
                                                        'CONDITIONAL_APPROVE' => 'COND',
                                                        'REVIEW' => 'REV',
                                                        'HIGH_RISK' => 'HIGH',
                                                        'DECLINE' => 'DEC',
                                                        default => '--'
                                                    };
                                                @endphp
                                                <div class="flex items-center space-x-1">
                                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full {{ $uwClass }} font-bold text-sm">
                                                        {{ $uwScore }}
                                                    </span>
                                                    <span class="text-xs text-gray-500">{{ $decisionShort }}</span>
                                                </div>
                                            @else
                                                <span class="text-gray-400">--</span>
                                            @endif
                                        </td>
                                        <td>{{ $application->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ route('applications.show', $application) }}" class="btn btn-sm btn-primary">
                                                    View
                                                </a>
                                                <a href="{{ route('applications.edit', $application) }}" class="btn btn-sm btn-secondary">
                                                    Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-8 text-gray-500">
                                            No applications found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($applications->hasPages())
                    <div class="card-footer">
                        {{ $applications->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
