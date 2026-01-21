<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'bank_name',
        'bank_type',
        'statement_period_start',
        'statement_period_end',
        'statement_pdf_path',
        'ground_truth_pdf_path',
        'scorecard_pdf_path',
        'fcs_pdf_path',
        'total_transactions',
        'total_revenue_transactions',
        'processing_status',
        'processing_message',
        'completed_at',
    ];

    protected $casts = [
        'statement_period_start' => 'date',
        'statement_period_end' => 'date',
        'completed_at' => 'datetime',
        'total_transactions' => 'integer',
        'total_revenue_transactions' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groundTruthTransactions(): HasMany
    {
        return $this->hasMany(GroundTruthTransaction::class, 'session_id', 'session_id');
    }
}
