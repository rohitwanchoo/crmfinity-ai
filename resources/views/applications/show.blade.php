<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <a href="{{ route('applications.index') }}" class="mr-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Application #{{ $application->id }}
                    </h2>
                    <p class="text-sm text-gray-500">{{ $application->business_name }}</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('applications.edit', $application) }}" class="btn btn-secondary">Edit</a>
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
                <span class="badge {{ $statusColors[$application->status] ?? 'badge-secondary' }} text-base px-4 py-2">
                    {{ ucfirst(str_replace('_', ' ', $application->status)) }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ activeTab: 'overview' }">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm">
                        Overview
                    </button>
                    <button @click="activeTab = 'verifications'" :class="activeTab === 'verifications' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm">
                        Verifications
                    </button>
                    <button @click="activeTab = 'banking'" :class="activeTab === 'banking' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm">
                        Banking
                    </button>
                    <button @click="activeTab = 'documents'" :class="activeTab === 'documents' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm">
                        Documents
                    </button>
                    <button @click="activeTab = 'notes'" :class="activeTab === 'notes' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm">
                        Notes & Activity
                    </button>
                </nav>
            </div>

            <!-- Flow Progress Indicator -->
            @if(isset($flowStatus))
            <div class="card mb-6">
                <div class="card-body py-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-700">Application Flow</h3>
                        <span class="text-sm text-gray-500">Current Phase: <span class="font-medium text-primary-600">{{ ucfirst(str_replace('_', ' ', $flowStatus['current_phase'])) }}</span></span>
                    </div>
                    <div class="relative">
                        <!-- Progress Bar Background -->
                        <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200 rounded"></div>

                        <!-- Progress Steps -->
                        <div class="relative flex justify-between">
                            @foreach($flowStatus['flow_progress'] as $phaseKey => $phase)
                                @php
                                    $stepClasses = match($phase['status']) {
                                        'completed' => 'bg-success-500 text-white border-success-500',
                                        'current' => 'bg-primary-500 text-white border-primary-500 ring-4 ring-primary-100',
                                        'declined' => 'bg-danger-500 text-white border-danger-500',
                                        'skipped' => 'bg-gray-300 text-gray-500 border-gray-300',
                                        default => 'bg-white text-gray-400 border-gray-300',
                                    };
                                    $labelClasses = match($phase['status']) {
                                        'completed' => 'text-success-600 font-medium',
                                        'current' => 'text-primary-600 font-semibold',
                                        'declined' => 'text-danger-600 font-medium',
                                        default => 'text-gray-400',
                                    };
                                    $iconMap = [
                                        'file-text' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
                                        'shield-check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
                                        'landmark' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>',
                                        'calculator' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
                                        'gavel' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                                        'banknotes' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>',
                                    ];
                                @endphp
                                <div class="flex flex-col items-center" style="width: 16.666%;">
                                    <div class="w-10 h-10 rounded-full border-2 flex items-center justify-center {{ $stepClasses }}">
                                        @if($phase['status'] === 'completed')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @elseif($phase['status'] === 'declined')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                {!! $iconMap[$phase['icon']] ?? '' !!}
                                            </svg>
                                        @endif
                                    </div>
                                    <span class="mt-2 text-xs {{ $labelClasses }}">{{ $phase['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Recommended Actions -->
                    @if(isset($recommendedActions) && count($recommendedActions) > 0)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex items-center flex-wrap gap-2">
                            <span class="text-sm text-gray-500 mr-2">Next steps:</span>
                            @foreach(array_slice($recommendedActions, 0, 3) as $action)
                                @php
                                    $actionClass = match($action['priority']) {
                                        'high' => 'bg-primary-100 text-primary-700 border-primary-200',
                                        'medium' => 'bg-gray-100 text-gray-700 border-gray-200',
                                        default => 'bg-gray-50 text-gray-600 border-gray-100',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 text-sm rounded-full border {{ $actionClass }}">
                                    @if($action['priority'] === 'high')
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    {{ $action['label'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Overview Tab -->
            <div x-show="activeTab === 'overview'" class="space-y-6">
                <!-- Underwriting Decision Score Card - Full Width -->
                @if($application->underwriting_score === null)
                <div class="card border-2 border-dashed border-gray-300">
                    <div class="card-body text-center py-8">
                        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">Underwriting Decision Score</h3>
                        <p class="text-gray-500 mb-4">Run bank statement analysis or click below to calculate the underwriting score</p>
                        <button onclick="recalculateUnderwriting()" class="btn btn-primary" id="recalculate-uw-btn">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Calculate Underwriting Score
                        </button>
                    </div>
                </div>
                @elseif($application->underwriting_score)
                <div class="card border-2 {{ $application->underwriting_score >= 75 ? 'border-success-500' : ($application->underwriting_score >= 45 ? 'border-warning-500' : 'border-danger-500') }}">
                    <div class="card-header flex justify-between items-center bg-gray-50">
                        <h3 class="font-semibold text-lg">Underwriting Decision Score</h3>
                        <div class="flex items-center space-x-3">
                            @if($application->underwriting_calculated_at)
                                <span class="text-sm text-gray-500">Last calculated: {{ $application->underwriting_calculated_at->format('M d, Y H:i') }}</span>
                            @endif
                            <button onclick="recalculateUnderwriting()" class="btn btn-sm btn-secondary" id="recalculate-uw-btn">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Recalculate
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                            <!-- Main Score -->
                            <div class="text-center">
                                @php
                                    $uwScore = $application->underwriting_score;
                                    $uwColor = $uwScore >= 75 ? 'text-success-600' : ($uwScore >= 45 ? 'text-warning-600' : 'text-danger-600');
                                    $uwBgColor = $uwScore >= 75 ? 'bg-success-100' : ($uwScore >= 45 ? 'bg-warning-100' : 'bg-danger-100');
                                @endphp
                                <div class="inline-flex items-center justify-center w-32 h-32 rounded-full {{ $uwBgColor }}">
                                    <span class="text-5xl font-bold {{ $uwColor }}">{{ $uwScore }}</span>
                                </div>
                                <div class="mt-2 text-lg font-semibold {{ $uwColor }}">
                                    @if($uwScore >= 75) Strong
                                    @elseif($uwScore >= 60) Good
                                    @elseif($uwScore >= 45) Fair
                                    @elseif($uwScore >= 30) Weak
                                    @else Poor
                                    @endif
                                </div>
                            </div>

                            <!-- Decision -->
                            <div class="text-center border-l pl-6">
                                <div class="text-sm text-gray-500 mb-2">Recommendation</div>
                                @php
                                    $decision = $application->underwriting_decision;
                                    $decisionColors = [
                                        'APPROVE' => 'bg-success-100 text-success-800 border-success-300',
                                        'CONDITIONAL_APPROVE' => 'bg-blue-100 text-blue-800 border-blue-300',
                                        'REVIEW' => 'bg-warning-100 text-warning-800 border-warning-300',
                                        'HIGH_RISK' => 'bg-orange-100 text-orange-800 border-orange-300',
                                        'DECLINE' => 'bg-danger-100 text-danger-800 border-danger-300',
                                    ];
                                    $decisionClass = $decisionColors[$decision] ?? 'bg-gray-100 text-gray-800 border-gray-300';
                                @endphp
                                <span class="inline-block px-4 py-2 rounded-lg border-2 font-bold text-lg {{ $decisionClass }}">
                                    {{ str_replace('_', ' ', $decision) }}
                                </span>
                                @if($application->underwriting_details && isset($application->underwriting_details['decision_details']['message']))
                                    <p class="mt-2 text-sm text-gray-600">{{ $application->underwriting_details['decision_details']['message'] }}</p>
                                @endif
                            </div>

                            <!-- Component Scores -->
                            <div class="border-l pl-6">
                                <div class="text-sm text-gray-500 mb-2">Component Scores</div>
                                @if($application->underwriting_details && isset($application->underwriting_details['component_scores']))
                                    @php
                                        $componentNames = [
                                            'true_revenue' => 'True Revenue',
                                            'cash_flow' => 'Cash Flow',
                                            'balance_quality' => 'Balance Quality',
                                            'transaction_patterns' => 'Patterns',
                                            'risk_indicators' => 'Risk Indicators',
                                        ];
                                    @endphp
                                    <div class="space-y-2">
                                        @foreach($application->underwriting_details['component_scores'] as $key => $score)
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="text-gray-600">{{ $componentNames[$key] ?? $key }}</span>
                                                <div class="flex items-center">
                                                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                                        <div class="h-2 rounded-full {{ $score >= 60 ? 'bg-success-500' : ($score >= 40 ? 'bg-warning-500' : 'bg-danger-500') }}" style="width: {{ $score }}%"></div>
                                                    </div>
                                                    <span class="font-medium w-8 text-right">{{ $score }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <!-- Flags -->
                            <div class="border-l pl-6">
                                <div class="text-sm text-gray-500 mb-2">Key Findings</div>
                                @if($application->underwriting_details && isset($application->underwriting_details['flags']))
                                    <div class="space-y-1 max-h-32 overflow-y-auto">
                                        @forelse(array_slice($application->underwriting_details['flags'], 0, 5) as $flag)
                                            @php
                                                $severityColors = [
                                                    'critical' => 'text-danger-700 bg-danger-50',
                                                    'high' => 'text-orange-700 bg-orange-50',
                                                    'medium' => 'text-warning-700 bg-warning-50',
                                                    'low' => 'text-gray-700 bg-gray-50',
                                                ];
                                                $flagClass = $severityColors[$flag['severity'] ?? 'low'] ?? 'text-gray-700 bg-gray-50';
                                            @endphp
                                            <div class="text-xs px-2 py-1 rounded {{ $flagClass }}">
                                                {{ $flag['message'] }}
                                            </div>
                                        @empty
                                            <div class="text-sm text-success-600">No significant issues found</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Risk Score Card -->
                    <div class="card">
                        <div class="card-header flex justify-between items-center">
                            <h3 class="font-semibold">Risk Assessment</h3>
                            <button onclick="calculateRisk()" class="btn btn-sm btn-primary">Recalculate</button>
                        </div>
                        <div class="card-body text-center">
                            @if($application->overall_risk_score)
                                @php
                                    $riskColor = $application->overall_risk_score >= 70 ? 'text-danger-600' :
                                                 ($application->overall_risk_score >= 40 ? 'text-warning-600' : 'text-success-600');
                                    $riskLevel = $application->overall_risk_score >= 70 ? 'High Risk' :
                                                 ($application->overall_risk_score >= 40 ? 'Medium Risk' : 'Low Risk');
                                @endphp
                                <div class="text-6xl font-bold {{ $riskColor }}">{{ $application->overall_risk_score }}</div>
                                <div class="text-lg {{ $riskColor }}">{{ $riskLevel }}</div>
                            @else
                                <div class="text-gray-400 py-4">
                                    <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    <p>Run verifications to calculate risk</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Funding Request -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="font-semibold">Funding Request</h3>
                        </div>
                        <div class="card-body">
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Requested Amount</span>
                                    <span class="font-semibold">${{ number_format($application->requested_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Monthly Revenue</span>
                                    <span class="font-semibold">${{ number_format($application->monthly_revenue, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Use of Funds</span>
                                    <span>{{ $application->use_of_funds ?? 'Not specified' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="font-semibold">Status Actions</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('applications.update-status', $application) }}">
                                @csrf
                                @method('PATCH')
                                <div class="space-y-3">
                                    <select name="status" class="form-select w-full">
                                        <option value="draft" {{ $application->status == 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="submitted" {{ $application->status == 'submitted' ? 'selected' : '' }}>Submitted</option>
                                        <option value="processing" {{ $application->status == 'processing' ? 'selected' : '' }}>Processing</option>
                                        <option value="under_review" {{ $application->status == 'under_review' ? 'selected' : '' }}>Under Review</option>
                                        <option value="approved" {{ $application->status == 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="declined" {{ $application->status == 'declined' ? 'selected' : '' }}>Declined</option>
                                        <option value="funded" {{ $application->status == 'funded' ? 'selected' : '' }}>Funded</option>
                                        <option value="closed" {{ $application->status == 'closed' ? 'selected' : '' }}>Closed</option>
                                    </select>
                                    <textarea name="notes" placeholder="Add notes (optional)" class="form-input w-full" rows="2"></textarea>
                                    <button type="submit" class="btn btn-primary w-full">Update Status</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Business & Owner Info -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="font-semibold">Business Information</h3>
                        </div>
                        <div class="card-body">
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm text-gray-500">Legal Name</dt>
                                    <dd class="font-medium">{{ $application->business_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">DBA</dt>
                                    <dd>{{ $application->dba_name ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">EIN</dt>
                                    <dd>{{ $application->ein ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Industry</dt>
                                    <dd>{{ $application->industry }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Phone</dt>
                                    <dd>{{ $application->business_phone }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Email</dt>
                                    <dd>{{ $application->business_email }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Start Date</dt>
                                    <dd>{{ \Carbon\Carbon::parse($application->business_start_date)->format('M d, Y') }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-sm text-gray-500">Address</dt>
                                    <dd>{{ $application->business_address }}, {{ $application->business_city }}, {{ $application->business_state }} {{ $application->business_zip }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="font-semibold">Owner Information</h3>
                        </div>
                        <div class="card-body">
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm text-gray-500">Name</dt>
                                    <dd class="font-medium">{{ $application->owner_first_name }} {{ $application->owner_last_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Ownership</dt>
                                    <dd>{{ $application->ownership_percentage }}%</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Email</dt>
                                    <dd>{{ $application->owner_email }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Phone</dt>
                                    <dd>{{ $application->owner_phone }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">SSN Last 4</dt>
                                    <dd>{{ $application->owner_ssn_last4 ? 'XXX-XX-' . $application->owner_ssn_last4 : '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">DOB</dt>
                                    <dd>{{ \Carbon\Carbon::parse($application->owner_dob)->format('M d, Y') }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-sm text-gray-500">Address</dt>
                                    <dd>{{ $application->owner_address }}, {{ $application->owner_city }}, {{ $application->owner_state }} {{ $application->owner_zip }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verifications Tab -->
            <div x-show="activeTab === 'verifications'" class="space-y-6">
                <!-- Run All Verifications Header -->
                <div class="card bg-gradient-to-r from-primary-50 to-blue-50 border-primary-200">
                    <div class="card-body py-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-gray-800 text-lg">Verification Checks</h3>
                                <p class="text-sm text-gray-600 mt-1">Run all required verifications to advance the application to the next phase.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right hidden sm:block">
                                    @php
                                        $verificationProgress = $flowStatus['verification_progress'] ?? [];
                                        $completedCount = collect($verificationProgress)->filter(fn($v) => $v['completed'])->count();
                                        $totalCount = count($verificationProgress);
                                    @endphp
                                    <span class="text-sm text-gray-500">Completed</span>
                                    <div class="font-bold text-lg {{ $completedCount === $totalCount ? 'text-success-600' : 'text-gray-700' }}">
                                        {{ $completedCount }}/{{ $totalCount }}
                                    </div>
                                </div>
                                <button
                                    onclick="runAllVerifications()"
                                    id="run-all-verifications-btn"
                                    class="btn btn-primary whitespace-nowrap"
                                    {{ $completedCount === $totalCount ? 'disabled' : '' }}
                                >
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    Run All Verifications
                                </button>
                            </div>
                        </div>
                        <!-- Progress Bar -->
                        <div class="mt-4">
                            <div class="flex gap-2">
                                @foreach($verificationProgress as $type => $data)
                                    <div class="flex-1">
                                        <div class="h-2 rounded-full {{ $data['completed'] ? 'bg-success-500' : 'bg-gray-200' }}"></div>
                                        <div class="text-xs text-center mt-1 {{ $data['completed'] ? 'text-success-600 font-medium' : 'text-gray-400' }}">
                                            {{ Str::limit($data['label'], 10) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Persona Identity Verification -->
                    <div class="card">
                        <div class="card-header flex justify-between items-center">
                            <h3 class="font-semibold">Identity Verification (Persona)</h3>
                            <button onclick="runVerification('persona')" class="btn btn-sm btn-primary">Run Check</button>
                        </div>
                        <div class="card-body">
                            @if($application->personaInquiries->count())
                                @foreach($application->personaInquiries as $inquiry)
                                    <div class="border-b last:border-0 pb-3 mb-3 last:pb-0 last:mb-0">
                                        <div class="flex justify-between items-center">
                                            <span class="badge {{ $inquiry->status == 'completed' ? 'badge-success' : ($inquiry->status == 'failed' ? 'badge-danger' : 'badge-warning') }}">
                                                {{ ucfirst($inquiry->status) }}
                                            </span>
                                            <span class="text-sm text-gray-500">{{ $inquiry->created_at->format('M d, Y H:i') }}</span>
                                        </div>
                                        @if($inquiry->verification_results)
                                            <div class="mt-2 text-sm">
                                                <pre class="bg-gray-50 p-2 rounded text-xs overflow-x-auto">{{ json_encode($inquiry->verification_results, JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <p class="text-gray-500 text-center py-4">No identity verification run yet</p>
                            @endif
                        </div>
                    </div>

                    <!-- Credit Reports -->
                    <div class="card">
                        <div class="card-header flex justify-between items-center">
                            <h3 class="font-semibold">Credit Reports (Experian)</h3>
                            <div class="space-x-2">
                                <button onclick="runVerification('experian_credit')" class="btn btn-sm btn-primary">Personal</button>
                                <button onclick="runVerification('experian_business')" class="btn btn-sm btn-secondary">Business</button>
                            </div>
                        </div>
                        <div class="card-body">
                            @if($application->creditReports->count())
                                @foreach($application->creditReports as $report)
                                    <div class="border-b last:border-0 pb-3 mb-3 last:pb-0 last:mb-0">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="badge badge-info">{{ ucfirst($report->report_type) }}</span>
                                            <span class="text-sm text-gray-500">{{ $report->created_at->format('M d, Y H:i') }}</span>
                                        </div>
                                        @if($report->credit_score)
                                            <div class="text-3xl font-bold {{ $report->credit_score >= 700 ? 'text-success-600' : ($report->credit_score >= 600 ? 'text-warning-600' : 'text-danger-600') }}">
                                                {{ $report->credit_score }}
                                            </div>
                                        @endif
                                        <div class="grid grid-cols-3 gap-2 mt-2 text-sm">
                                            <div>
                                                <span class="text-gray-500">Open Accounts</span>
                                                <div class="font-medium">{{ $report->open_accounts }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Delinquent</span>
                                                <div class="font-medium {{ $report->delinquent_accounts > 0 ? 'text-danger-600' : '' }}">{{ $report->delinquent_accounts }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Total Debt</span>
                                                <div class="font-medium">${{ number_format($report->total_debt, 0) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-gray-500 text-center py-4">No credit reports pulled yet</p>
                            @endif
                        </div>
                    </div>

                    <!-- DataMerch Stacking -->
                    <div class="card">
                        <div class="card-header flex justify-between items-center">
                            <h3 class="font-semibold">MCA Stacking (DataMerch)</h3>
                            <button onclick="runVerification('datamerch')" class="btn btn-sm btn-primary">Run Check</button>
                        </div>
                        <div class="card-body">
                            @if($application->stackingReports->count())
                                @foreach($application->stackingReports as $report)
                                    <div class="border-b last:border-0 pb-3 mb-3 last:pb-0 last:mb-0">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="badge {{ $report->risk_level == 'high' ? 'badge-danger' : ($report->risk_level == 'medium' ? 'badge-warning' : 'badge-success') }}">
                                                {{ ucfirst($report->risk_level) }} Risk
                                            </span>
                                            <span class="text-sm text-gray-500">{{ $report->created_at->format('M d, Y H:i') }}</span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2 text-sm">
                                            <div>
                                                <span class="text-gray-500">Active MCAs</span>
                                                <div class="font-medium {{ $report->active_mcas > 0 ? 'text-warning-600' : '' }}">{{ $report->active_mcas }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Defaulted</span>
                                                <div class="font-medium {{ $report->defaulted_mcas > 0 ? 'text-danger-600' : '' }}">{{ $report->defaulted_mcas }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Exposure</span>
                                                <div class="font-medium">${{ number_format($report->total_exposure, 0) }}</div>
                                            </div>
                                        </div>
                                        @if($report->recommendation)
                                            <div class="mt-2 p-2 bg-gray-50 rounded text-sm">{{ $report->recommendation }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <p class="text-gray-500 text-center py-4">No stacking report run yet</p>
                            @endif
                        </div>
                    </div>

                    <!-- UCC Filings -->
                    <div class="card">
                        <div class="card-header flex justify-between items-center">
                            <h3 class="font-semibold">UCC Filings</h3>
                            <button onclick="runVerification('ucc')" class="btn btn-sm btn-primary">Run Search</button>
                        </div>
                        <div class="card-body">
                            @if($application->uccReports->count())
                                @foreach($application->uccReports as $report)
                                    <div class="border-b last:border-0 pb-3 mb-3 last:pb-0 last:mb-0">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="badge {{ $report->risk_level == 'high' ? 'badge-danger' : ($report->risk_level == 'medium' ? 'badge-warning' : 'badge-success') }}">
                                                {{ ucfirst($report->risk_level) }} Risk
                                            </span>
                                            <span class="text-sm text-gray-500">{{ $report->created_at->format('M d, Y H:i') }}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            <div>
                                                <span class="text-gray-500">Total Filings</span>
                                                <div class="font-medium">{{ $report->total_filings }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Active</span>
                                                <div class="font-medium">{{ $report->active_filings }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">MCA Related</span>
                                                <div class="font-medium {{ $report->mca_related_filings > 0 ? 'text-warning-600' : '' }}">{{ $report->mca_related_filings }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Blanket Liens</span>
                                                <div class="font-medium {{ $report->blanket_liens > 0 ? 'text-danger-600' : '' }}">{{ $report->blanket_liens }}</div>
                                            </div>
                                        </div>
                                        @if($report->recommendation)
                                            <div class="mt-2 p-2 bg-gray-50 rounded text-sm">{{ $report->recommendation }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <p class="text-gray-500 text-center py-4">No UCC search run yet</p>
                            @endif
                        </div>
                    </div>

                    <!-- PACER Court Records -->
                    <div class="card">
                        <div class="card-header flex justify-between items-center">
                            <h3 class="font-semibold">PACER Court Records</h3>
                            <button onclick="runVerification('pacer')" class="btn btn-sm btn-primary">Run Search</button>
                        </div>
                        <div class="card-body">
                            @if($application->pacerReports->count())
                                @foreach($application->pacerReports as $report)
                                    <div class="border-b last:border-0 pb-3 mb-3 last:pb-0 last:mb-0">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="badge {{ $report->risk_level == 'critical' ? 'badge-danger' : ($report->risk_level == 'high' ? 'badge-danger' : ($report->risk_level == 'medium' ? 'badge-warning' : 'badge-success')) }}">
                                                {{ ucfirst($report->risk_level) }} Risk
                                            </span>
                                            <span class="text-sm text-gray-500">{{ $report->created_at->format('M d, Y H:i') }}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            <div>
                                                <span class="text-gray-500">Total Cases</span>
                                                <div class="font-medium">{{ $report->total_cases }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Bankruptcies</span>
                                                <div class="font-medium {{ $report->bankruptcy_cases > 0 ? 'text-danger-600' : '' }}">{{ $report->bankruptcy_cases }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Civil Cases</span>
                                                <div class="font-medium {{ $report->civil_cases > 3 ? 'text-warning-600' : '' }}">{{ $report->civil_cases }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Judgments</span>
                                                <div class="font-medium {{ $report->judgments > 0 ? 'text-danger-600' : '' }}">{{ $report->judgments }}</div>
                                            </div>
                                        </div>
                                        @if($report->flags && count($report->flags) > 0)
                                            <div class="mt-2">
                                                @foreach($report->flags as $flag)
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-red-100 text-red-800 mr-1 mb-1">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                        </svg>
                                                        {{ $flag }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($report->recommendation)
                                            <div class="mt-2 p-2 bg-gray-50 rounded text-sm">{{ $report->recommendation }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <p class="text-gray-500 text-center py-4">No PACER search run yet</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Banking Tab -->
            <div x-show="activeTab === 'banking'" class="space-y-6" x-data="bankingTab()">
                <!-- Send Bank Link Request Card -->
                <div class="card">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="font-semibold">Request Bank Connection from Merchant</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-gray-600 mb-4">Send a secure link to the merchant to connect their bank account via Plaid.</p>
                        <div class="flex items-end gap-4">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Merchant Email</label>
                                <input type="email" x-model="sendEmail" class="form-input w-full" placeholder="{{ $application->owner_email }}" value="{{ $application->owner_email }}">
                            </div>
                            <button @click="sendBankLinkRequest()" :disabled="sending" class="btn btn-primary">
                                <template x-if="!sending">
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        Send Request
                                    </span>
                                </template>
                                <template x-if="sending">
                                    <span class="flex items-center">
                                        <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Sending...
                                    </span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bank Link Request History -->
                <div class="card">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="font-semibold">Bank Link Requests</h3>
                        <button @click="loadBankStatus()" class="btn btn-sm btn-outline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                    <div class="card-body">
                        <template x-if="loading">
                            <div class="text-center py-8">
                                <svg class="animate-spin h-8 w-8 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </template>
                        <template x-if="!loading && linkRequests.length === 0">
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <p>No bank link requests sent yet</p>
                            </div>
                        </template>
                        <template x-if="!loading && linkRequests.length > 0">
                            <div class="space-y-3">
                                <template x-for="request in linkRequests" :key="request.id">
                                    <div class="border rounded-lg p-4">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <span x-text="request.email" class="font-medium"></span>
                                                <span class="badge ml-2" :class="{
                                                    'badge-secondary': request.status === 'pending',
                                                    'badge-info': request.status === 'sent',
                                                    'badge-warning': request.status === 'opened',
                                                    'badge-success': request.status === 'completed',
                                                    'badge-danger': request.status === 'failed' || request.status === 'expired'
                                                }" x-text="request.status.charAt(0).toUpperCase() + request.status.slice(1)"></span>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                Expires: <span x-text="request.expires_at"></span>
                                            </div>
                                        </div>
                                        <div class="text-sm text-gray-600 space-y-1">
                                            <template x-if="request.sent_at">
                                                <div>Sent: <span x-text="request.sent_at"></span></div>
                                            </template>
                                            <template x-if="request.opened_at">
                                                <div>Opened: <span x-text="request.opened_at"></span></div>
                                            </template>
                                            <template x-if="request.completed_at">
                                                <div class="text-green-600">Completed: <span x-text="request.completed_at"></span></div>
                                            </template>
                                            <template x-if="request.institution_name">
                                                <div class="font-medium text-green-600">Connected: <span x-text="request.institution_name"></span></div>
                                            </template>
                                        </div>
                                        <template x-if="request.link_url && !request.is_expired && request.status !== 'completed'">
                                            <div class="mt-3 flex gap-2">
                                                <button @click="copyLink(request.link_url)" class="btn btn-sm btn-outline">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                                    </svg>
                                                    Copy Link
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Connected Bank Accounts -->
                <div class="card">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="font-semibold">Connected Bank Accounts</h3>
                    </div>
                    <div class="card-body">
                        <template x-if="!loading && connectedBanks.length === 0">
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                <p>No bank accounts connected yet</p>
                            </div>
                        </template>
                        <template x-if="!loading && connectedBanks.length > 0">
                            <div class="space-y-4">
                                <template x-for="bank in connectedBanks" :key="bank.id">
                                    <div class="border rounded-lg p-4">
                                        <div class="flex justify-between items-center mb-3">
                                            <h4 class="font-semibold" x-text="bank.institution_name"></h4>
                                            <span class="badge badge-success">Connected</span>
                                        </div>
                                        <div class="grid gap-2">
                                            <template x-for="account in bank.accounts" :key="account.mask">
                                                <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                                    <div>
                                                        <span class="font-medium" x-text="account.name"></span>
                                                        <span class="text-sm text-gray-500 ml-2" x-text="'****' + account.mask"></span>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="font-semibold" x-text="'$' + parseFloat(account.balance).toLocaleString('en-US', {minimumFractionDigits: 2})"></div>
                                                        <div class="text-xs text-gray-500" x-text="account.type"></div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Documents Tab -->
            <div x-show="activeTab === 'documents'" class="space-y-6" x-data="documentsTab()">
                <!-- Upload Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold">Upload Document</h3>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="uploadDocument" enctype="multipart/form-data">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                                    <select x-model="documentType" @change="statementPeriod = ''" class="form-select w-full" required>
                                        <option value="">Select type...</option>
                                        <option value="bank_statement">Bank Statement</option>
                                        <option value="tax_return">Tax Return</option>
                                        <option value="business_license">Business License</option>
                                        <option value="drivers_license">Driver's License</option>
                                        <option value="voided_check">Voided Check</option>
                                        <option value="lease_agreement">Lease Agreement</option>
                                        <option value="credit_card_statement">Credit Card Statement</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div x-show="documentType === 'bank_statement'" x-transition>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Statement Month</label>
                                    <input type="month" x-model="statementPeriod" class="form-input w-full" :required="documentType === 'bank_statement'" max="{{ date('Y-m') }}">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Select File</label>
                                    <input type="file" @change="selectedFile = $event.target.files[0]" class="form-input w-full" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv" required>
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" :disabled="uploading || !selectedFile || !documentType || (documentType === 'bank_statement' && !statementPeriod)" class="btn btn-primary w-full flex items-center justify-center">
                                        <span x-show="!uploading" class="flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                            </svg>
                                            Upload
                                        </span>
                                        <span x-show="uploading" class="flex items-center">
                                            <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Uploading...
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">Accepted formats: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, CSV (max 20MB)</p>
                        </form>
                    </div>
                </div>

                <!-- Bank Statement Analysis -->
                <div class="card" x-show="hasBankStatements">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="font-semibold">Bank Statement Analysis</h3>
                        <button @click="runAnalysis()" :disabled="analyzing" class="btn btn-primary flex items-center">
                            <span x-show="!analyzing" class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Run Analysis
                            </span>
                            <span x-show="analyzing" class="flex items-center">
                                <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Analyzing...
                            </span>
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Analysis Results -->
                        <template x-if="analysisResults">
                            <div class="space-y-4">
                                <!-- Summary Cards -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="bg-green-50 rounded-lg p-4 text-center">
                                        <div class="text-2xl font-bold text-green-600" x-text="'$' + analysisResults.summary.total_true_revenue.toLocaleString('en-US', {minimumFractionDigits: 2})"></div>
                                        <div class="text-sm text-green-700">True Revenue</div>
                                    </div>
                                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                                        <div class="text-2xl font-bold text-blue-600" x-text="'$' + analysisResults.summary.total_credits.toLocaleString('en-US', {minimumFractionDigits: 2})"></div>
                                        <div class="text-sm text-blue-700">Total Credits</div>
                                    </div>
                                    <div class="bg-red-50 rounded-lg p-4 text-center">
                                        <div class="text-2xl font-bold text-red-600" x-text="'$' + analysisResults.summary.total_debits.toLocaleString('en-US', {minimumFractionDigits: 2})"></div>
                                        <div class="text-sm text-red-700">Total Debits</div>
                                    </div>
                                    <div class="bg-purple-50 rounded-lg p-4 text-center">
                                        <div class="text-2xl font-bold text-purple-600" x-text="analysisResults.summary.total_transactions"></div>
                                        <div class="text-sm text-purple-700">Transactions</div>
                                    </div>
                                </div>

                                <!-- Statement Results -->
                                <div class="border rounded-lg overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left">Statement</th>
                                                <th class="px-4 py-2 text-left">Period</th>
                                                <th class="px-4 py-2 text-right">Credits</th>
                                                <th class="px-4 py-2 text-right">Debits</th>
                                                <th class="px-4 py-2 text-right">True Revenue</th>
                                                <th class="px-4 py-2 text-center">Analyzed</th>
                                                <th class="px-4 py-2 text-center">Status</th>
                                                <th class="px-4 py-2 text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="result in analysisResults.results" :key="result.document_id">
                                                <tr class="border-t hover:bg-gray-50">
                                                    <td class="px-4 py-2" x-text="result.filename"></td>
                                                    <td class="px-4 py-2">
                                                        <span class="font-medium" x-text="result.statement_period_label || result.statement_period || 'N/A'"></span>
                                                    </td>
                                                    <td class="px-4 py-2 text-right" x-text="result.success ? '$' + result.total_credits.toLocaleString('en-US', {minimumFractionDigits: 2}) : '-'"></td>
                                                    <td class="px-4 py-2 text-right" x-text="result.success ? '$' + result.total_debits.toLocaleString('en-US', {minimumFractionDigits: 2}) : '-'"></td>
                                                    <td class="px-4 py-2 text-right font-semibold text-green-600" x-text="result.success ? '$' + result.true_revenue.toLocaleString('en-US', {minimumFractionDigits: 2}) : '-'"></td>
                                                    <td class="px-4 py-2 text-center text-xs text-gray-500" x-text="result.analyzed_at || '-'"></td>
                                                    <td class="px-4 py-2 text-center">
                                                        <span x-show="result.success" class="badge badge-success">Analyzed</span>
                                                        <span x-show="!result.success" class="badge badge-danger" x-text="result.error || 'Failed'"></span>
                                                    </td>
                                                    <td class="px-4 py-2 text-center">
                                                        <button x-show="result.success" @click.stop.prevent="viewTransactions(result.document_id)" class="text-blue-600 hover:text-blue-800 cursor-pointer" title="View Transactions">
                                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Download & Email FCS Buttons -->
                                <div x-show="analysisResults.fcs_url" class="flex justify-center gap-3">
                                    <a :href="analysisResults.fcs_url" target="_blank" class="btn btn-success">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        Download FCS
                                    </a>
                                    <button @click="showEmailFcsModal = true" class="btn btn-primary">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        Email FCS
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- No Analysis Yet -->
                        <template x-if="!analysisResults">
                            <div class="text-center py-6 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <p class="font-medium">Click "Run Analysis" to analyze bank statements</p>
                                <p class="text-sm mt-1">This will extract transactions, calculate true revenue, and generate an FCS report</p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Documents List -->
                <div class="card">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="font-semibold">Uploaded Documents</h3>
                        <button @click="loadDocuments()" class="btn btn-sm btn-outline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                    <div class="card-body">
                        <template x-if="loading">
                            <div class="text-center py-8">
                                <svg class="animate-spin h-8 w-8 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </template>
                        <template x-if="!loading && documents.length === 0">
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p>No documents uploaded yet</p>
                            </div>
                        </template>
                        <template x-if="!loading && documents.length > 0">
                            <div class="space-y-2">
                                <template x-for="doc in documents" :key="doc.id">
                                    <div class="border rounded-lg hover:bg-gray-50">
                                        <div class="flex items-center justify-between p-3">
                                            <div class="flex items-center">
                                                <svg class="w-8 h-8 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                                <div>
                                                    <div class="font-medium flex items-center">
                                                        <span x-text="doc.original_filename"></span>
                                                        <span x-show="doc.is_analyzed" class="ml-2 badge badge-success text-xs">Analyzed</span>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <span x-text="doc.document_type_label"></span> -
                                                        <span x-text="doc.file_size"></span> -
                                                        <span x-text="doc.created_at"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <!-- View Button -->
                                                <a :href="doc.view_url" target="_blank" class="btn btn-sm btn-primary" title="View">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </a>
                                                <!-- Download Button -->
                                                <a :href="doc.download_url" class="btn btn-sm btn-secondary" download title="Download">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                    </svg>
                                                </a>
                                                <!-- Delete Button -->
                                                <button @click="deleteDocument(doc.id)" class="btn btn-sm btn-danger" :disabled="deleting === doc.id" title="Delete">
                                                    <svg x-show="deleting !== doc.id" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    <svg x-show="deleting === doc.id" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Analysis Details for Bank Statements -->
                                        <template x-if="doc.document_type === 'bank_statement' && doc.is_analyzed">
                                            <div class="px-3 pb-3 pt-0">
                                                <div class="bg-gray-50 rounded p-3 text-sm">
                                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                                                        <div>
                                                            <span class="text-gray-500">Period:</span>
                                                            <span class="font-medium ml-1" x-text="doc.statement_period_label || doc.statement_period || 'N/A'"></span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-500">True Revenue:</span>
                                                            <span class="font-semibold text-green-600 ml-1" x-text="'$' + (doc.true_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-500">Credits:</span>
                                                            <span class="font-medium text-blue-600 ml-1" x-text="'$' + (doc.total_credits || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-500">Debits:</span>
                                                            <span class="font-medium text-red-600 ml-1" x-text="'$' + (doc.total_debits || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-500">Analyzed:</span>
                                                            <span class="text-gray-700 ml-1" x-text="doc.analyzed_at"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Transaction Details Modal -->
                <div x-show="showTransactionModal" x-cloak class="fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <!-- Background overlay -->
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showTransactionModal = false"></div>

                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <!-- Modal panel -->
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full relative z-[10000]">

                            <!-- Modal Header -->
                            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900" x-text="transactionData?.document?.filename || 'Transaction Details'"></h3>
                                    <p class="text-sm text-gray-500">
                                        <span x-text="transactionData?.document?.statement_period_label || ''"></span>
                                        <span x-show="transactionData?.document?.analyzed_at"> - Analyzed: <span x-text="transactionData?.document?.analyzed_at"></span></span>
                                    </p>
                                </div>
                                <button @click="showTransactionModal = false" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Modal Body -->
                            <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                                <!-- Loading State -->
                                <template x-if="loadingTransactions">
                                    <div class="text-center py-8">
                                        <svg class="animate-spin h-8 w-8 mx-auto text-primary-500" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <p class="mt-2 text-gray-500">Loading transactions...</p>
                                    </div>
                                </template>

                                <!-- Transaction Content -->
                                <template x-if="!loadingTransactions && transactionData">
                                    <div class="space-y-4">
                                        <!-- Summary Cards -->
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            <div class="bg-green-50 rounded-lg p-3 text-center">
                                                <div class="text-xl font-bold text-green-600" x-text="'$' + (transactionData.summary?.true_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></div>
                                                <div class="text-xs text-green-700">True Revenue</div>
                                            </div>
                                            <div class="bg-blue-50 rounded-lg p-3 text-center">
                                                <div class="text-xl font-bold text-blue-600" x-text="'$' + (transactionData.summary?.total_credits || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></div>
                                                <div class="text-xs text-blue-700">Credits (<span x-text="transactionData.summary?.credit_count || 0"></span>)</div>
                                            </div>
                                            <div class="bg-red-50 rounded-lg p-3 text-center">
                                                <div class="text-xl font-bold text-red-600" x-text="'$' + (transactionData.summary?.total_debits || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></div>
                                                <div class="text-xs text-red-700">Debits (<span x-text="transactionData.summary?.debit_count || 0"></span>)</div>
                                            </div>
                                            <div class="bg-purple-50 rounded-lg p-3 text-center">
                                                <div class="text-xl font-bold text-purple-600" x-text="transactionData.summary?.total_transactions || 0"></div>
                                                <div class="text-xs text-purple-700">Total Transactions</div>
                                            </div>
                                        </div>

                                        <!-- Filter Tabs -->
                                        <div class="flex space-x-2 border-b">
                                            <button @click="transactionFilter = 'all'" :class="transactionFilter === 'all' ? 'border-b-2 border-primary-500 text-primary-600' : 'text-gray-500'" class="px-4 py-2 text-sm font-medium">
                                                All (<span x-text="transactionData.transactions?.length || 0"></span>)
                                            </button>
                                            <button @click="transactionFilter = 'credit'" :class="transactionFilter === 'credit' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500'" class="px-4 py-2 text-sm font-medium">
                                                Credits (<span x-text="transactionData.summary?.credit_count || 0"></span>)
                                            </button>
                                            <button @click="transactionFilter = 'debit'" :class="transactionFilter === 'debit' ? 'border-b-2 border-red-500 text-red-600' : 'text-gray-500'" class="px-4 py-2 text-sm font-medium">
                                                Debits (<span x-text="transactionData.summary?.debit_count || 0"></span>)
                                            </button>
                                        </div>

                                        <!-- Transactions Table -->
                                        <div class="border rounded-lg overflow-hidden">
                                            <table class="w-full text-sm">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-center w-16">Include</th>
                                                        <th class="px-3 py-2 text-left">Date</th>
                                                        <th class="px-3 py-2 text-left">Description</th>
                                                        <th class="px-3 py-2 text-right">Amount</th>
                                                        <th class="px-3 py-2 text-center">Type</th>
                                                        <th class="px-3 py-2 text-center">Confidence</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="txn in filteredTransactions" :key="txn.id">
                                                        <tr class="border-t hover:bg-gray-50" :class="txn.exclude_from_revenue ? 'bg-gray-100 opacity-60' : ''">
                                                            <td class="px-3 py-2 text-center">
                                                                <input type="checkbox"
                                                                    :checked="!txn.exclude_from_revenue"
                                                                    @change="toggleTransactionExclusion(txn)"
                                                                    class="w-4 h-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500"
                                                                    title="Include in true revenue calculation">
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap" x-text="txn.date_formatted"></td>
                                                            <td class="px-3 py-2">
                                                                <div class="max-w-sm truncate" :class="txn.exclude_from_revenue ? 'line-through' : ''" x-text="txn.description" :title="txn.description"></div>
                                                                <div x-show="txn.merchant_name" class="text-xs text-gray-500" x-text="txn.merchant_name"></div>
                                                                <div x-show="txn.was_corrected" class="text-xs text-orange-500">
                                                                    <span class="inline-flex items-center">
                                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                                                                        Modified
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td class="px-3 py-2 text-right whitespace-nowrap" :class="txn.type === 'credit' ? 'text-blue-600' : 'text-red-600'">
                                                                <span x-text="(txn.type === 'debit' ? '-' : '') + '$' + txn.amount.toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <button @click="toggleTransactionType(txn)"
                                                                    :class="txn.type === 'credit' ? 'bg-blue-100 text-blue-800 hover:bg-blue-200' : 'bg-red-100 text-red-800 hover:bg-red-200'"
                                                                    class="px-2 py-1 rounded-full text-xs font-medium cursor-pointer transition-colors"
                                                                    :title="'Click to change to ' + (txn.type === 'credit' ? 'debit' : 'credit')">
                                                                    <span x-text="txn.type"></span>
                                                                    <svg class="inline w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                                                    </svg>
                                                                </button>
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <span :class="{
                                                                    'bg-green-100 text-green-800': txn.confidence >= 80,
                                                                    'bg-yellow-100 text-yellow-800': txn.confidence >= 50 && txn.confidence < 80,
                                                                    'bg-red-100 text-red-800': txn.confidence < 50
                                                                }" class="px-2 py-1 rounded-full text-xs font-medium" x-text="txn.confidence + '%'"></span>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Pending Changes Notice -->
                                        <div x-show="hasTransactionChanges" x-cloak class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center justify-between">
                                            <div class="flex items-center text-yellow-800">
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-sm font-medium">You have unsaved changes. Click "Recalculate" to update the true revenue.</span>
                                            </div>
                                            <button @click="recalculateTrueRevenue()"
                                                :disabled="recalculating"
                                                class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 disabled:opacity-50 text-sm font-medium flex items-center">
                                                <template x-if="recalculating">
                                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </template>
                                                <span x-text="recalculating ? 'Recalculating...' : 'Recalculate True Revenue'"></span>
                                            </button>
                                        </div>

                                        <!-- No Transactions -->
                                        <div x-show="!transactionData.transactions || transactionData.transactions.length === 0" class="text-center py-8 text-gray-500">
                                            No transactions found for this statement.
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Modal Footer -->
                            <div class="bg-gray-50 px-6 py-3 flex justify-end">
                                <button @click="showTransactionModal = false" class="btn btn-secondary">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email FCS Modal -->
                <div x-show="showEmailFcsModal" x-cloak class="fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="email-fcs-modal" role="dialog" aria-modal="true">
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div x-show="showEmailFcsModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showEmailFcsModal = false"></div>

                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div x-show="showEmailFcsModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-6 py-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Email FCS Report</h3>
                                    <button @click="showEmailFcsModal = false" class="text-gray-400 hover:text-gray-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Recipient Email</label>
                                        <input type="email" x-model="emailFcsTo" class="form-input w-full" placeholder="email@example.com" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Message (Optional)</label>
                                        <textarea x-model="emailFcsMessage" class="form-input w-full" rows="3" placeholder="Add a personal message..."></textarea>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg text-sm">
                                        <p class="text-gray-600"><strong>Attachment:</strong> FCS Report PDF</p>
                                        <p class="text-gray-500 mt-1">Includes underwriting score and bank analysis summary</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-6 py-3 flex justify-end gap-3">
                                <button @click="showEmailFcsModal = false" class="btn btn-secondary" :disabled="sendingEmail">Cancel</button>
                                <button @click="sendFcsEmail()" class="btn btn-primary" :disabled="sendingEmail || !emailFcsTo">
                                    <template x-if="sendingEmail">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </template>
                                    <span x-text="sendingEmail ? 'Sending...' : 'Send Email'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Tab -->
            <div x-show="activeTab === 'notes'" class="space-y-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold">Add Note</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('applications.add-note', $application) }}">
                            @csrf
                            <textarea name="content" placeholder="Add a note..." class="form-input w-full" rows="3" required></textarea>
                            <div class="mt-3 flex justify-end">
                                <button type="submit" class="btn btn-primary">Add Note</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold">Activity & Notes</h3>
                    </div>
                    <div class="card-body">
                        @if($application->notes && $application->notes->count())
                            <div class="space-y-4">
                                @foreach($application->notes->sortByDesc('created_at') as $note)
                                    <div class="border-l-4 {{ $note->type == 'system' ? 'border-gray-300' : ($note->type == 'status_change' ? 'border-primary-500' : 'border-success-500') }} pl-4 py-2">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="badge badge-secondary text-xs">{{ ucfirst(str_replace('_', ' ', $note->type)) }}</span>
                                                @if($note->user)
                                                    <span class="text-sm text-gray-500 ml-2">by {{ $note->user->name }}</span>
                                                @endif
                                            </div>
                                            <span class="text-sm text-gray-500">{{ $note->created_at->format('M d, Y H:i') }}</span>
                                        </div>
                                        <p class="mt-1">{{ $note->content }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">No notes yet</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    @push('scripts')
    <script>
        // Toast Notification System
        const Toast = {
            container: null,

            init() {
                this.container = document.getElementById('toast-container');
            },

            show(message, type = 'success', duration = 5000) {
                if (!this.container) this.init();

                const toast = document.createElement('div');
                toast.className = `toast-notification transform translate-x-full transition-all duration-300 ease-out`;

                const colors = {
                    success: 'bg-success-500',
                    error: 'bg-danger-500',
                    warning: 'bg-warning-500',
                    info: 'bg-primary-500'
                };

                const icons = {
                    success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                    error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                    warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
                    info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                };

                // Handle multiline messages
                const formattedMessage = message.replace(/\n/g, '<br>');

                toast.innerHTML = `
                    <div class="${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg flex items-start max-w-md">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            ${icons[type]}
                        </svg>
                        <div class="flex-1 text-sm">${formattedMessage}</div>
                        <button onclick="Toast.dismiss(this.closest('.toast-notification'))" class="ml-3 flex-shrink-0 text-white/80 hover:text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                `;

                this.container.appendChild(toast);

                // Trigger animation
                requestAnimationFrame(() => {
                    toast.classList.remove('translate-x-full');
                    toast.classList.add('translate-x-0');
                });

                // Auto dismiss
                if (duration > 0) {
                    setTimeout(() => this.dismiss(toast), duration);
                }

                return toast;
            },

            dismiss(toast) {
                if (!toast) return;
                toast.classList.remove('translate-x-0');
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            },

            success(message, duration) {
                return this.show(message, 'success', duration);
            },

            error(message, duration) {
                return this.show(message, 'error', duration || 8000);
            },

            warning(message, duration) {
                return this.show(message, 'warning', duration);
            },

            info(message, duration) {
                return this.show(message, 'info', duration);
            }
        };

        // Initialize toast on page load
        document.addEventListener('DOMContentLoaded', () => Toast.init());

        function runVerification(type) {
            Toast.info('Running verification...', 0);

            fetch('{{ route("applications.run-verification", $application) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ type: type })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Toast.success(data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Toast.error('Error: ' + data.message);
                }
            })
            .catch(error => {
                Toast.error('Error running verification');
                console.error(error);
            });
        }

        function runAllVerifications() {
            const btn = document.getElementById('run-all-verifications-btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Running All Verifications...
            `;

            Toast.info('Running all verifications. This may take a moment...', 0);

            fetch('{{ route("applications.run-all-verifications", $application) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Clear the loading toast
                document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));

                if (data.success) {
                    // Show individual results
                    const results = data.results || {};
                    let successCount = 0;
                    let failCount = 0;

                    for (const [type, result] of Object.entries(results)) {
                        if (result.success) {
                            Toast.success(`${result.label} completed`);
                            successCount++;
                        } else {
                            Toast.error(`${result.label}: ${result.error || 'Failed'}`);
                            failCount++;
                        }
                    }

                    // Show status advancement
                    if (data.flow_advanced) {
                        setTimeout(() => {
                            Toast.info(`Application advanced to: ${data.current_status.replace('_', ' ')}`);
                        }, 500);
                    }

                    setTimeout(() => location.reload(), 3000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    Toast.error(data.message || 'Failed to run verifications');
                }
            })
            .catch(error => {
                document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                btn.disabled = false;
                btn.innerHTML = originalText;
                Toast.error('Error running verifications');
                console.error(error);
            });
        }

        function calculateRisk() {
            Toast.info('Calculating risk score...', 0);

            fetch('{{ route("applications.calculate-risk", $application) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                if (data.success) {
                    Toast.success('Risk score calculated: ' + data.data.overall_score);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Toast.error('Error calculating risk');
                }
            })
            .catch(error => {
                document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                Toast.error('Error calculating risk');
                console.error(error);
            });
        }

        function recalculateUnderwriting() {
            const btn = document.getElementById('recalculate-uw-btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Calculating...';

            Toast.info('Calculating underwriting score...', 0);

            fetch('{{ route("applications.underwriting-score", $application) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                if (data.success) {
                    Toast.success(`Underwriting score: ${data.score}/100 - ${data.decision}`);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    Toast.error(data.error || 'Failed to calculate underwriting score');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                Toast.error('Error calculating underwriting score');
                console.error(error);
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }

        function bankingTab() {
            return {
                loading: true,
                sending: false,
                sendEmail: '{{ $application->owner_email }}',
                linkRequests: [],
                connectedBanks: [],

                init() {
                    this.loadBankStatus();
                },

                async loadBankStatus() {
                    this.loading = true;
                    try {
                        const response = await fetch('{{ route("applications.bank-status", $application) }}');
                        const data = await response.json();
                        if (data.success) {
                            this.linkRequests = data.link_requests;
                            this.connectedBanks = data.connected_banks;
                        }
                    } catch (error) {
                        console.error('Error loading bank status:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async sendBankLinkRequest() {
                    if (!this.sendEmail) {
                        Toast.warning('Please enter an email address');
                        return;
                    }

                    this.sending = true;
                    try {
                        const response = await fetch('{{ route("applications.send-bank-link", $application) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ email: this.sendEmail })
                        });

                        const data = await response.json();
                        if (data.success) {
                            Toast.success(data.message);
                            this.loadBankStatus();
                        } else {
                            Toast.error('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error sending bank link request:', error);
                        Toast.error('Failed to send bank link request');
                    } finally {
                        this.sending = false;
                    }
                },

                copyLink(url) {
                    navigator.clipboard.writeText(url).then(() => {
                        Toast.success('Link copied to clipboard!');
                    }).catch(err => {
                        console.error('Failed to copy:', err);
                        prompt('Copy this link:', url);
                    });
                }
            };
        }

        function documentsTab() {
            return {
                loading: true,
                uploading: false,
                analyzing: false,
                deleting: null,
                documents: [],
                documentType: '',
                statementPeriod: '',
                selectedFile: null,
                hasBankStatements: false,
                analysisResults: null,
                showTransactionModal: false,
                loadingTransactions: false,
                transactionData: null,
                transactionFilter: 'all',
                hasTransactionChanges: false,
                recalculating: false,
                currentDocumentId: null,
                // Email FCS Modal
                showEmailFcsModal: false,
                emailFcsTo: '{{ $application->business_email ?? $application->owner_email }}',
                emailFcsMessage: '',
                sendingEmail: false,

                get filteredTransactions() {
                    if (!this.transactionData?.transactions) return [];
                    if (this.transactionFilter === 'all') return this.transactionData.transactions;
                    return this.transactionData.transactions.filter(t => t.type === this.transactionFilter);
                },

                init() {
                    this.loadDocuments();
                },

                async sendFcsEmail() {
                    if (!this.emailFcsTo) {
                        Toast.warning('Please enter an email address');
                        return;
                    }
                    this.sendingEmail = true;
                    Toast.info('Sending FCS report...', 0);
                    try {
                        const response = await fetch('{{ route("applications.send-fcs", $application) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                email: this.emailFcsTo,
                                message: this.emailFcsMessage
                            })
                        });
                        document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                        const data = await response.json();
                        if (data.success) {
                            Toast.success(data.message);
                            this.showEmailFcsModal = false;
                            this.emailFcsMessage = '';
                        } else {
                            Toast.error(data.error || 'Failed to send email');
                        }
                    } catch (error) {
                        document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                        console.error('Error sending FCS email:', error);
                        Toast.error('Failed to send email');
                    } finally {
                        this.sendingEmail = false;
                    }
                },

                async loadDocuments() {
                    this.loading = true;
                    try {
                        const response = await fetch('{{ route("applications.documents", $application) }}');
                        const data = await response.json();
                        if (data.success) {
                            this.documents = data.documents;
                            // Check if there are bank statements
                            this.hasBankStatements = this.documents.some(doc => doc.document_type === 'bank_statement' && doc.mime_type === 'application/pdf');
                            // Load saved analysis results if available
                            if (data.saved_analysis) {
                                this.analysisResults = data.saved_analysis;
                            }
                        }
                    } catch (error) {
                        console.error('Error loading documents:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async runAnalysis() {
                    if (this.analyzing) return;

                    this.analyzing = true;
                    Toast.info('Analyzing bank statements. This may take a few minutes...', 0);

                    try {
                        const response = await fetch('{{ route("applications.analyze-bank", $application) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                        const data = await response.json();
                        if (data.success) {
                            this.analysisResults = data;
                            Toast.success(data.message);
                            // Refresh documents to show updated analysis status
                            this.loadDocuments();
                        } else {
                            Toast.error(data.message);
                        }
                    } catch (error) {
                        document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                        console.error('Error running analysis:', error);
                        Toast.error('Failed to run bank statement analysis');
                    } finally {
                        this.analyzing = false;
                    }
                },

                async uploadDocument() {
                    if (!this.selectedFile || !this.documentType) {
                        Toast.warning('Please select a file and document type');
                        return;
                    }

                    if (this.documentType === 'bank_statement' && !this.statementPeriod) {
                        Toast.warning('Please select the statement month for bank statements');
                        return;
                    }

                    this.uploading = true;
                    Toast.info('Uploading document...', 0);

                    try {
                        const formData = new FormData();
                        formData.append('document', this.selectedFile);
                        formData.append('document_type', this.documentType);
                        if (this.statementPeriod) {
                            formData.append('statement_period', this.statementPeriod);
                        }

                        const response = await fetch('{{ route("applications.upload-document", $application) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: formData
                        });

                        document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                        const data = await response.json();
                        if (data.success) {
                            Toast.success(data.message);
                            this.documentType = '';
                            this.statementPeriod = '';
                            this.selectedFile = null;
                            // Reset file input
                            document.querySelector('input[type="file"]').value = '';
                            this.loadDocuments();
                        } else {
                            Toast.error(data.message);
                        }
                    } catch (error) {
                        document.querySelectorAll('.toast-notification').forEach(t => Toast.dismiss(t));
                        console.error('Error uploading document:', error);
                        Toast.error('Failed to upload document');
                    } finally {
                        this.uploading = false;
                    }
                },

                async deleteDocument(documentId) {
                    if (!confirm('Are you sure you want to delete this document?')) {
                        return;
                    }

                    this.deleting = documentId;
                    try {
                        const response = await fetch(`{{ url('applications/' . $application->id . '/documents') }}/${documentId}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        const data = await response.json();
                        if (data.success) {
                            Toast.success('Document deleted');
                            this.loadDocuments();
                        } else {
                            Toast.error(data.message);
                        }
                    } catch (error) {
                        console.error('Error deleting document:', error);
                        Toast.error('Failed to delete document');
                    } finally {
                        this.deleting = null;
                    }
                },

                async viewTransactions(documentId) {
                    this.showTransactionModal = true;
                    this.loadingTransactions = true;
                    this.transactionData = null;
                    this.transactionFilter = 'all';
                    this.hasTransactionChanges = false;
                    this.currentDocumentId = documentId;

                    const url = `{{ url('applications/' . $application->id . '/documents') }}/${documentId}/transactions`;

                    fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Failed to load transactions');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            this.transactionData = data;
                        } else {
                            Toast.error(data.message);
                            this.showTransactionModal = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Toast.error('Failed to load transaction details');
                        this.showTransactionModal = false;
                    })
                    .finally(() => {
                        this.loadingTransactions = false;
                    });
                },

                async toggleTransactionExclusion(txn) {
                    const newExcludeValue = !txn.exclude_from_revenue;
                    const url = `{{ url('applications/' . $application->id . '/documents') }}/${this.currentDocumentId}/transactions/${txn.id}`;

                    try {
                        const response = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                exclude_from_revenue: newExcludeValue,
                                exclusion_reason: newExcludeValue ? 'Manually excluded' : null
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            txn.exclude_from_revenue = data.transaction.exclude_from_revenue;
                            txn.exclusion_reason = data.transaction.exclusion_reason;
                            this.hasTransactionChanges = true;
                            Toast.success(newExcludeValue ? 'Transaction excluded from revenue' : 'Transaction included in revenue');
                        } else {
                            Toast.error(data.message || 'Failed to update transaction');
                        }
                    } catch (error) {
                        console.error('Error updating transaction:', error);
                        Toast.error('Failed to update transaction');
                    }
                },

                async toggleTransactionType(txn) {
                    const newType = txn.type === 'credit' ? 'debit' : 'credit';
                    const url = `{{ url('applications/' . $application->id . '/documents') }}/${this.currentDocumentId}/transactions/${txn.id}`;

                    try {
                        const response = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                type: newType
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            txn.type = data.transaction.type;
                            txn.was_corrected = data.transaction.was_corrected;
                            this.hasTransactionChanges = true;
                            Toast.success(`Transaction type changed to ${newType}`);
                        } else {
                            Toast.error(data.message || 'Failed to update transaction');
                        }
                    } catch (error) {
                        console.error('Error updating transaction:', error);
                        Toast.error('Failed to update transaction');
                    }
                },

                async recalculateTrueRevenue() {
                    if (!this.currentDocumentId) {
                        Toast.error('No document selected');
                        return;
                    }

                    this.recalculating = true;
                    const url = `{{ url('applications/' . $application->id . '/documents') }}/${this.currentDocumentId}/recalculate-revenue`;

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        const data = await response.json();
                        if (data.success) {
                            // Update the summary in the modal
                            if (this.transactionData?.summary) {
                                this.transactionData.summary.true_revenue = data.document.true_revenue;
                                this.transactionData.summary.total_credits = data.document.total_credits;
                                this.transactionData.summary.total_debits = data.document.total_debits;
                            }

                            // Update the document in the documents list
                            const doc = this.documents.find(d => d.id === this.currentDocumentId);
                            if (doc) {
                                doc.true_revenue = data.document.true_revenue;
                            }

                            // Update analysis results if present
                            if (this.analysisResults?.results) {
                                const result = this.analysisResults.results.find(r => r.document_id === this.currentDocumentId);
                                if (result) {
                                    result.true_revenue = data.document.true_revenue;
                                }
                                // Recalculate total
                                this.analysisResults.summary.total_true_revenue = this.analysisResults.results
                                    .filter(r => r.success)
                                    .reduce((sum, r) => sum + r.true_revenue, 0);
                            }

                            this.hasTransactionChanges = false;
                            Toast.success(`True revenue recalculated: $${data.document.true_revenue.toLocaleString('en-US', {minimumFractionDigits: 2})}`);
                        } else {
                            Toast.error(data.message || 'Failed to recalculate true revenue');
                        }
                    } catch (error) {
                        console.error('Error recalculating true revenue:', error);
                        Toast.error('Failed to recalculate true revenue');
                    } finally {
                        this.recalculating = false;
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
