<?php

namespace App\Services;

use App\Models\MCAApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ApplicationFlowService
{
    protected UnderwritingScoreService $underwritingService;
    protected RiskScoringService $riskScoringService;

    public function __construct(
        UnderwritingScoreService $underwritingService,
        RiskScoringService $riskScoringService
    ) {
        $this->underwritingService = $underwritingService;
        $this->riskScoringService = $riskScoringService;
    }

    /**
     * Transition application to a new status with validation
     */
    public function transitionTo(MCAApplication $application, string $newStatus, ?string $notes = null): array
    {
        if (!$application->canTransitionTo($newStatus)) {
            return [
                'success' => false,
                'error' => "Cannot transition from '{$application->status}' to '{$newStatus}'",
                'allowed_transitions' => MCAApplication::STATUS_TRANSITIONS[$application->status] ?? [],
            ];
        }

        $oldStatus = $application->status;
        $application->status = $newStatus;

        // Handle decision status updates
        if (in_array($newStatus, [MCAApplication::STATUS_APPROVED, MCAApplication::STATUS_DECLINED])) {
            $application->decision = $newStatus;
            $application->decided_by = Auth::id();
            $application->decided_at = now();
            if ($notes) {
                $application->decision_notes = $notes;
            }
        }

        // Handle funded status
        if ($newStatus === MCAApplication::STATUS_FUNDED) {
            $application->funded_at = now();
        }

        $application->save();

        // Add note for status change
        $application->addNote(
            "Status changed from {$oldStatus} to {$newStatus}" . ($notes ? ": {$notes}" : ''),
            'status_change',
            Auth::id()
        );

        Log::info("Application {$application->application_id} transitioned from {$oldStatus} to {$newStatus}");

        return [
            'success' => true,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'phase' => $application->getCurrentPhase(),
        ];
    }

    /**
     * Check if application can advance to next phase
     */
    public function canAdvancePhase(MCAApplication $application): array
    {
        $currentPhase = $application->getCurrentPhase();
        $requirements = $this->getPhaseRequirements($application, $currentPhase);

        return [
            'can_advance' => $requirements['met'],
            'current_phase' => $currentPhase,
            'requirements' => $requirements['items'],
            'next_phase' => $this->getNextPhase($currentPhase),
        ];
    }

    /**
     * Get requirements for current phase
     */
    protected function getPhaseRequirements(MCAApplication $application, string $phase): array
    {
        $requirements = [];
        $allMet = true;

        switch ($phase) {
            case MCAApplication::PHASE_APPLICATION:
                $requirements[] = [
                    'label' => 'Business information complete',
                    'met' => !empty($application->business_name),
                ];
                $requirements[] = [
                    'label' => 'Owner information complete',
                    'met' => !empty($application->owner_first_name) && !empty($application->owner_last_name),
                ];
                $requirements[] = [
                    'label' => 'Funding request specified',
                    'met' => !empty($application->requested_amount),
                ];
                break;

            case MCAApplication::PHASE_VERIFICATION:
                $verificationProgress = $application->getVerificationProgress();
                $requiredVerifications = ['persona', 'experian_credit', 'datamerch'];

                foreach ($requiredVerifications as $type) {
                    $requirements[] = [
                        'label' => MCAApplication::VERIFICATION_TYPES[$type],
                        'met' => $verificationProgress[$type]['completed'] ?? false,
                    ];
                }
                break;

            case MCAApplication::PHASE_BANK_DATA:
                $bankProgress = $application->getBankDataProgress();
                $requirements[] = [
                    'label' => 'Bank statements uploaded or Plaid connected',
                    'met' => $bankProgress['has_bank_data'],
                ];
                $requirements[] = [
                    'label' => 'Bank statements analyzed',
                    'met' => $bankProgress['documents']['analyzed'] > 0 || $bankProgress['plaid']['connected'],
                ];
                break;

            case MCAApplication::PHASE_UNDERWRITING:
                $uwProgress = $application->getUnderwritingProgress();
                $requirements[] = [
                    'label' => 'Underwriting score calculated',
                    'met' => $uwProgress['has_score'],
                ];
                break;

            case MCAApplication::PHASE_DECISION:
                $requirements[] = [
                    'label' => 'Decision made (approve/decline)',
                    'met' => in_array($application->status, [
                        MCAApplication::STATUS_APPROVED,
                        MCAApplication::STATUS_DECLINED,
                    ]),
                ];
                break;

            case MCAApplication::PHASE_FUNDING:
                $requirements[] = [
                    'label' => 'Funding completed',
                    'met' => $application->status === MCAApplication::STATUS_FUNDED,
                ];
                break;
        }

        foreach ($requirements as $req) {
            if (!$req['met']) {
                $allMet = false;
                break;
            }
        }

        return [
            'met' => $allMet,
            'items' => $requirements,
        ];
    }

    /**
     * Get next phase in the flow
     */
    protected function getNextPhase(string $currentPhase): ?string
    {
        $phases = [
            MCAApplication::PHASE_APPLICATION,
            MCAApplication::PHASE_VERIFICATION,
            MCAApplication::PHASE_BANK_DATA,
            MCAApplication::PHASE_UNDERWRITING,
            MCAApplication::PHASE_DECISION,
            MCAApplication::PHASE_FUNDING,
        ];

        $currentIndex = array_search($currentPhase, $phases);
        if ($currentIndex === false || $currentIndex >= count($phases) - 1) {
            return null;
        }

        return $phases[$currentIndex + 1];
    }

    /**
     * Auto-advance application based on completed requirements
     */
    public function autoAdvance(MCAApplication $application): array
    {
        $advanced = false;
        $transitions = [];

        // Check verification completion -> move to processing
        if ($application->status === MCAApplication::STATUS_SUBMITTED) {
            $verificationProgress = $application->getVerificationProgress();
            $requiredVerifications = ['persona', 'experian_credit', 'datamerch'];
            $allComplete = true;

            foreach ($requiredVerifications as $type) {
                if (!($verificationProgress[$type]['completed'] ?? false)) {
                    $allComplete = false;
                    break;
                }
            }

            if ($allComplete) {
                $result = $this->transitionTo($application, MCAApplication::STATUS_PROCESSING, 'Auto-advanced: All required verifications complete');
                if ($result['success']) {
                    $advanced = true;
                    $transitions[] = $result;
                }
            }
        }

        // Check underwriting completion -> move to under_review
        if ($application->status === MCAApplication::STATUS_PROCESSING) {
            $uwProgress = $application->getUnderwritingProgress();
            if ($uwProgress['has_score']) {
                $result = $this->transitionTo($application, MCAApplication::STATUS_UNDER_REVIEW, 'Auto-advanced: Underwriting score calculated');
                if ($result['success']) {
                    $advanced = true;
                    $transitions[] = $result;
                }
            }
        }

        return [
            'advanced' => $advanced,
            'transitions' => $transitions,
            'current_status' => $application->status,
            'current_phase' => $application->getCurrentPhase(),
        ];
    }

    /**
     * Process underwriting and auto-decision
     */
    public function processUnderwriting(MCAApplication $application): array
    {
        // Calculate underwriting score
        $uwResult = $this->underwritingService->calculateAndSave($application);

        if (!$uwResult['success']) {
            return [
                'success' => false,
                'error' => $uwResult['error'] ?? 'Failed to calculate underwriting score',
            ];
        }

        $application->refresh();

        // Check for auto-decision thresholds
        $autoDecision = null;
        $score = $uwResult['score'];
        $decision = $uwResult['decision'];

        if ($score >= 75 && $decision === 'APPROVE') {
            $autoDecision = MCAApplication::STATUS_APPROVED;
        } elseif ($score < 30 && $decision === 'DECLINE') {
            $autoDecision = MCAApplication::STATUS_DECLINED;
        }

        // Auto-advance status
        $advanceResult = $this->autoAdvance($application);

        return [
            'success' => true,
            'underwriting' => [
                'score' => $score,
                'decision' => $decision,
                'flags' => $uwResult['flags'] ?? [],
            ],
            'auto_decision' => $autoDecision,
            'status_advanced' => $advanceResult['advanced'],
            'current_status' => $application->status,
        ];
    }

    /**
     * Get recommended next actions for application
     */
    public function getRecommendedActions(MCAApplication $application): array
    {
        $actions = [];
        $phase = $application->getCurrentPhase();

        switch ($phase) {
            case MCAApplication::PHASE_APPLICATION:
                if ($application->status === MCAApplication::STATUS_DRAFT) {
                    $actions[] = [
                        'action' => 'submit',
                        'label' => 'Submit Application',
                        'priority' => 'high',
                    ];
                }
                break;

            case MCAApplication::PHASE_VERIFICATION:
                $verificationProgress = $application->getVerificationProgress();
                foreach ($verificationProgress as $type => $data) {
                    if (!$data['completed']) {
                        $actions[] = [
                            'action' => 'run_verification',
                            'type' => $type,
                            'label' => "Run {$data['label']}",
                            'priority' => in_array($type, ['persona', 'experian_credit', 'datamerch']) ? 'high' : 'medium',
                        ];
                    }
                }
                break;

            case MCAApplication::PHASE_BANK_DATA:
                $bankProgress = $application->getBankDataProgress();
                if (!$bankProgress['has_bank_data']) {
                    $actions[] = [
                        'action' => 'upload_bank_statements',
                        'label' => 'Upload Bank Statements',
                        'priority' => 'high',
                    ];
                    $actions[] = [
                        'action' => 'send_plaid_link',
                        'label' => 'Send Plaid Bank Link',
                        'priority' => 'high',
                    ];
                } elseif ($bankProgress['documents']['total'] > $bankProgress['documents']['analyzed']) {
                    $actions[] = [
                        'action' => 'analyze_bank_statements',
                        'label' => 'Analyze Bank Statements',
                        'priority' => 'high',
                    ];
                }
                break;

            case MCAApplication::PHASE_UNDERWRITING:
                $uwProgress = $application->getUnderwritingProgress();
                if (!$uwProgress['has_score']) {
                    $actions[] = [
                        'action' => 'calculate_underwriting',
                        'label' => 'Calculate Underwriting Score',
                        'priority' => 'high',
                    ];
                }
                break;

            case MCAApplication::PHASE_DECISION:
                if ($application->status === MCAApplication::STATUS_UNDER_REVIEW) {
                    $actions[] = [
                        'action' => 'approve',
                        'label' => 'Approve Application',
                        'priority' => 'medium',
                    ];
                    $actions[] = [
                        'action' => 'decline',
                        'label' => 'Decline Application',
                        'priority' => 'medium',
                    ];
                }
                break;

            case MCAApplication::PHASE_FUNDING:
                if ($application->status === MCAApplication::STATUS_APPROVED) {
                    $actions[] = [
                        'action' => 'fund',
                        'label' => 'Mark as Funded',
                        'priority' => 'high',
                    ];
                }
                break;
        }

        return $actions;
    }

    /**
     * Get application summary for dashboard/list view
     */
    public function getApplicationSummary(MCAApplication $application): array
    {
        $flowStatus = $application->getCompleteFlowStatus();

        return [
            'id' => $application->id,
            'application_id' => $application->application_id,
            'business_name' => $application->business_name,
            'owner_name' => $application->owner_full_name,
            'requested_amount' => $application->requested_amount,
            'status' => $application->status,
            'status_badge' => $application->status_badge,
            'phase' => $flowStatus['current_phase'],
            'flow_progress' => $flowStatus['flow_progress'],
            'underwriting_score' => $application->underwriting_score,
            'underwriting_decision' => $application->underwriting_decision,
            'created_at' => $application->created_at->format('M d, Y'),
            'updated_at' => $application->updated_at->format('M d, Y H:i'),
            'recommended_actions' => $this->getRecommendedActions($application),
        ];
    }
}
