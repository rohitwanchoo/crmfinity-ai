<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class McaLenderGuideline extends Model
{
    use HasFactory;

    protected $table = 'mca_lender_guidelines';

    protected $fillable = [
        'lender_id',
        'lender_name',
        // Financial Criteria
        'min_credit_score',
        'min_time_in_business',
        'min_loan_amount',
        'max_loan_amount',
        'max_negative_days',
        'max_nsfs',
        'min_monthly_deposits',
        'max_positions',
        'min_avg_daily_balance',
        // Business Type
        'sole_proprietors',
        'home_based_business',
        'consolidation_deals',
        'non_profits',
        // Geographic
        'restricted_states',
        'excluded_industries',
        // Funding Terms
        'funding_speed',
        'factor_rate',
        'max_term',
        'payment_frequency',
        'product_type',
        'bonus_available',
        'bonus_details',
        // Special Circumstances
        'bankruptcy',
        'tax_lien',
        'prior_default',
        'criminal_history',
        // Status
        'status',
        'white_label',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'min_credit_score'       => 'integer',
        'min_time_in_business'   => 'integer',
        'min_loan_amount'        => 'decimal:2',
        'max_loan_amount'        => 'decimal:2',
        'max_negative_days'      => 'integer',
        'max_nsfs'               => 'integer',
        'min_monthly_deposits'   => 'decimal:2',
        'max_positions'          => 'integer',
        'min_avg_daily_balance'  => 'decimal:2',
        'bonus_available'        => 'boolean',
        'white_label'            => 'boolean',
        'restricted_states'      => 'array',
        'excluded_industries'    => 'array',
    ];

    public function patterns()
    {
        return $this->hasMany(McaPattern::class, 'lender_id', 'lender_id');
    }
}
