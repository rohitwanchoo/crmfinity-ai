<?php

namespace App\Services;

use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service to recalculate and update analysis summaries
 * when transactions are added, updated, or deleted.
 */
class AnalysisSummaryService
{
    protected TrueRevenueEngine $trueRevenueEngine;

    public function __construct()
    {
        $this->trueRevenueEngine = new TrueRevenueEngine();
    }

    /**
     * Recalculate all totals for an analysis session and update related records
     */
    public function recalculateSession(int $sessionId): array
    {
        $session = AnalysisSession::findOrFail($sessionId);

        // Get all transactions for this session
        $transactions = AnalyzedTransaction::where('analysis_session_id', $sessionId)->get();

        // Calculate basic totals
        $totalCredits = $transactions->where('type', 'credit')->sum('amount');
        $totalDebits = $transactions->where('type', 'debit')->sum('amount');
        $netFlow = $totalCredits - $totalDebits;

        // Calculate true revenue using TrueRevenueEngine
        $txnArray = $transactions->map(function ($t) {
            return [
                'description' => $t->description,
                'amount' => (float) $t->amount,
                'type' => $t->type,
                'date' => $t->transaction_date->format('Y-m-d'),
            ];
        })->toArray();

        $revenueResult = $this->trueRevenueEngine->calculateTrueRevenue($txnArray);
        $trueRevenue = $revenueResult['true_revenue'];

        // Update session
        $session->update([
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'net_flow' => $netFlow,
            'true_revenue' => $trueRevenue,
            'total_transactions' => $transactions->count(),
        ]);

        Log::info("AnalysisSummaryService: Updated session {$sessionId}", [
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'net_flow' => $netFlow,
            'true_revenue' => $trueRevenue,
        ]);

        // Update linked application document if exists
        $documentUpdated = $this->updateLinkedDocument($session);

        // Update application combined summary if exists
        $applicationUpdated = $this->updateApplicationSummary($session);

        return [
            'session_id' => $sessionId,
            'total_credits' => round($totalCredits, 2),
            'total_debits' => round($totalDebits, 2),
            'net_flow' => round($netFlow, 2),
            'true_revenue' => round($trueRevenue, 2),
            'total_transactions' => $transactions->count(),
            'revenue_breakdown' => [
                'revenue_items' => $revenueResult['counts']['revenue'],
                'excluded_items' => $revenueResult['counts']['excluded'],
                'needs_review_items' => $revenueResult['counts']['needs_review'],
                'excluded_amount' => $revenueResult['excluded_amount'],
            ],
            'document_updated' => $documentUpdated,
            'application_updated' => $applicationUpdated,
        ];
    }

    /**
     * Update the application_documents record linked to this session
     */
    protected function updateLinkedDocument(AnalysisSession $session): bool
    {
        $document = DB::table('application_documents')
            ->where('analysis_session_id', $session->id)
            ->first();

        if (!$document) {
            return false;
        }

        DB::table('application_documents')
            ->where('id', $document->id)
            ->update([
                'true_revenue' => $session->true_revenue,
                'total_credits' => $session->total_credits,
                'total_debits' => $session->total_debits,
                'transaction_count' => $session->total_transactions,
                'updated_at' => now(),
            ]);

        Log::info("AnalysisSummaryService: Updated document {$document->id}", [
            'true_revenue' => $session->true_revenue,
            'total_credits' => $session->total_credits,
        ]);

        return true;
    }

    /**
     * Update the combined analysis summary for an application
     */
    protected function updateApplicationSummary(AnalysisSession $session): bool
    {
        // First check if session is linked to an application
        if ($session->application_id) {
            return $this->recalculateApplicationFromSessions($session->application_id);
        }

        // Check if there's a document linked that has an application
        $document = DB::table('application_documents')
            ->where('analysis_session_id', $session->id)
            ->first();

        if ($document && $document->application_id) {
            return $this->recalculateApplicationFromDocuments($document->application_id);
        }

        return false;
    }

    /**
     * Recalculate application summary from all linked analysis sessions
     */
    public function recalculateApplicationFromSessions(int $applicationId): bool
    {
        $sessions = AnalysisSession::where('application_id', $applicationId)->get();

        if ($sessions->isEmpty()) {
            return false;
        }

        $totalCredits = $sessions->sum('total_credits');
        $totalDebits = $sessions->sum('total_debits');
        $totalTrueRevenue = $sessions->sum('true_revenue');

        // Store in verification_results as bank_analysis type
        $this->saveBankAnalysisVerification($applicationId, [
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'total_true_revenue' => $totalTrueRevenue,
            'net_flow' => $totalCredits - $totalDebits,
            'statements_analyzed' => $sessions->count(),
            'updated_at' => now()->toIso8601String(),
        ]);

        Log::info("AnalysisSummaryService: Updated application {$applicationId} from sessions", [
            'statements' => $sessions->count(),
            'total_true_revenue' => $totalTrueRevenue,
        ]);

        return true;
    }

    /**
     * Recalculate application summary from all bank statement documents
     */
    public function recalculateApplicationFromDocuments(int $applicationId): bool
    {
        $documents = DB::table('application_documents')
            ->where('application_id', $applicationId)
            ->where('document_type', 'bank_statement')
            ->whereNotNull('true_revenue')
            ->get();

        if ($documents->isEmpty()) {
            return false;
        }

        $totalCredits = $documents->sum('total_credits');
        $totalDebits = $documents->sum('total_debits');
        $totalTrueRevenue = $documents->sum('true_revenue');

        // Store in verification_results as bank_analysis type
        $this->saveBankAnalysisVerification($applicationId, [
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'total_true_revenue' => $totalTrueRevenue,
            'net_flow' => $totalCredits - $totalDebits,
            'statements_analyzed' => $documents->count(),
            'updated_at' => now()->toIso8601String(),
        ]);

        Log::info("AnalysisSummaryService: Updated application {$applicationId} from documents", [
            'documents' => $documents->count(),
            'total_true_revenue' => $totalTrueRevenue,
        ]);

        return true;
    }

    /**
     * Save or update bank analysis verification result
     */
    protected function saveBankAnalysisVerification(int $applicationId, array $data): void
    {
        $existing = DB::table('verification_results')
            ->where('application_id', $applicationId)
            ->where('verification_type', 'bank_analysis')
            ->first();

        $verificationData = [
            'application_id' => $applicationId,
            'verification_type' => 'bank_analysis',
            'provider' => 'internal',
            'status' => 'completed',
            'parsed_data' => json_encode($data),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('verification_results')
                ->where('id', $existing->id)
                ->update($verificationData);
        } else {
            $verificationData['created_at'] = now();
            DB::table('verification_results')->insert($verificationData);
        }
    }

    /**
     * Link an analysis session to an application
     */
    public function linkSessionToApplication(int $sessionId, int $applicationId): bool
    {
        $session = AnalysisSession::find($sessionId);
        if (!$session) {
            return false;
        }

        $session->application_id = $applicationId;
        $session->save();

        // Recalculate application summary
        $this->recalculateApplicationFromSessions($applicationId);

        return true;
    }

    /**
     * Get combined analysis summary for an application
     */
    public function getApplicationSummary(int $applicationId): ?array
    {
        // Try from verification_results first
        $verification = DB::table('verification_results')
            ->where('application_id', $applicationId)
            ->where('verification_type', 'bank_analysis')
            ->first();

        if ($verification && $verification->parsed_data) {
            return json_decode($verification->parsed_data, true);
        }

        // Calculate from documents
        $documents = DB::table('application_documents')
            ->where('application_id', $applicationId)
            ->where('document_type', 'bank_statement')
            ->whereNotNull('true_revenue')
            ->get();

        if ($documents->isEmpty()) {
            return null;
        }

        return [
            'total_credits' => $documents->sum('total_credits'),
            'total_debits' => $documents->sum('total_debits'),
            'total_true_revenue' => $documents->sum('true_revenue'),
            'net_flow' => $documents->sum('total_credits') - $documents->sum('total_debits'),
            'statements_analyzed' => $documents->count(),
        ];
    }
}
