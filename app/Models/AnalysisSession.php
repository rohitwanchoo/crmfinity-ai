<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AnalysisSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'batch_id',
        'user_id',
        'application_id',
        'filename',
        'bank_name',
        'pages',
        'total_transactions',
        'total_credits',
        'total_debits',
        'total_returned',
        'returned_count',
        'net_flow',
        'true_revenue',
        'high_confidence_count',
        'medium_confidence_count',
        'low_confidence_count',
        'status',
        'analysis_type',
        'model_used',
        'api_cost',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'beginning_balance',
        'ending_balance',
    ];

    protected $casts = [
        'total_credits' => 'decimal:2',
        'total_debits' => 'decimal:2',
        'total_returned' => 'decimal:2',
        'net_flow' => 'decimal:2',
        'true_revenue' => 'decimal:2',
        'api_cost' => 'decimal:4',
        'beginning_balance' => 'decimal:2',
        'ending_balance' => 'decimal:2',
        'pages' => 'integer',
        'total_transactions' => 'integer',
        'returned_count' => 'integer',
        'high_confidence_count' => 'integer',
        'medium_confidence_count' => 'integer',
        'low_confidence_count' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->session_id)) {
                $model->session_id = 'MCA-'.strtoupper(Str::random(12));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function application()
    {
        return $this->belongsTo(MCAApplication::class);
    }

    public function transactions()
    {
        return $this->hasMany(AnalyzedTransaction::class);
    }

    public function apiUsageLogs()
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    /**
     * Create a session from analysis results
     */
    public static function createFromAnalysis(array $data, ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId,
            'filename' => $data['file'],
            'pages' => $data['pages'] ?? 0,
            'total_transactions' => $data['summary']['transaction_count'] ?? 0,
            'total_credits' => $data['summary']['total_credits'] ?? 0,
            'total_debits' => $data['summary']['total_debits'] ?? 0,
            'net_flow' => $data['summary']['net_flow'] ?? 0,
            'high_confidence_count' => $data['summary']['high_confidence'] ?? 0,
            'medium_confidence_count' => $data['summary']['medium_confidence'] ?? 0,
            'low_confidence_count' => $data['summary']['low_confidence'] ?? 0,
            'status' => 'completed',
        ]);
    }
}
