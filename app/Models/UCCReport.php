<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UCCReport extends Model
{
    use HasFactory;

    protected $table = 'ucc_reports';

    protected $fillable = [
        'application_id', 'total_filings', 'active_filings', 'mca_related_filings',
        'blanket_liens', 'total_secured_amount', 'risk_score', 'risk_level',
        'filing_details', 'recommendation',
    ];

    protected $casts = [
        'filing_details' => 'array',
        'total_secured_amount' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }
}
