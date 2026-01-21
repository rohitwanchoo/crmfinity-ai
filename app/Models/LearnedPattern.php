<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnedPattern extends Model
{
    protected $fillable = [
        'pattern_type',
        'pattern_key',
        'pattern_value',
        'classification',
        'revenue_type',
        'confidence_score',
        'occurrence_count',
        'accuracy_rate',
        'bank_specific',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:4',
        'accuracy_rate' => 'decimal:4',
        'occurrence_count' => 'integer',
        'pattern_value' => 'array', // Auto JSON encode/decode
    ];
}
