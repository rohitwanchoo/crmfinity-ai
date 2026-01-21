<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id', 'document_type', 'statement_period', 'filename', 'original_filename',
        'mime_type', 'file_size', 'storage_path', 'is_processed', 'extracted_data',
        'analysis_session_id', 'true_revenue', 'total_credits', 'total_debits',
        'transaction_count', 'analyzed_at', 'fcs_report_path',
    ];

    protected $casts = [
        'is_processed' => 'boolean',
        'extracted_data' => 'array',
        'analyzed_at' => 'datetime',
        'true_revenue' => 'decimal:2',
        'total_credits' => 'decimal:2',
        'total_debits' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }

    public function analysisSession(): BelongsTo
    {
        return $this->belongsTo(AnalysisSession::class, 'analysis_session_id');
    }
}
