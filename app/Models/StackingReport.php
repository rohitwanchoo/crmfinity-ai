<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StackingReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id', 'provider', 'active_mcas', 'defaulted_mcas',
        'total_exposure', 'risk_score', 'risk_level', 'mca_details', 'recommendation',
    ];

    protected $casts = [
        'mca_details' => 'array',
        'total_exposure' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }
}
