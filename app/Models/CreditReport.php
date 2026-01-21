<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id', 'report_type', 'provider', 'credit_score',
        'score_factors', 'open_accounts', 'delinquent_accounts', 'total_debt',
        'bankruptcies', 'raw_report', 'analysis',
    ];

    protected $casts = [
        'score_factors' => 'array',
        'raw_report' => 'array',
        'analysis' => 'array',
        'total_debt' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }
}
