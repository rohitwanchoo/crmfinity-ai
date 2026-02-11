<?php

namespace App\Http\Controllers;

use App\Models\ApiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiUsageController extends Controller
{
    /**
     * Convert Claude model names to LSC names
     */
    protected function getDisplayModelName($modelName)
    {
        $mapping = [
            'claude-haiku-4-5' => 'LSC Basic',
            'claude-sonnet-4-5' => 'LSC Pro',
            'claude-opus-4-6' => 'LSC Max',
        ];

        return $mapping[$modelName] ?? $modelName;
    }
    /**
     * Display the API usage dashboard
     */
    public function index(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        // Get overall statistics from historical analysis sessions
        $totalCost = \App\Models\AnalysisSession::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->sum('api_cost');

        $totalTokens = \App\Models\AnalysisSession::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->sum('total_tokens');

        $totalRequests = \App\Models\AnalysisSession::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('api_cost')
            ->count();

        // Get cost by model from analysis sessions
        $costByModel = \App\Models\AnalysisSession::select('model_used')
            ->selectRaw('SUM(api_cost) as total_cost')
            ->selectRaw('SUM(total_tokens) as total_tokens')
            ->selectRaw('COUNT(*) as request_count')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('model_used')
            ->groupBy('model_used')
            ->get()
            ->map(function($model) {
                $model->display_name = $this->getDisplayModelName($model->model_used);
                return $model;
            });

        // Get cost by provider (all are anthropic/LSC)
        $costByProvider = collect([
            (object)[
                'api_provider' => 'LSC',
                'total_cost' => $totalCost,
                'total_tokens' => $totalTokens,
                'request_count' => $totalRequests,
            ]
        ]);

        // Get cost by user from analysis sessions
        $costByUser = \App\Models\AnalysisSession::select('user_id')
            ->selectRaw('SUM(api_cost) as total_cost')
            ->selectRaw('SUM(total_tokens) as total_tokens')
            ->selectRaw('COUNT(*) as request_count')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->with('user')
            ->get();

        // Get daily usage from analysis sessions
        $dailyUsage = \App\Models\AnalysisSession::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(api_cost) as total_cost'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('COUNT(*) as request_count')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Get average cost per request
        $avgCostPerRequest = $totalRequests > 0 ? $totalCost / $totalRequests : 0;

        // Get average tokens per request
        $avgTokensPerRequest = $totalRequests > 0 ? $totalTokens / $totalRequests : 0;

        // Recent requests from analysis sessions
        $recentRequests = \App\Models\AnalysisSession::with('user')
            ->whereNotNull('api_cost')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($session) {
                return (object)[
                    'created_at' => $session->created_at,
                    'user' => $session->user,
                    'model_used' => $this->getDisplayModelName($session->model_used ?? 'N/A'),
                    'input_tokens' => $session->input_tokens ?? 0,
                    'output_tokens' => $session->output_tokens ?? 0,
                    'total_cost' => $session->api_cost,
                    'status' => 'success',
                ];
            });

        return view('api-usage.index', compact(
            'days',
            'totalCost',
            'totalTokens',
            'totalRequests',
            'costByModel',
            'costByProvider',
            'costByUser',
            'dailyUsage',
            'avgCostPerRequest',
            'avgTokensPerRequest',
            'recentRequests'
        ));
    }

    /**
     * Get statistics for a specific date range (API endpoint)
     */
    public function getStats(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'user_id' => 'nullable|integer',
        ]);

        $startDate = $request->get('start_date', now()->subDays(30));
        $endDate = $request->get('end_date', now());
        $userId = $request->get('user_id');

        $stats = [
            'total_cost' => ApiUsageLog::getTotalCost($startDate, $endDate, $userId),
            'total_tokens' => ApiUsageLog::getTotalTokens($startDate, $endDate, $userId),
            'total_requests' => ApiUsageLog::query()
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'cost_by_model' => ApiUsageLog::getCostByModel($startDate, $endDate),
            'cost_by_provider' => ApiUsageLog::getCostByProvider($startDate, $endDate),
        ];

        return response()->json($stats);
    }

    /**
     * Export API usage data to CSV
     */
    public function exportCsv(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        $logs = \App\Models\AnalysisSession::with('user')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('api_cost')
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'api_usage_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'Date',
                'User',
                'Session ID',
                'API Provider',
                'Model',
                'Input Tokens',
                'Output Tokens',
                'Total Tokens',
                'Input Cost',
                'Output Cost',
                'Total Cost',
                'Status',
            ]);

            // CSV rows
            foreach ($logs as $session) {
                fputcsv($file, [
                    $session->created_at->format('Y-m-d H:i:s'),
                    $session->user ? $session->user->username : 'N/A',
                    $session->session_id,
                    'LSC',
                    $this->getDisplayModelName($session->model_used ?? 'N/A'),
                    $session->input_tokens ?? 0,
                    $session->output_tokens ?? 0,
                    $session->total_tokens ?? 0,
                    0, // input_cost not stored separately
                    0, // output_cost not stored separately
                    $session->api_cost,
                    'success',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
