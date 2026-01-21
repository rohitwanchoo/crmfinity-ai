<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MCAApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mca_applications';

    // Application Status Constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_FUNDED = 'funded';
    public const STATUS_CLOSED = 'closed';

    // Flow Phases
    public const PHASE_APPLICATION = 'application';
    public const PHASE_VERIFICATION = 'verification';
    public const PHASE_BANK_DATA = 'bank_data';
    public const PHASE_UNDERWRITING = 'underwriting';
    public const PHASE_DECISION = 'decision';
    public const PHASE_FUNDING = 'funding';

    // Verification Types
    public const VERIFICATION_TYPES = [
        'persona' => 'Identity Verification',
        'experian_credit' => 'Personal Credit Report',
        'experian_business' => 'Business Credit Report',
        'datamerch' => 'MCA Stacking Check',
        'ucc' => 'UCC Filing Search',
        'pacer' => 'Court Records Search',
    ];

    // Valid Status Transitions (State Machine)
    public const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED],
        self::STATUS_SUBMITTED => [self::STATUS_PROCESSING, self::STATUS_UNDER_REVIEW],
        self::STATUS_PROCESSING => [self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED, self::STATUS_DECLINED],
        self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_DECLINED, self::STATUS_PROCESSING],
        self::STATUS_APPROVED => [self::STATUS_FUNDED, self::STATUS_CLOSED],
        self::STATUS_DECLINED => [self::STATUS_CLOSED, self::STATUS_UNDER_REVIEW],
        self::STATUS_FUNDED => [self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    // Status to Phase Mapping
    public const STATUS_PHASE_MAP = [
        self::STATUS_DRAFT => self::PHASE_APPLICATION,
        self::STATUS_SUBMITTED => self::PHASE_VERIFICATION,
        self::STATUS_PROCESSING => self::PHASE_UNDERWRITING,
        self::STATUS_UNDER_REVIEW => self::PHASE_DECISION,
        self::STATUS_APPROVED => self::PHASE_FUNDING,
        self::STATUS_DECLINED => self::PHASE_DECISION,
        self::STATUS_FUNDED => self::PHASE_FUNDING,
        self::STATUS_CLOSED => self::PHASE_FUNDING,
    ];

    protected $fillable = [
        'application_id', 'user_id', 'status',
        // Business Info
        'business_name', 'dba_name', 'ein', 'business_type', 'industry',
        'business_start_date', 'business_phone', 'business_email', 'website',
        'business_address', 'business_city', 'business_state', 'business_zip',
        // Owner Info
        'owner_first_name', 'owner_last_name', 'owner_email', 'owner_phone',
        'owner_ssn_last4', 'owner_dob', 'ownership_percentage',
        'owner_address', 'owner_city', 'owner_state', 'owner_zip',
        // Funding
        'requested_amount', 'use_of_funds', 'monthly_revenue', 'time_in_business_months',
        // Risk
        'overall_risk_score', 'risk_level', 'risk_details',
        // Underwriting
        'underwriting_score', 'underwriting_decision', 'underwriting_details', 'underwriting_calculated_at',
        // Decision
        'decision', 'decision_notes', 'decided_by', 'decided_at',
        // Offer
        'approved_amount', 'factor_rate', 'payback_amount', 'term_months',
        'daily_payment', 'holdback_percentage',
        // Funding
        'funded_at', 'funded_position',
    ];

    protected $casts = [
        'business_start_date' => 'date',
        'owner_dob' => 'date',
        'decided_at' => 'datetime',
        'funded_at' => 'datetime',
        'risk_details' => 'array',
        'underwriting_details' => 'array',
        'underwriting_calculated_at' => 'datetime',
        'requested_amount' => 'decimal:2',
        'monthly_revenue' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'payback_amount' => 'decimal:2',
        'daily_payment' => 'decimal:2',
        'factor_rate' => 'decimal:4',
        'holdback_percentage' => 'decimal:4',
        'ownership_percentage' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($application) {
            if (empty($application->application_id)) {
                $application->application_id = 'APP-'.strtoupper(Str::random(8));
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(VerificationResult::class, 'application_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'application_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class, 'application_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function personaInquiry(): HasOne
    {
        return $this->hasOne(PersonaInquiry::class, 'application_id');
    }

    public function personaInquiries(): HasMany
    {
        return $this->hasMany(PersonaInquiry::class, 'application_id');
    }

    public function creditReport(): HasOne
    {
        return $this->hasOne(CreditReport::class, 'application_id')->latest();
    }

    public function creditReports(): HasMany
    {
        return $this->hasMany(CreditReport::class, 'application_id');
    }

    public function stackingReport(): HasOne
    {
        return $this->hasOne(StackingReport::class, 'application_id')->latest();
    }

    public function stackingReports(): HasMany
    {
        return $this->hasMany(StackingReport::class, 'application_id');
    }

    public function uccReport(): HasOne
    {
        return $this->hasOne(UCCReport::class, 'application_id')->latest();
    }

    public function uccReports(): HasMany
    {
        return $this->hasMany(UCCReport::class, 'application_id');
    }

    public function pacerReport(): HasOne
    {
        return $this->hasOne(PacerReport::class, 'application_id')->latest();
    }

    public function pacerReports(): HasMany
    {
        return $this->hasMany(PacerReport::class, 'application_id');
    }

    public function verificationResults(): HasMany
    {
        return $this->hasMany(VerificationResult::class, 'application_id');
    }

    public function plaidItems(): HasMany
    {
        return $this->hasMany(PlaidItem::class, 'application_id');
    }

    public function bankLinkRequests(): HasMany
    {
        return $this->hasMany(BankLinkRequest::class, 'application_id');
    }

    public function activeBankLinkRequest()
    {
        return $this->hasOne(BankLinkRequest::class, 'application_id')
            ->whereIn('status', ['pending', 'sent', 'opened'])
            ->where('expires_at', '>', now())
            ->latest();
    }

    // Accessors
    public function getOwnerFullNameAttribute(): string
    {
        return trim("{$this->owner_first_name} {$this->owner_last_name}");
    }

    public function getBusinessFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->business_address,
            $this->business_city,
            $this->business_state,
            $this->business_zip,
        ]));
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'secondary',
            'submitted' => 'info',
            'processing' => 'warning',
            'under_review' => 'warning',
            'approved' => 'success',
            'declined' => 'danger',
            'funded' => 'primary',
            'closed' => 'secondary',
            default => 'secondary',
        };
    }

    public function getRiskBadgeAttribute(): string
    {
        return match ($this->risk_level) {
            'low' => 'success',
            'medium-low' => 'success',
            'medium' => 'warning',
            'medium-high' => 'warning',
            'high' => 'danger',
            default => 'secondary',
        };
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['submitted', 'processing', 'under_review']);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    // Methods
    public function addNote(string $content, string $type = 'note', ?int $userId = null, ?array $metadata = null): ApplicationNote
    {
        return $this->notes()->create([
            'user_id' => $userId,
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    public function updateStatus(string $status, ?string $notes = null, ?int $userId = null): void
    {
        $oldStatus = $this->status;
        $this->status = $status;

        if ($status === 'approved' || $status === 'declined') {
            $this->decision = $status;
            $this->decided_by = $userId;
            $this->decided_at = now();
            if ($notes) {
                $this->decision_notes = $notes;
            }
        }

        $this->save();

        $this->addNote(
            "Status changed from {$oldStatus} to {$status}".($notes ? ": {$notes}" : ''),
            'status_change',
            $userId
        );
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'submitted']);
    }

    public function canRunVerifications(): bool
    {
        return in_array($this->status, ['submitted', 'processing', 'under_review']);
    }

    public function getVerificationStatus(string $type): ?string
    {
        $verification = $this->verifications()->where('verification_type', $type)->latest()->first();

        return $verification?->status;
    }

    public function hasCompletedVerification(string $type): bool
    {
        return $this->getVerificationStatus($type) === 'completed';
    }

    // Flow Management Methods

    /**
     * Check if transition to new status is valid
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = self::STATUS_TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowedTransitions);
    }

    /**
     * Get current phase in the application flow
     */
    public function getCurrentPhase(): string
    {
        return self::STATUS_PHASE_MAP[$this->status] ?? self::PHASE_APPLICATION;
    }

    /**
     * Get flow progress data for UI
     */
    public function getFlowProgress(): array
    {
        $phases = [
            self::PHASE_APPLICATION => [
                'label' => 'Application',
                'icon' => 'file-text',
                'status' => 'pending',
            ],
            self::PHASE_VERIFICATION => [
                'label' => 'Verification',
                'icon' => 'shield-check',
                'status' => 'pending',
            ],
            self::PHASE_BANK_DATA => [
                'label' => 'Bank Data',
                'icon' => 'landmark',
                'status' => 'pending',
            ],
            self::PHASE_UNDERWRITING => [
                'label' => 'Underwriting',
                'icon' => 'calculator',
                'status' => 'pending',
            ],
            self::PHASE_DECISION => [
                'label' => 'Decision',
                'icon' => 'gavel',
                'status' => 'pending',
            ],
            self::PHASE_FUNDING => [
                'label' => 'Funding',
                'icon' => 'banknotes',
                'status' => 'pending',
            ],
        ];

        $currentPhase = $this->getCurrentPhase();
        $phaseOrder = array_keys($phases);
        $currentIndex = array_search($currentPhase, $phaseOrder);

        foreach ($phaseOrder as $index => $phase) {
            if ($index < $currentIndex) {
                $phases[$phase]['status'] = 'completed';
            } elseif ($index === $currentIndex) {
                $phases[$phase]['status'] = 'current';
            }
        }

        // Special handling for declined/closed
        if ($this->status === self::STATUS_DECLINED) {
            $phases[self::PHASE_DECISION]['status'] = 'declined';
            $phases[self::PHASE_FUNDING]['status'] = 'skipped';
        } elseif ($this->status === self::STATUS_FUNDED) {
            $phases[self::PHASE_FUNDING]['status'] = 'completed';
        }

        return $phases;
    }

    /**
     * Get verification completion status
     */
    public function getVerificationProgress(): array
    {
        $progress = [];
        foreach (self::VERIFICATION_TYPES as $type => $label) {
            $progress[$type] = [
                'label' => $label,
                'completed' => $this->hasCompletedVerificationType($type),
                'latest' => $this->getLatestVerification($type),
            ];
        }
        return $progress;
    }

    /**
     * Check if a specific verification type is completed
     */
    protected function hasCompletedVerificationType(string $type): bool
    {
        return match ($type) {
            'persona' => $this->personaInquiries()->where('status', 'completed')->exists(),
            'experian_credit' => $this->creditReports()->where('report_type', 'personal')->exists(),
            'experian_business' => $this->creditReports()->where('report_type', 'business')->exists(),
            'datamerch' => $this->stackingReports()->exists(),
            'ucc' => $this->uccReports()->exists(),
            'pacer' => $this->pacerReports()->exists(),
            default => false,
        };
    }

    /**
     * Get latest verification data for a type
     */
    protected function getLatestVerification(string $type): ?array
    {
        $data = match ($type) {
            'persona' => $this->personaInquiries()->latest()->first(),
            'experian_credit' => $this->creditReports()->where('report_type', 'personal')->latest()->first(),
            'experian_business' => $this->creditReports()->where('report_type', 'business')->latest()->first(),
            'datamerch' => $this->stackingReports()->latest()->first(),
            'ucc' => $this->uccReports()->latest()->first(),
            'pacer' => $this->pacerReports()->latest()->first(),
            default => null,
        };

        if (!$data) {
            return null;
        }

        return [
            'id' => $data->id,
            'created_at' => $data->created_at->format('M d, Y H:i'),
            'status' => $data->status ?? 'completed',
            'risk_level' => $data->risk_level ?? null,
            'risk_score' => $data->risk_score ?? $data->credit_score ?? null,
        ];
    }

    /**
     * Get bank data collection status
     */
    public function getBankDataProgress(): array
    {
        $bankStatements = $this->documents()->where('document_type', 'bank_statement')->get();
        $analyzedStatements = $bankStatements->where('is_processed', true);
        $plaidConnections = $this->plaidItems()->where('status', 'active')->count();

        return [
            'has_bank_data' => $bankStatements->isNotEmpty() || $plaidConnections > 0,
            'documents' => [
                'total' => $bankStatements->count(),
                'analyzed' => $analyzedStatements->count(),
            ],
            'plaid' => [
                'connected' => $plaidConnections > 0,
                'accounts' => $plaidConnections,
            ],
            'pending_link_request' => $this->activeBankLinkRequest()->exists(),
        ];
    }

    /**
     * Get underwriting status
     */
    public function getUnderwritingProgress(): array
    {
        return [
            'has_score' => !is_null($this->underwriting_score),
            'score' => $this->underwriting_score,
            'decision' => $this->underwriting_decision,
            'calculated_at' => $this->underwriting_calculated_at?->format('M d, Y H:i'),
            'details' => $this->underwriting_details,
        ];
    }

    /**
     * Get complete application flow status
     */
    public function getCompleteFlowStatus(): array
    {
        return [
            'current_status' => $this->status,
            'current_phase' => $this->getCurrentPhase(),
            'flow_progress' => $this->getFlowProgress(),
            'verification_progress' => $this->getVerificationProgress(),
            'bank_data_progress' => $this->getBankDataProgress(),
            'underwriting_progress' => $this->getUnderwritingProgress(),
            'allowed_transitions' => self::STATUS_TRANSITIONS[$this->status] ?? [],
        ];
    }
}
