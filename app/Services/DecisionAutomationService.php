<?php

namespace App\Services;

use App\Models\MCAApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class DecisionAutomationService
{
    protected RiskScoringService $riskScoring;

    protected array $config;

    public function __construct(RiskScoringService $riskScoring)
    {
        $this->riskScoring = $riskScoring;
        $this->config = config('decision_automation', $this->getDefaultConfig());
    }

    /**
     * Process application through automated decision workflow
     */
    public function processApplication(int $applicationId): array
    {
        $application = MCAApplication::with([
            'verificationResults',
            'creditReports',
            'stackingReports',
            'uccReports',
        ])->findOrFail($applicationId);

        $startTime = microtime(true);
        $workflow = [];

        try {
            // Step 1: Check if automation is enabled
            if (! $this->config['enabled']) {
                return $this->createManualReviewResponse($application, 'Automation disabled');
            }

            // Step 2: Gather all available data
            $workflow['data_collection'] = $this->collectApplicationData($application);

            // Step 3: Perform comprehensive risk assessment
            $workflow['risk_assessment'] = $this->riskScoring->performComprehensiveAssessment($workflow['data_collection']);

            // Step 4: Check for required verifications
            $missingVerifications = $this->checkRequiredVerifications($application, $workflow['risk_assessment']);
            if (! empty($missingVerifications)) {
                return $this->createPendingVerificationResponse($application, $missingVerifications, $workflow);
            }

            // Step 5: Make automated decision
            $decision = $this->makeAutomatedDecision($application, $workflow['risk_assessment']);
            $workflow['decision'] = $decision;

            // Step 6: Execute decision actions
            $this->executeDecisionActions($application, $decision, $workflow['risk_assessment']);

            // Step 7: Record decision
            $this->recordDecision($application, $decision, $workflow);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => true,
                'application_id' => $applicationId,
                'decision' => $decision,
                'risk_assessment' => $workflow['risk_assessment'],
                'execution_time_ms' => $executionTime,
                'workflow' => $workflow,
            ];
        } catch (\Exception $e) {
            Log::error('Decision automation failed', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
                'fallback' => 'manual_review',
            ];
        }
    }

    /**
     * Collect all application data for assessment
     */
    protected function collectApplicationData(MCAApplication $application): array
    {
        $data = [
            'application_id' => $application->id,
            'business_name' => $application->business_name,
            'industry' => $application->industry,
            'monthly_revenue' => $application->monthly_revenue,
            'requested_amount' => $application->funding_amount,
            'time_in_business_months' => $this->calculateTimeInBusiness($application),
            'state' => $application->business_state,
            'business_type' => $application->business_type,
        ];

        // Credit data
        $creditReport = $application->creditReports()->latest()->first();
        if ($creditReport) {
            $data['credit'] = [
                'credit_score' => $creditReport->credit_score,
                'bankruptcies' => $creditReport->bankruptcies ?? 0,
                'delinquencies' => $creditReport->delinquencies ?? 0,
                'flags' => json_decode($creditReport->flags ?? '[]', true),
            ];
        }

        // Identity verification
        $identityVerification = $application->verificationResults()
            ->where('type', 'persona')
            ->latest()
            ->first();
        if ($identityVerification) {
            $data['identity'] = [
                'score' => $identityVerification->score ?? 50,
                'status' => $identityVerification->status,
                'flags' => json_decode($identityVerification->flags ?? '[]', true),
            ];
        }

        // Stacking data
        $stackingReport = $application->stackingReports()->latest()->first();
        if ($stackingReport) {
            $data['stacking'] = [
                'active_mcas' => $stackingReport->active_mcas ?? 0,
                'risk_score' => $stackingReport->risk_score ?? 100,
                'total_exposure' => $stackingReport->total_exposure ?? 0,
                'has_defaults' => $stackingReport->has_defaults ?? false,
                'flags' => json_decode($stackingReport->flags ?? '[]', true),
            ];

            // Get existing positions for stacking optimizer
            $data['existing_positions'] = json_decode($stackingReport->positions ?? '[]', true);
        }

        // UCC data
        $uccReport = $application->uccReports()->latest()->first();
        if ($uccReport) {
            $data['ucc'] = [
                'active_filings' => $uccReport->active_filings ?? 0,
                'mca_filings' => $uccReport->mca_filings ?? 0,
                'risk_score' => $uccReport->risk_score ?? 100,
                'has_blanket_lien' => $uccReport->has_blanket_lien ?? false,
                'flags' => json_decode($uccReport->flags ?? '[]', true),
            ];
        }

        // Bank analysis data (if available)
        $bankVerification = $application->verificationResults()
            ->where('type', 'bank_analysis')
            ->latest()
            ->first();
        if ($bankVerification) {
            $data['bank_analysis'] = json_decode($bankVerification->data ?? '{}', true);
        }

        // Application metadata
        $data['application_data'] = [
            'monthly_revenue' => $application->monthly_revenue,
            'business_name' => $application->business_name,
        ];

        return $data;
    }

    /**
     * Check for required verifications
     */
    protected function checkRequiredVerifications(MCAApplication $application, array $riskAssessment): array
    {
        $missing = [];
        $requiredTypes = $this->config['required_verifications'];

        // Add rule-based required verifications
        if (! empty($riskAssessment['required_verifications'])) {
            foreach ($riskAssessment['required_verifications'] as $reqVerification) {
                $requiredTypes[] = $reqVerification['type'];
            }
            $requiredTypes = array_unique($requiredTypes);
        }

        foreach ($requiredTypes as $type) {
            $hasVerification = $application->verificationResults()
                ->where('type', $type)
                ->where('status', 'completed')
                ->exists();

            if (! $hasVerification) {
                $missing[] = [
                    'type' => $type,
                    'description' => $this->getVerificationDescription($type),
                ];
            }
        }

        return $missing;
    }

    /**
     * Make automated decision based on risk assessment
     */
    protected function makeAutomatedDecision(MCAApplication $application, array $riskAssessment): array
    {
        $decision = $riskAssessment['decision'];
        $score = $riskAssessment['overall_score'];

        // Apply automation rules
        if ($decision['action'] === 'APPROVE' && $this->config['auto_approve_enabled']) {
            if ($score >= $this->config['auto_approve_threshold']) {
                return [
                    'action' => 'APPROVE',
                    'type' => 'automated',
                    'status' => 'approved',
                    'message' => 'Application automatically approved based on risk score',
                    'offer_terms' => $riskAssessment['offer_terms'],
                    'requires_human_review' => false,
                ];
            }
        }

        if ($decision['action'] === 'DECLINE' && $this->config['auto_decline_enabled']) {
            if ($score < $this->config['auto_decline_threshold']) {
                return [
                    'action' => 'DECLINE',
                    'type' => 'automated',
                    'status' => 'declined',
                    'message' => $decision['message'] ?? 'Application does not meet minimum criteria',
                    'reason_code' => $decision['reason_code'] ?? 'LOW_SCORE',
                    'requires_human_review' => false,
                ];
            }
        }

        // Everything else goes to manual review
        return [
            'action' => 'REVIEW',
            'type' => 'manual_required',
            'status' => 'under_review',
            'message' => 'Application requires manual underwriter review',
            'review_level' => $this->determineReviewLevel($score, $riskAssessment),
            'review_notes' => $this->generateReviewNotes($riskAssessment),
            'requires_human_review' => true,
        ];
    }

    /**
     * Execute decision actions
     */
    protected function executeDecisionActions(MCAApplication $application, array $decision, array $riskAssessment): void
    {
        // Update application status
        $application->update([
            'status' => $decision['status'],
            'overall_risk_score' => $riskAssessment['overall_score'],
            'risk_level' => $riskAssessment['risk_level'],
            'risk_details' => json_encode($riskAssessment),
            'decision' => $decision['action'],
            'decided_at' => $decision['type'] === 'automated' ? now() : null,
            'decided_by' => $decision['type'] === 'automated' ? 'system' : null,
        ]);

        // Update offer terms if approved
        if ($decision['action'] === 'APPROVE' && isset($decision['offer_terms'])) {
            $terms = $decision['offer_terms'];
            $application->update([
                'approved_amount' => $terms['approved_amount'] ?? null,
                'factor_rate' => $terms['factor_rate'] ?? null,
                'payback_amount' => $terms['payback_amount'] ?? null,
                'term_months' => $terms['term_months'] ?? null,
                'daily_payment' => $terms['daily_payment'] ?? null,
                'holdback_percentage' => $terms['holdback_percentage'] ?? null,
            ]);
        }

        // Send notifications
        $this->sendDecisionNotifications($application, $decision);

        // Queue follow-up tasks
        $this->queueFollowUpTasks($application, $decision);
    }

    /**
     * Record decision in audit log
     */
    protected function recordDecision(MCAApplication $application, array $decision, array $workflow): void
    {
        // Add application note
        $application->notes()->create([
            'type' => 'decision',
            'content' => json_encode([
                'decision' => $decision,
                'risk_score' => $workflow['risk_assessment']['overall_score'] ?? null,
                'flags' => $workflow['risk_assessment']['flags'] ?? [],
                'matched_rules' => $workflow['risk_assessment']['analysis_results']['custom_rules']['matched_rules'] ?? [],
            ]),
            'created_by' => 'system',
        ]);

        // Log decision
        Log::info('Application decision recorded', [
            'application_id' => $application->id,
            'decision' => $decision['action'],
            'type' => $decision['type'],
            'score' => $workflow['risk_assessment']['overall_score'] ?? null,
        ]);
    }

    /**
     * Send decision notifications
     */
    protected function sendDecisionNotifications(MCAApplication $application, array $decision): void
    {
        if (! $this->config['notifications_enabled']) {
            return;
        }

        // Notify applicant
        if ($this->config['notify_applicant'] && $application->owner_email) {
            try {
                // Queue email notification
                // Mail::to($application->owner_email)->queue(new ApplicationDecisionNotification($application, $decision));
            } catch (\Exception $e) {
                Log::warning('Failed to send applicant notification', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notify underwriters for manual review
        if ($decision['requires_human_review'] && $this->config['notify_underwriters']) {
            $this->notifyUnderwriters($application, $decision);
        }
    }

    /**
     * Notify underwriters of pending review
     */
    protected function notifyUnderwriters(MCAApplication $application, array $decision): void
    {
        $reviewLevel = $decision['review_level'] ?? 'standard';

        // Get underwriters based on review level
        $underwriters = $this->getAvailableUnderwriters($reviewLevel);

        // Auto-assign if enabled
        if ($this->config['auto_assign_enabled'] && ! empty($underwriters)) {
            $assignee = $this->selectUnderwriter($underwriters, $application);
            $application->update(['assigned_to' => $assignee['id']]);
        }
    }

    /**
     * Queue follow-up tasks
     */
    protected function queueFollowUpTasks(MCAApplication $application, array $decision): void
    {
        if ($decision['action'] === 'APPROVE') {
            // Queue funding preparation tasks
            // Queue::push(new PrepareFundingDocuments($application));
        } elseif ($decision['action'] === 'DECLINE') {
            // Queue adverse action notice if required
            // Queue::later(now()->addDay(), new SendAdverseActionNotice($application));
        }
    }

    /**
     * Determine review level based on score and risk factors
     */
    protected function determineReviewLevel(int $score, array $riskAssessment): string
    {
        if ($score < 30) {
            return 'senior';
        }

        $highSeverityFlags = count(array_filter(
            $riskAssessment['analysis_results']['custom_rules']['flags'] ?? [],
            fn ($f) => ($f['severity'] ?? '') === 'high'
        ));

        if ($highSeverityFlags >= 2) {
            return 'senior';
        }

        if ($score < 50 || $highSeverityFlags >= 1) {
            return 'experienced';
        }

        return 'standard';
    }

    /**
     * Generate review notes for underwriter
     */
    protected function generateReviewNotes(array $riskAssessment): array
    {
        $notes = [];

        // Key findings
        $notes['key_findings'] = array_slice($riskAssessment['flags'] ?? [], 0, 10);

        // Areas of concern
        $concerns = [];
        $componentScores = $riskAssessment['component_scores'] ?? [];
        foreach ($componentScores as $component => $score) {
            if ($score < 50) {
                $concerns[] = "{$component}: {$score}/100";
            }
        }
        $notes['areas_of_concern'] = $concerns;

        // Fraud indicators
        if (isset($riskAssessment['analysis_results']['fraud_analysis'])) {
            $fraudAnalysis = $riskAssessment['analysis_results']['fraud_analysis'];
            if (($fraudAnalysis['fraud_score'] ?? 100) < 80) {
                $notes['fraud_review_required'] = true;
                $notes['fraud_flags'] = array_column($fraudAnalysis['flags'] ?? [], 'message');
            }
        }

        // Stacking concerns
        if (isset($riskAssessment['analysis_results']['position_optimization'])) {
            $stacking = $riskAssessment['analysis_results']['position_optimization'];
            if (isset($stacking['stacking_analysis']['risk_level']) && $stacking['stacking_analysis']['risk_level'] !== 'low') {
                $notes['stacking_review_required'] = true;
                $notes['stacking_factors'] = $stacking['stacking_analysis']['risk_factors'] ?? [];
            }
        }

        return $notes;
    }

    /**
     * Get available underwriters
     */
    protected function getAvailableUnderwriters(string $level): array
    {
        // This would typically query a users table
        // For now, return empty array - implement based on your user system
        return [];
    }

    /**
     * Select underwriter based on workload and expertise
     */
    protected function selectUnderwriter(array $underwriters, MCAApplication $application): ?array
    {
        if (empty($underwriters)) {
            return null;
        }

        // Simple round-robin or workload-based selection
        // Implement based on your business rules
        return $underwriters[0];
    }

    /**
     * Calculate time in business in months
     */
    protected function calculateTimeInBusiness(MCAApplication $application): int
    {
        if (! $application->business_start_date) {
            return 0;
        }

        return Carbon::parse($application->business_start_date)->diffInMonths(now());
    }

    /**
     * Get verification description
     */
    protected function getVerificationDescription(string $type): string
    {
        return match ($type) {
            'persona' => 'Identity Verification',
            'experian_credit' => 'Personal Credit Report',
            'experian_business' => 'Business Credit Report',
            'datamerch' => 'MCA Stacking Check',
            'ucc' => 'UCC Filing Search',
            'bank_analysis' => 'Bank Statement Analysis',
            'enhanced_due_diligence' => 'Enhanced Due Diligence',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Create response for manual review
     */
    protected function createManualReviewResponse(MCAApplication $application, string $reason): array
    {
        return [
            'success' => true,
            'application_id' => $application->id,
            'decision' => [
                'action' => 'REVIEW',
                'type' => 'manual_required',
                'status' => 'under_review',
                'message' => $reason,
                'requires_human_review' => true,
            ],
        ];
    }

    /**
     * Create response for pending verification
     */
    protected function createPendingVerificationResponse(MCAApplication $application, array $missing, array $workflow): array
    {
        $application->update(['status' => 'pending_verification']);

        return [
            'success' => true,
            'application_id' => $application->id,
            'decision' => [
                'action' => 'PENDING',
                'type' => 'verification_required',
                'status' => 'pending_verification',
                'message' => 'Application requires additional verifications',
                'requires_human_review' => false,
            ],
            'missing_verifications' => $missing,
            'workflow' => $workflow,
        ];
    }

    /**
     * Process batch of applications
     */
    public function processBatch(array $applicationIds): array
    {
        $results = [];

        foreach ($applicationIds as $id) {
            $results[$id] = $this->processApplication($id);
        }

        return [
            'processed' => count($results),
            'approved' => count(array_filter($results, fn ($r) => ($r['decision']['action'] ?? '') === 'APPROVE')),
            'declined' => count(array_filter($results, fn ($r) => ($r['decision']['action'] ?? '') === 'DECLINE')),
            'review' => count(array_filter($results, fn ($r) => ($r['decision']['action'] ?? '') === 'REVIEW')),
            'results' => $results,
        ];
    }

    /**
     * Re-evaluate application with updated data
     */
    public function reEvaluate(int $applicationId): array
    {
        return $this->processApplication($applicationId);
    }

    /**
     * Get automation statistics
     */
    public function getStatistics(string $period = '30d'): array
    {
        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };

        try {
            $stats = DB::table('mca_applications')
                ->where('decided_at', '>=', $startDate)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN decision = 'APPROVE' AND decided_by = 'system' THEN 1 ELSE 0 END) as auto_approved,
                    SUM(CASE WHEN decision = 'DECLINE' AND decided_by = 'system' THEN 1 ELSE 0 END) as auto_declined,
                    SUM(CASE WHEN decided_by != 'system' THEN 1 ELSE 0 END) as manual_decisions,
                    AVG(overall_risk_score) as avg_score
                ")
                ->first();

            return [
                'period' => $period,
                'total_decisions' => $stats->total ?? 0,
                'auto_approved' => $stats->auto_approved ?? 0,
                'auto_declined' => $stats->auto_declined ?? 0,
                'manual_decisions' => $stats->manual_decisions ?? 0,
                'automation_rate' => $stats->total > 0
                    ? round((($stats->auto_approved + $stats->auto_declined) / $stats->total) * 100, 2)
                    : 0,
                'average_score' => round($stats->avg_score ?? 0, 2),
            ];
        } catch (\Exception $e) {
            return [
                'period' => $period,
                'error' => 'Unable to retrieve statistics',
            ];
        }
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'auto_approve_enabled' => true,
            'auto_approve_threshold' => 80,
            'auto_decline_enabled' => true,
            'auto_decline_threshold' => 30,
            'required_verifications' => ['persona', 'experian_credit'],
            'notifications_enabled' => true,
            'notify_applicant' => true,
            'notify_underwriters' => true,
            'auto_assign_enabled' => true,
        ];
    }
}
