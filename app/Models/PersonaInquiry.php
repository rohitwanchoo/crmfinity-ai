<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id', 'inquiry_id', 'status', 'reference_id',
        'inquiry_data', 'verification_results', 'completed_at',
    ];

    protected $casts = [
        'inquiry_data' => 'array',
        'verification_results' => 'array',
        'completed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }
}
