<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacerReport extends Model
{
    use HasFactory;

    protected $table = 'pacer_reports';

    protected $fillable = [
        'application_id',
        'total_cases',
        'bankruptcy_cases',
        'civil_cases',
        'judgments',
        'risk_score',
        'risk_level',
        'case_details',
        'bankruptcy_details',
        'judgment_details',
        'recommendation',
        'flags',
        'search_name',
        'search_type',
    ];

    protected $casts = [
        'case_details' => 'array',
        'bankruptcy_details' => 'array',
        'judgment_details' => 'array',
        'flags' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }

    public function getRiskBadgeAttribute(): string
    {
        return match ($this->risk_level) {
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
            default => 'secondary',
        };
    }

    public function hasActiveBankruptcy(): bool
    {
        if (empty($this->bankruptcy_details)) {
            return false;
        }

        foreach ($this->bankruptcy_details as $case) {
            $status = strtolower($case['caseStatus'] ?? $case['status'] ?? '');
            if (in_array($status, ['open', 'active', 'pending'])) {
                return true;
            }
        }

        return false;
    }
}
