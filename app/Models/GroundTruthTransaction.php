<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundTruthTransaction extends Model
{
    protected $fillable = [
        'session_id',
        'transaction_date',
        'description',
        'description_normalized',
        'amount',
        'transaction_type',
        'category',
        'is_revenue',
        'revenue_type',
        'merchant_name',
        'merchant_category',
        'confidence_score',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'is_revenue' => 'boolean',
        'confidence_score' => 'decimal:4',
    ];

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id', 'session_id');
    }
}
