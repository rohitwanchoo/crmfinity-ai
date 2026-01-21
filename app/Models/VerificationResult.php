<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id', 'verification_type', 'provider', 'status',
        'score', 'risk_level', 'raw_response', 'parsed_data', 'flags', 'external_id',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'parsed_data' => 'array',
        'flags' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }
}
