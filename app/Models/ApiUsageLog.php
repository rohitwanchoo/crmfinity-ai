<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ApiUsageLog extends Model
{
    protected $fillable = [
        'analysis_session_id',
        'user_id',
        'api_provider',
        'model_used',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'input_cost',
        'output_cost',
        'total_cost',
        'extraction_method',
        'response_time_ms',
        'status',
        'error_message',
        'endpoint',
        'metadata',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'input_cost' => 'decimal:6',
        'output_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'response_time_ms' => 'integer',
        'metadata' => 'array',
    ];

    // Relationships
    public function analysisSession()
    {
        return $this->belongsTo(AnalysisSession::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('api_provider', $provider);
    }

    public function scopeByModel($query, string $model)
    {
        return $query->where('model_used', $model);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Static helper methods for statistics
    public static function getTotalCost($startDate = null, $endDate = null, $userId = null)
    {
        $query = self::query();

        if ($startDate && $endDate) {
            $query->inDateRange($startDate, $endDate);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->sum('total_cost');
    }

    public static function getTotalTokens($startDate = null, $endDate = null, $userId = null)
    {
        $query = self::query();

        if ($startDate && $endDate) {
            $query->inDateRange($startDate, $endDate);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->sum('total_tokens');
    }

    public static function getCostByModel($startDate = null, $endDate = null)
    {
        $query = self::query()
            ->select('model_used', DB::raw('SUM(total_cost) as total_cost'), DB::raw('SUM(total_tokens) as total_tokens'), DB::raw('COUNT(*) as request_count'))
            ->groupBy('model_used');

        if ($startDate && $endDate) {
            $query->inDateRange($startDate, $endDate);
        }

        return $query->get();
    }

    public static function getCostByUser($startDate = null, $endDate = null)
    {
        $query = self::query()
            ->select('user_id', DB::raw('SUM(total_cost) as total_cost'), DB::raw('SUM(total_tokens) as total_tokens'), DB::raw('COUNT(*) as request_count'))
            ->groupBy('user_id');

        if ($startDate && $endDate) {
            $query->inDateRange($startDate, $endDate);
        }

        return $query->with('user')->get();
    }

    public static function getCostByProvider($startDate = null, $endDate = null)
    {
        $query = self::query()
            ->select('api_provider', DB::raw('SUM(total_cost) as total_cost'), DB::raw('SUM(total_tokens) as total_tokens'), DB::raw('COUNT(*) as request_count'))
            ->groupBy('api_provider');

        if ($startDate && $endDate) {
            $query->inDateRange($startDate, $endDate);
        }

        return $query->get();
    }

    public static function getDailyUsage($days = 30)
    {
        return self::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_cost) as total_cost'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('COUNT(*) as request_count')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();
    }

    // Instance helper methods
    public function costPerToken()
    {
        if ($this->total_tokens == 0) {
            return 0;
        }

        return $this->total_cost / $this->total_tokens;
    }

    public function averageResponseTime()
    {
        return $this->response_time_ms ? $this->response_time_ms / 1000 : null;
    }
}
