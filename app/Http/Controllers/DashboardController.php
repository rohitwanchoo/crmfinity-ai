<?php

namespace App\Http\Controllers;

use App\Models\AnalysisSession;
use App\Models\ApiUsageLog;
use App\Models\LearnedPattern;
use App\Models\MCAApplication;
use App\Models\McaPattern;
use App\Models\MerchantProfile;
use App\Models\TrainingSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Get known lenders and debt collectors count
        $knownLenders = McaPattern::getKnownLenders();
        $knownDebtCollectors = McaPattern::getKnownDebtCollectors();

        // Get API usage statistics from historical analysis sessions (last 30 days)
        $startDate = now()->subDays(30);
        $endDate = now();

        $totalCost = AnalysisSession::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->sum('api_cost');

        $totalTokens = AnalysisSession::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->sum('total_tokens');

        $totalRequests = AnalysisSession::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('api_cost')
            ->count();

        $apiStats = [
            'total_cost' => (float) $totalCost,
            'total_tokens' => (int) $totalTokens,
            'total_requests' => $totalRequests,
        ];

        \Log::info('Dashboard API Stats', [
            'total_cost' => $totalCost,
            'total_tokens' => $totalTokens,
            'total_requests' => $totalRequests,
            'apiStats_array' => $apiStats,
        ]);

        // Get dashboard statistics
        $stats = [
            'total_sessions' => TrainingSession::count(),
            'user_sessions' => TrainingSession::where('user_id', $user->id)->count(),
            'total_transactions' => TrainingSession::sum('total_transactions'),
            'learned_patterns' => LearnedPattern::count(),
            'merchant_profiles' => MerchantProfile::count(),
            'total_lenders' => count($knownLenders),
            'total_debt_collectors' => count($knownDebtCollectors),
            'detected_lenders' => McaPattern::where('is_mca', true)->distinct('lender_id')->count('lender_id'),
            'total_detections' => McaPattern::where('is_mca', true)->sum('usage_count'),
            'bank_statements_analyzed' => TrainingSession::count(),
            'recent_sessions' => TrainingSession::with('user')
                ->latest()
                ->take(5)
                ->get(),
            'api_cost' => $apiStats['total_cost'],
            'api_tokens' => $apiStats['total_tokens'],
            'api_requests' => $apiStats['total_requests'],
        ];

        \Log::info('Stats array api_cost value', ['api_cost' => $stats['api_cost']]);

        // Top lenders by detection count
        $topLenders = McaPattern::select('lender_id', 'lender_name')
            ->selectRaw('SUM(usage_count) as total_detections')
            ->where('is_mca', true)
            ->whereNotIn('lender_id', array_keys($knownDebtCollectors))
            ->groupBy('lender_id', 'lender_name')
            ->orderByDesc('total_detections')
            ->take(5)
            ->get();

        // Top debt collectors by detection count
        $topDebtCollectors = McaPattern::select('lender_id', 'lender_name')
            ->selectRaw('SUM(usage_count) as total_detections')
            ->where('is_mca', true)
            ->whereIn('lender_id', array_keys($knownDebtCollectors))
            ->groupBy('lender_id', 'lender_name')
            ->orderByDesc('total_detections')
            ->take(5)
            ->get();

        // Underwriting score statistics
        $uwStats = [
            'total_applications' => MCAApplication::count(),
            'pending' => MCAApplication::where('status', 'pending')->orWhereNull('status')->count(),
            'approved' => MCAApplication::where('underwriting_decision', 'APPROVE')
                ->orWhere('underwriting_decision', 'CONDITIONAL_APPROVE')->count(),
            'declined' => MCAApplication::where('underwriting_decision', 'DECLINE')->count(),
            'scored' => MCAApplication::whereNotNull('underwriting_score')->count(),
            'avg_score' => round(MCAApplication::whereNotNull('underwriting_score')->avg('underwriting_score') ?? 0),
            'by_decision' => MCAApplication::whereNotNull('underwriting_decision')
                ->select('underwriting_decision', DB::raw('count(*) as count'))
                ->groupBy('underwriting_decision')
                ->pluck('count', 'underwriting_decision')
                ->toArray(),
            'by_score_range' => [
                'high' => MCAApplication::where('underwriting_score', '>=', 75)->count(),
                'medium' => MCAApplication::whereBetween('underwriting_score', [45, 74])->count(),
                'low' => MCAApplication::where('underwriting_score', '<', 45)->count(),
            ],
            'recent_applications' => MCAApplication::orderBy('created_at', 'desc')
                ->take(5)
                ->get(['id', 'business_name', 'underwriting_score', 'underwriting_decision', 'status', 'created_at']),
            'recent_scored' => MCAApplication::whereNotNull('underwriting_score')
                ->orderBy('underwriting_calculated_at', 'desc')
                ->take(5)
                ->get(['id', 'business_name', 'underwriting_score', 'underwriting_decision', 'underwriting_calculated_at']),
        ];

        // Recent MCA patterns detected
        $recentPatterns = McaPattern::where('is_mca', true)
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get(['lender_id', 'lender_name', 'usage_count', 'updated_at']);

        return view('dashboard', compact('stats', 'uwStats', 'topLenders', 'topDebtCollectors', 'recentPatterns'));
    }
}
