<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantProfile extends Model
{
    protected $fillable = [
        'merchant_name',
        'merchant_name_variations',
        'primary_category',
        'is_revenue_source',
        'revenue_type',
        'typical_amount_min',
        'typical_amount_max',
        'typical_frequency',
        'total_occurrences',
        'revenue_occurrences',
        'confidence_score',
        'last_seen',
    ];

    protected $casts = [
        'merchant_name_variations' => 'array',
        'is_revenue_source' => 'boolean',
        'typical_amount_min' => 'decimal:2',
        'typical_amount_max' => 'decimal:2',
        'total_occurrences' => 'integer',
        'revenue_occurrences' => 'integer',
        'confidence_score' => 'decimal:4',
        'last_seen' => 'datetime',
    ];
}
