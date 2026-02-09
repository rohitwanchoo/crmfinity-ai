<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class McaOffer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'offer_id',
        'session_uuid',
        'user_id',
        'true_revenue_monthly',
        'revenue_override',
        'override_revenue',
        'existing_mca_payment',
        'withhold_percent',
        'cap_amount',
        'new_payment_available',
        'factor_rate',
        'term_months',
        'advance_amount',
        'total_payback',
        'monthly_payment',
        'max_funded_amount',
        'offer_name',
        'notes',
        'is_favorite',
        'metadata',
    ];

    protected $casts = [
        'true_revenue_monthly' => 'decimal:2',
        'revenue_override' => 'boolean',
        'override_revenue' => 'decimal:2',
        'existing_mca_payment' => 'decimal:2',
        'withhold_percent' => 'decimal:2',
        'cap_amount' => 'decimal:2',
        'new_payment_available' => 'decimal:2',
        'factor_rate' => 'decimal:4',
        'advance_amount' => 'decimal:2',
        'total_payback' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'max_funded_amount' => 'decimal:2',
        'is_favorite' => 'boolean',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->belongsTo(AnalysisSession::class, 'session_uuid', 'session_id');
    }
}
