<?php

namespace App\Http\Controllers;

use App\Mail\FCSReportMail;
use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Models\ApplicationDocument;
use App\Models\BankLinkRequest;
use App\Models\MCAApplication;
use App\Models\TransactionCorrection;
use App\Notifications\BankLinkRequestNotification;
use App\Services\AnalysisSummaryService;
use App\Services\ApplicationFlowService;
use App\Services\BankAnalysisService;
use App\Services\BankPatternService;
use App\Services\DataMerchService;
use App\Services\ExperianService;
use App\Services\PacerService;
use App\Services\PersonaService;
use App\Services\PlaidService;
use App\Services\RiskScoringService;
use App\Services\UCCService;
use App\Services\UnderwritingScoreService;
use App\Services\VerificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    protected PersonaService $personaService;
    protected ExperianService $experianService;
    protected DataMerchService $dataMerchService;
    protected UCCService $uccService;
    protected PacerService $pacerService;
    protected RiskScoringService $riskScoringService;
    protected PlaidService $plaidService;
    protected UnderwritingScoreService $underwritingScoreService;
    protected VerificationService $verificationService;
    protected BankAnalysisService $bankAnalysisService;
    protected ApplicationFlowService $flowService;
    protected AnalysisSummaryService $analysisSummaryService;

    public function __construct(
        PersonaService $personaService,
        ExperianService $experianService,
        DataMerchService $dataMerchService,
        UCCService $uccService,
        PacerService $pacerService,
        RiskScoringService $riskScoringService,
        PlaidService $plaidService,
        UnderwritingScoreService $underwritingScoreService,
        VerificationService $verificationService,
        BankAnalysisService $bankAnalysisService,
        ApplicationFlowService $flowService,
        AnalysisSummaryService $analysisSummaryService
    ) {
        $this->personaService = $personaService;
        $this->experianService = $experianService;
        $this->dataMerchService = $dataMerchService;
        $this->uccService = $uccService;
        $this->pacerService = $pacerService;
        $this->riskScoringService = $riskScoringService;
        $this->underwritingScoreService = $underwritingScoreService;
        $this->plaidService = $plaidService;
        $this->verificationService = $verificationService;
        $this->bankAnalysisService = $bankAnalysisService;
        $this->flowService = $flowService;
        $this->analysisSummaryService = $analysisSummaryService;
    }

    public function index(Request $request)
    {
        $query = MCAApplication::with(['user', 'assignedTo'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('business_email', 'like', "%{$search}%")
                    ->orWhere('owner_first_name', 'like', "%{$search}%")
                    ->orWhere('owner_last_name', 'like', "%{$search}%");
            });
        }

        // Underwriting score filter
        if ($request->filled('uw_score')) {
            $uwFilter = $request->uw_score;
            match ($uwFilter) {
                'high' => $query->where('underwriting_score', '>=', 75),
                'medium' => $query->whereBetween('underwriting_score', [45, 74]),
                'low' => $query->where('underwriting_score', '<', 45),
                'none' => $query->whereNull('underwriting_score'),
                default => null,
            };
        }

        // Underwriting decision filter
        if ($request->filled('uw_decision')) {
            $query->where('underwriting_decision', $request->uw_decision);
        }

        $applications = $query->paginate(20);

        $stats = [
            'total' => MCAApplication::count(),
            'submitted' => MCAApplication::where('status', 'submitted')->count(),
            'under_review' => MCAApplication::where('status', 'under_review')->count(),
            'approved' => MCAApplication::where('status', 'approved')->count(),
            'declined' => MCAApplication::where('status', 'declined')->count(),
        ];

        return view('applications.index', compact('applications', 'stats'));
    }

    public function create()
    {
        return view('applications.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'dba_name' => 'nullable|string|max:255',
            'ein' => 'nullable|string|max:20',
            'business_phone' => 'nullable|string|max:20',
            'business_email' => 'nullable|email|max:255',
            'business_address' => 'nullable|string|max:500',
            'business_city' => 'nullable|string|max:100',
            'business_state' => 'nullable|string|max:2',
            'business_zip' => 'nullable|string|max:10',
            'business_start_date' => 'nullable|date',
            'industry' => 'nullable|string|max:100',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'requested_amount' => 'required|numeric|min:0',
            'use_of_funds' => 'nullable|string|max:500',
            'owner_first_name' => 'required|string|max:100',
            'owner_last_name' => 'required|string|max:100',
            'owner_email' => 'required|email|max:255',
            'owner_phone' => 'nullable|string|max:20',
            'owner_ssn_last4' => 'nullable|string|max:4',
            'owner_dob' => 'nullable|date',
            'owner_address' => 'nullable|string|max:500',
            'owner_city' => 'nullable|string|max:100',
            'owner_state' => 'nullable|string|max:2',
            'owner_zip' => 'nullable|string|max:10',
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'submitted';

        $application = MCAApplication::create($validated);

        $this->addNote($application, 'system', 'Application submitted');

        return redirect()->route('applications.show', $application)
            ->with('success', 'Application created successfully.');
    }

    public function show(MCAApplication $application)
    {
        $application->load([
            'user.plaidItems.accounts',
            'assignedTo',
            'personaInquiries',
            'creditReports',
            'stackingReports',
            'uccReports',
            'pacerReports',
            'verificationResults',
            'documents',
            'notes.user',
        ]);

        // Get complete flow status for the view
        $flowStatus = $application->getCompleteFlowStatus();
        $recommendedActions = $this->flowService->getRecommendedActions($application);

        return view('applications.show', compact('application', 'flowStatus', 'recommendedActions'));
    }

    /**
     * Get application flow status (API endpoint)
     */
    public function getFlowStatus(MCAApplication $application)
    {
        return response()->json([
            'success' => true,
            'flow_status' => $application->getCompleteFlowStatus(),
            'recommended_actions' => $this->flowService->getRecommendedActions($application),
            'can_advance' => $this->flowService->canAdvancePhase($application),
        ]);
    }

    public function edit(MCAApplication $application)
    {
        return view('applications.edit', compact('application'));
    }

    public function update(Request $request, MCAApplication $application)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'dba_name' => 'nullable|string|max:255',
            'ein' => 'nullable|string|max:20',
            'business_phone' => 'nullable|string|max:20',
            'business_email' => 'nullable|email|max:255',
            'business_address' => 'nullable|string|max:500',
            'business_city' => 'nullable|string|max:100',
            'business_state' => 'nullable|string|max:2',
            'business_zip' => 'nullable|string|max:10',
            'business_start_date' => 'nullable|date',
            'industry' => 'nullable|string|max:100',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'requested_amount' => 'required|numeric|min:0',
            'use_of_funds' => 'nullable|string|max:500',
            'owner_first_name' => 'required|string|max:100',
            'owner_last_name' => 'required|string|max:100',
            'owner_email' => 'required|email|max:255',
            'owner_phone' => 'nullable|string|max:20',
            'owner_dob' => 'nullable|date',
            'owner_address' => 'nullable|string|max:500',
            'owner_city' => 'nullable|string|max:100',
            'owner_state' => 'nullable|string|max:2',
            'owner_zip' => 'nullable|string|max:10',
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $application->update($validated);

        $this->addNote($application, 'update', 'Application details updated');

        return redirect()->route('applications.show', $application)
            ->with('success', 'Application updated successfully.');
    }

    public function updateStatus(Request $request, MCAApplication $application)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,submitted,processing,under_review,approved,declined,funded,closed',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Use flow service for validated transitions
        $result = $this->flowService->transitionTo(
            $application,
            $validated['status'],
            $validated['notes'] ?? null
        );

        if (!$result['success']) {
            return redirect()->route('applications.show', $application)
                ->with('error', $result['error'] ?? 'Invalid status transition.');
        }

        return redirect()->route('applications.show', $application)
            ->with('success', 'Application status updated to ' . $validated['status'] . '.');
    }

    public function runVerification(Request $request, MCAApplication $application)
    {
        $validated = $request->validate([
            'type' => 'required|in:persona,experian_credit,experian_business,datamerch,ucc,pacer',
        ]);

        $type = $validated['type'];

        // Use the verification service
        $result = $this->verificationService->runVerification($application, $type);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . ($result['error'] ?? 'Unknown error'),
            ], 500);
        }

        // Check if we can auto-advance the application status
        $advanceResult = $this->flowService->autoAdvance($application);

        return response()->json([
            'success' => true,
            'message' => $result['label'] . ' verification completed',
            'data' => $result['data'],
            'flow_advanced' => $advanceResult['advanced'],
            'current_status' => $application->fresh()->status,
            'verification_summary' => $this->verificationService->getVerificationSummary($application),
        ]);
    }

    /**
     * Run all required verifications at once
     */
    public function runAllVerifications(MCAApplication $application)
    {
        $result = $this->verificationService->runAllRequired($application);

        // Auto-advance if all required verifications complete
        $advanceResult = $this->flowService->autoAdvance($application);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? 'All required verifications completed'
                : 'Some verifications failed',
            'results' => $result['results'],
            'flow_advanced' => $advanceResult['advanced'],
            'current_status' => $application->fresh()->status,
        ]);
    }

    protected function runPersonaVerification(MCAApplication $application)
    {
        $inquiryData = $this->personaService->createInquiry([
            'reference_id' => 'APP-'.$application->id,
            'first_name' => $application->owner_first_name,
            'last_name' => $application->owner_last_name,
            'email' => $application->owner_email,
        ]);

        $application->personaInquiries()->create([
            'inquiry_id' => $inquiryData['id'] ?? null,
            'status' => 'pending',
            'reference_id' => 'APP-'.$application->id,
            'inquiry_data' => $inquiryData,
        ]);

        $this->addNote($application, 'verification', 'Persona identity verification initiated');

        return $inquiryData;
    }

    protected function runExperianCreditCheck(MCAApplication $application)
    {
        $reportData = $this->experianService->getCreditReport([
            'firstName' => $application->owner_first_name,
            'lastName' => $application->owner_last_name,
            'ssn' => $application->owner_ssn,
            'dob' => $application->owner_dob,
            'address' => $application->owner_address,
            'city' => $application->owner_city,
            'state' => $application->owner_state,
            'zip' => $application->owner_zip,
        ]);

        $application->creditReports()->create([
            'report_type' => 'personal',
            'provider' => 'experian',
            'credit_score' => $reportData['score'] ?? null,
            'score_factors' => $reportData['factors'] ?? [],
            'open_accounts' => $reportData['openAccounts'] ?? 0,
            'delinquent_accounts' => $reportData['delinquentAccounts'] ?? 0,
            'total_debt' => $reportData['totalDebt'] ?? 0,
            'bankruptcies' => $reportData['bankruptcies'] ?? 0,
            'raw_report' => $reportData,
        ]);

        $this->addNote($application, 'verification', 'Experian credit report pulled');

        return $reportData;
    }

    protected function runExperianBusinessCheck(MCAApplication $application)
    {
        $reportData = $this->experianService->getBusinessReport([
            'businessName' => $application->business_name,
            'ein' => $application->business_ein,
            'address' => $application->business_address,
            'city' => $application->business_city,
            'state' => $application->business_state,
            'zip' => $application->business_zip,
        ]);

        $application->creditReports()->create([
            'report_type' => 'business',
            'provider' => 'experian',
            'credit_score' => $reportData['intelliscore'] ?? null,
            'raw_report' => $reportData,
            'analysis' => [
                'years_in_business' => $reportData['yearsInBusiness'] ?? null,
                'payment_trend' => $reportData['paymentTrend'] ?? null,
                'risk_class' => $reportData['riskClass'] ?? null,
            ],
        ]);

        $this->addNote($application, 'verification', 'Experian business credit report pulled');

        return $reportData;
    }

    protected function runDataMerchCheck(MCAApplication $application)
    {
        $searchData = $this->dataMerchService->searchMerchant([
            'business_name' => $application->business_name,
            'ein' => $application->business_ein,
            'owner_name' => $application->owner_first_name.' '.$application->owner_last_name,
            'owner_ssn' => $application->owner_ssn,
        ]);

        $riskScore = $this->dataMerchService->calculateStackingRisk($searchData);
        $riskLevel = $riskScore >= 70 ? 'high' : ($riskScore >= 40 ? 'medium' : 'low');

        $application->stackingReports()->create([
            'provider' => 'datamerch',
            'active_mcas' => $searchData['activeMCAs'] ?? 0,
            'defaulted_mcas' => $searchData['defaultedMCAs'] ?? 0,
            'total_exposure' => $searchData['totalExposure'] ?? 0,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'mca_details' => $searchData['mcaDetails'] ?? [],
            'recommendation' => $this->dataMerchService->generateRecommendation($searchData),
        ]);

        $this->addNote($application, 'verification', 'DataMerch stacking check completed');

        return $searchData;
    }

    protected function runUCCSearch(MCAApplication $application)
    {
        $searchData = $this->uccService->searchFilings([
            'business_name' => $application->business_name,
            'state' => $application->business_state,
            'owner_name' => $application->owner_first_name.' '.$application->owner_last_name,
        ]);

        $analysis = $this->uccService->analyzeFilings($searchData);

        $application->uccReports()->create([
            'total_filings' => $analysis['totalFilings'] ?? 0,
            'active_filings' => $analysis['activeFilings'] ?? 0,
            'mca_related_filings' => $analysis['mcaRelatedFilings'] ?? 0,
            'blanket_liens' => $analysis['blanketLiens'] ?? 0,
            'total_secured_amount' => $analysis['totalSecuredAmount'] ?? 0,
            'risk_score' => $analysis['riskScore'] ?? 0,
            'risk_level' => $analysis['riskLevel'] ?? 'low',
            'filing_details' => $searchData,
            'recommendation' => $analysis['recommendation'] ?? null,
        ]);

        $this->addNote($application, 'verification', 'UCC filing search completed');

        return $searchData;
    }

    protected function runPacerSearch(MCAApplication $application)
    {
        // Check if PACER is configured
        if (! $this->pacerService->isEnabled()) {
            throw new \Exception('PACER integration is not configured. Please configure it in Settings > Configuration.');
        }

        $searchName = $application->owner_first_name.' '.$application->owner_last_name;

        // Search by owner name first
        $searchData = $this->pacerService->searchByPartyName($searchName, [
            'search_type' => 'all',
        ]);

        // Also search by business name if different
        if ($application->business_name && strtolower($application->business_name) !== strtolower($searchName)) {
            $businessSearchData = $this->pacerService->searchByPartyName($application->business_name, [
                'search_type' => 'all',
            ]);

            // Merge results
            if (! empty($businessSearchData['content'])) {
                $searchData['content'] = array_merge(
                    $searchData['content'] ?? [],
                    $businessSearchData['content'] ?? []
                );
            }
        }

        // Analyze the court records
        $analysis = $this->pacerService->analyzeCourtRecords($searchData);

        // Save the PACER report
        $application->pacerReports()->create([
            'total_cases' => $analysis['total_cases'] ?? 0,
            'bankruptcy_cases' => $analysis['bankruptcy_cases'] ?? 0,
            'civil_cases' => $analysis['civil_litigation'] ?? 0,
            'judgments' => $analysis['judgments'] ?? 0,
            'risk_score' => $analysis['risk_score'] ?? 100,
            'risk_level' => $analysis['risk_level'] ?? 'low',
            'case_details' => $searchData['content'] ?? [],
            'bankruptcy_details' => $analysis['bankruptcy_details'] ?? [],
            'judgment_details' => $analysis['judgment_details'] ?? [],
            'recommendation' => $analysis['recommendation'] ?? null,
            'flags' => $analysis['flags'] ?? [],
            'search_name' => $searchName,
            'search_type' => 'all',
        ]);

        $this->addNote($application, 'verification', 'PACER court records search completed');

        return [
            'search_data' => $searchData,
            'analysis' => $analysis,
        ];
    }

    public function calculateRiskScore(MCAApplication $application)
    {
        $application->load([
            'creditReports',
            'stackingReports',
            'uccReports',
            'plaidItems.accounts',
        ]);

        $riskData = $this->riskScoringService->calculateOverallRisk($application);

        $application->update([
            'risk_score' => $riskData['overall_score'],
            'risk_factors' => $riskData['factors'],
        ]);

        $this->addNote($application, 'risk_assessment',
            "Risk score calculated: {$riskData['overall_score']} ({$riskData['risk_level']})");

        return response()->json([
            'success' => true,
            'data' => $riskData,
        ]);
    }

    public function addApplicationNote(Request $request, MCAApplication $application)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'type' => 'nullable|string|max:50',
        ]);

        $this->addNote($application, $validated['type'] ?? 'manual', $validated['content']);

        return redirect()->route('applications.show', $application)
            ->with('success', 'Note added successfully.');
    }

    protected function addNote(MCAApplication $application, string $type, string $content, array $metadata = [])
    {
        return $application->notes()->create([
            'user_id' => Auth::id(),
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    public function assignTo(Request $request, MCAApplication $application)
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $application->update(['assigned_to' => $validated['assigned_to']]);

        $this->addNote($application, 'assignment', 'Application assigned to user ID: '.$validated['assigned_to']);

        return redirect()->route('applications.show', $application)
            ->with('success', 'Application assigned successfully.');
    }

    public function destroy(MCAApplication $application)
    {
        $application->delete();

        return redirect()->route('applications.index')
            ->with('success', 'Application deleted successfully.');
    }

    /**
     * Send bank link request to merchant
     */
    public function sendBankLinkRequest(Request $request, MCAApplication $application)
    {
        // Check if Plaid is configured
        if (! $this->plaidService->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Plaid integration is not configured. Please configure it in Settings > Configuration.',
            ], 400);
        }

        // Check if there's already an active link request
        $activeRequest = $application->activeBankLinkRequest;
        if ($activeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'There is already an active bank link request for this application.',
                'link_request' => [
                    'id' => $activeRequest->id,
                    'status' => $activeRequest->status,
                    'expires_at' => $activeRequest->expires_at->format('M d, Y'),
                    'link_url' => $activeRequest->link_url,
                ],
            ], 400);
        }

        $validated = $request->validate([
            'email' => 'nullable|email',
            'expires_days' => 'nullable|integer|min:1|max:30',
        ]);

        $email = $validated['email'] ?? $application->owner_email;
        $expiresDays = $validated['expires_days'] ?? 7;

        // Create the bank link request
        $linkRequest = BankLinkRequest::create([
            'application_id' => $application->id,
            'merchant_email' => $email,
            'merchant_name' => $application->owner_full_name,
            'business_name' => $application->business_name,
            'status' => 'pending',
            'expires_at' => now()->addDays($expiresDays),
            'sent_by' => Auth::id(),
        ]);

        try {
            // Send email notification
            Notification::route('mail', $email)
                ->notify(new BankLinkRequestNotification($linkRequest));

            $linkRequest->markAsSent();

            $this->addNote(
                $application,
                'bank_request',
                "Bank link request sent to {$email}. Expires: ".$linkRequest->expires_at->format('M d, Y')
            );

            return response()->json([
                'success' => true,
                'message' => "Bank connection request sent to {$email}",
                'link_request' => [
                    'id' => $linkRequest->id,
                    'status' => $linkRequest->status,
                    'expires_at' => $linkRequest->expires_at->format('M d, Y'),
                    'link_url' => $linkRequest->link_url,
                ],
            ]);
        } catch (\Exception $e) {
            $linkRequest->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to send bank link request: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend bank link request
     */
    public function resendBankLinkRequest(Request $request, MCAApplication $application, BankLinkRequest $linkRequest)
    {
        if ($linkRequest->application_id !== $application->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid link request.',
            ], 400);
        }

        // Expire the old request and create a new one
        $linkRequest->markAsExpired();

        // Create new request
        $newRequest = BankLinkRequest::create([
            'application_id' => $application->id,
            'merchant_email' => $linkRequest->merchant_email,
            'merchant_name' => $linkRequest->merchant_name,
            'business_name' => $linkRequest->business_name,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'sent_by' => Auth::id(),
        ]);

        try {
            Notification::route('mail', $newRequest->merchant_email)
                ->notify(new BankLinkRequestNotification($newRequest));

            $newRequest->markAsSent();

            $this->addNote(
                $application,
                'bank_request',
                "Bank link request resent to {$newRequest->merchant_email}"
            );

            return response()->json([
                'success' => true,
                'message' => "Bank connection request resent to {$newRequest->merchant_email}",
                'link_request' => [
                    'id' => $newRequest->id,
                    'status' => $newRequest->status,
                    'expires_at' => $newRequest->expires_at->format('M d, Y'),
                    'link_url' => $newRequest->link_url,
                ],
            ]);
        } catch (\Exception $e) {
            $newRequest->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend bank link request: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload document for application
     */
    public function uploadDocument(Request $request, MCAApplication $application)
    {
        $validated = $request->validate([
            'document' => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv',
            'document_type' => 'required|string|in:bank_statement,tax_return,business_license,drivers_license,voided_check,lease_agreement,credit_card_statement,other',
            'statement_period' => 'required_if:document_type,bank_statement|nullable|date_format:Y-m',
        ]);

        try {
            $file = $request->file('document');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            // Generate unique filename
            $filename = 'app_'.$application->id.'_'.time().'_'.uniqid().'.'.$extension;

            // Store file
            $path = $file->storeAs(
                'applications/'.$application->id.'/documents',
                $filename,
                'local'
            );

            // Create document record
            $document = ApplicationDocument::create([
                'application_id' => $application->id,
                'document_type' => $validated['document_type'],
                'statement_period' => $validated['statement_period'] ?? null,
                'filename' => $filename,
                'original_filename' => $originalName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'storage_path' => $path,
                'is_processed' => false,
            ]);

            // Build note message
            $noteMessage = "Document uploaded: {$originalName} (".ucfirst(str_replace('_', ' ', $validated['document_type'])).')';
            if (! empty($validated['statement_period'])) {
                $noteMessage .= ' - '.\Carbon\Carbon::createFromFormat('Y-m', $validated['statement_period'])->format('F Y');
            }

            $this->addNote(
                $application,
                'document',
                $noteMessage
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully.',
                'document' => [
                    'id' => $document->id,
                    'original_filename' => $document->original_filename,
                    'document_type' => $document->document_type,
                    'file_size' => $this->formatFileSize($document->file_size),
                    'created_at' => $document->created_at->format('M d, Y H:i'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download document
     */
    public function downloadDocument(MCAApplication $application, ApplicationDocument $document)
    {
        if ($document->application_id !== $application->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($document->storage_path)) {
            abort(404, 'Document not found.');
        }

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->original_filename
        );
    }

    /**
     * View/preview document in browser
     */
    public function viewDocument(MCAApplication $application, ApplicationDocument $document)
    {
        if ($document->application_id !== $application->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($document->storage_path)) {
            abort(404, 'Document not found.');
        }

        $file = Storage::disk('local')->get($document->storage_path);
        $mimeType = $document->mime_type;

        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="'.$document->original_filename.'"');
    }

    /**
     * Delete document
     */
    public function deleteDocument(MCAApplication $application, ApplicationDocument $document)
    {
        if ($document->application_id !== $application->id) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found.',
            ], 404);
        }

        try {
            // Delete file from storage
            if (Storage::disk('local')->exists($document->storage_path)) {
                Storage::disk('local')->delete($document->storage_path);
            }

            $originalName = $document->original_filename;
            $document->delete();

            $this->addNote(
                $application,
                'document',
                "Document deleted: {$originalName}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all documents for application
     */
    public function getDocuments(MCAApplication $application)
    {
        $documents = $application->documents()->orderBy('created_at', 'desc')->get();

        // Get analyzed bank statements to build saved analysis summary
        $analyzedStatements = $documents->where('document_type', 'bank_statement')
            ->where('is_processed', true)
            ->where('analyzed_at', '!=', null);

        $savedAnalysis = null;
        if ($analyzedStatements->isNotEmpty()) {
            $savedAnalysis = [
                'success' => true,
                'message' => 'Loaded saved analysis',
                'results' => $analyzedStatements->map(function ($doc) {
                    return [
                        'document_id' => $doc->id,
                        'filename' => $doc->original_filename,
                        'statement_period' => $doc->statement_period,
                        'statement_period_label' => $doc->statement_period ? \Carbon\Carbon::createFromFormat('Y-m', $doc->statement_period)->format('F Y') : null,
                        'success' => true,
                        'transactions' => $doc->transaction_count ?? 0,
                        'total_credits' => (float) ($doc->total_credits ?? 0),
                        'total_debits' => (float) ($doc->total_debits ?? 0),
                        'true_revenue' => (float) ($doc->true_revenue ?? 0),
                        'analyzed_at' => $doc->analyzed_at ? $doc->analyzed_at->format('M d, Y H:i') : null,
                    ];
                })->values()->all(),
                'summary' => [
                    'statements_analyzed' => $analyzedStatements->count(),
                    'total_statements' => $documents->where('document_type', 'bank_statement')->count(),
                    'total_transactions' => $analyzedStatements->sum('transaction_count') ?? 0,
                    'total_credits' => round($analyzedStatements->sum('total_credits') ?? 0, 2),
                    'total_debits' => round($analyzedStatements->sum('total_debits') ?? 0, 2),
                    'total_true_revenue' => round($analyzedStatements->sum('true_revenue') ?? 0, 2),
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'documents' => $documents->map(function ($doc) use ($application) {
                $typeLabel = ucfirst(str_replace('_', ' ', $doc->document_type));
                if ($doc->statement_period) {
                    $typeLabel .= ' ('.\Carbon\Carbon::createFromFormat('Y-m', $doc->statement_period)->format('M Y').')';
                }

                // Check if file can be previewed in browser
                $viewableMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $canPreview = in_array($doc->mime_type, $viewableMimes);

                return [
                    'id' => $doc->id,
                    'original_filename' => $doc->original_filename,
                    'document_type' => $doc->document_type,
                    'document_type_label' => $typeLabel,
                    'statement_period' => $doc->statement_period,
                    'statement_period_label' => $doc->statement_period ? \Carbon\Carbon::createFromFormat('Y-m', $doc->statement_period)->format('F Y') : null,
                    'file_size' => $this->formatFileSize($doc->file_size),
                    'mime_type' => $doc->mime_type,
                    'can_preview' => $canPreview,
                    'is_analyzed' => $doc->is_processed,
                    'analyzed_at' => $doc->analyzed_at ? $doc->analyzed_at->format('M d, Y H:i') : null,
                    'true_revenue' => $doc->true_revenue ? (float) $doc->true_revenue : null,
                    'total_credits' => $doc->total_credits ? (float) $doc->total_credits : null,
                    'total_debits' => $doc->total_debits ? (float) $doc->total_debits : null,
                    'transaction_count' => $doc->transaction_count,
                    'created_at' => $doc->created_at->format('M d, Y H:i'),
                    'view_url' => route('applications.view-document', [$application, $doc]),
                    'download_url' => route('applications.download-document', [$application, $doc]),
                ];
            }),
            'saved_analysis' => $savedAnalysis,
        ]);
    }

    /**
     * Format file size for display
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * Get transactions for a specific document analysis
     */
    public function getDocumentTransactions(MCAApplication $application, ApplicationDocument $document)
    {
        if ($document->application_id !== $application->id) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found.',
            ], 404);
        }

        if (! $document->analysis_session_id) {
            return response()->json([
                'success' => false,
                'message' => 'No analysis found for this document.',
            ], 404);
        }

        $session = AnalysisSession::with('transactions')->find($document->analysis_session_id);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Analysis session not found.',
            ], 404);
        }

        $transactions = $session->transactions->map(function ($txn) {
            return [
                'id' => $txn->id,
                'date' => $txn->transaction_date->format('Y-m-d'),
                'date_formatted' => $txn->transaction_date->format('M d, Y'),
                'description' => $txn->description,
                'amount' => (float) $txn->amount,
                'type' => $txn->type,
                'original_type' => $txn->original_type,
                'category' => $txn->category,
                'confidence' => round($txn->confidence * 100),
                'confidence_label' => $txn->confidence_label,
                'was_corrected' => $txn->was_corrected,
                'exclude_from_revenue' => $txn->exclude_from_revenue ?? false,
                'exclusion_reason' => $txn->exclusion_reason,
                'merchant_name' => $txn->merchant_name,
            ];
        });

        // Calculate summary
        $credits = $transactions->where('type', 'credit');
        $debits = $transactions->where('type', 'debit');

        return response()->json([
            'success' => true,
            'document' => [
                'id' => $document->id,
                'filename' => $document->original_filename,
                'statement_period' => $document->statement_period,
                'statement_period_label' => $document->statement_period
                    ? \Carbon\Carbon::createFromFormat('Y-m', $document->statement_period)->format('F Y')
                    : null,
                'analyzed_at' => $document->analyzed_at?->format('M d, Y H:i'),
            ],
            'summary' => [
                'total_transactions' => $transactions->count(),
                'total_credits' => round($credits->sum('amount'), 2),
                'total_debits' => round($debits->sum('amount'), 2),
                'credit_count' => $credits->count(),
                'debit_count' => $debits->count(),
                'true_revenue' => (float) $document->true_revenue,
            ],
            'transactions' => $transactions->sortBy('date')->values()->all(),
        ]);
    }

    /**
     * Update a transaction (change type or exclude from revenue)
     */
    public function updateTransaction(Request $request, MCAApplication $application, ApplicationDocument $document, AnalyzedTransaction $transaction)
    {
        // Verify document belongs to application
        if ($document->application_id !== $application->id) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        // Verify transaction belongs to document's analysis session
        if ($transaction->analysis_session_id !== $document->analysis_session_id) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:credit,debit',
            'exclude_from_revenue' => 'sometimes|boolean',
            'exclusion_reason' => 'nullable|string|max:255',
        ]);

        // Track if type was changed
        $typeChanged = isset($validated['type']) && $validated['type'] !== $transaction->type;

        if ($typeChanged) {
            $validated['was_corrected'] = true;
        }

        $transaction->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transaction updated successfully.',
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'was_corrected' => $transaction->was_corrected,
                'exclude_from_revenue' => $transaction->exclude_from_revenue,
                'exclusion_reason' => $transaction->exclusion_reason,
            ],
        ]);
    }

    /**
     * Recalculate true revenue for a document based on current transaction states
     */
    public function recalculateTrueRevenue(MCAApplication $application, ApplicationDocument $document)
    {
        // Verify document belongs to application
        if ($document->application_id !== $application->id) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        if (!$document->analysis_session_id) {
            return response()->json(['success' => false, 'message' => 'No analysis found for this document.'], 404);
        }

        // Use AnalysisSummaryService to recalculate everything
        $result = $this->analysisSummaryService->recalculateSession($document->analysis_session_id);

        // Get application combined summary
        $applicationSummary = $this->analysisSummaryService->getApplicationSummary($application->id);
        $totalTrueRevenue = $applicationSummary['total_true_revenue'] ?? 0;

        // Get excluded transaction count
        $excludedCount = AnalyzedTransaction::where('analysis_session_id', $document->analysis_session_id)
            ->where('exclude_from_revenue', true)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'True revenue recalculated successfully.',
            'document' => [
                'id' => $document->id,
                'true_revenue' => $result['true_revenue'],
                'total_credits' => $result['total_credits'],
                'total_debits' => $result['total_debits'],
                'included_transactions' => $result['revenue_breakdown']['revenue_items'],
                'excluded_transactions' => $excludedCount,
            ],
            'application_total' => $totalTrueRevenue,
            'combined_summary' => $applicationSummary,
        ]);
    }

    /**
     * Get bank link request status
     */
    public function getBankLinkStatus(MCAApplication $application)
    {
        $linkRequests = $application->bankLinkRequests()
            ->orderBy('created_at', 'desc')
            ->get();

        $connectedBanks = $application->plaidItems()
            ->with('accounts')
            ->where('status', 'active')
            ->get();

        return response()->json([
            'success' => true,
            'link_requests' => $linkRequests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'email' => $request->merchant_email,
                    'status' => $request->status,
                    'status_badge' => $request->status_badge,
                    'sent_at' => $request->sent_at?->format('M d, Y H:i'),
                    'opened_at' => $request->opened_at?->format('M d, Y H:i'),
                    'completed_at' => $request->completed_at?->format('M d, Y H:i'),
                    'expires_at' => $request->expires_at->format('M d, Y'),
                    'is_expired' => $request->is_expired,
                    'institution_name' => $request->institution_name,
                    'link_url' => $request->is_valid ? $request->link_url : null,
                ];
            }),
            'connected_banks' => $connectedBanks->map(function ($item) {
                return [
                    'id' => $item->id,
                    'institution_name' => $item->institution_name,
                    'status' => $item->status,
                    'accounts_count' => $item->accounts->count(),
                    'accounts' => $item->accounts->map(function ($account) {
                        return [
                            'name' => $account->name,
                            'mask' => $account->mask,
                            'type' => $account->type,
                            'balance' => $account->current_balance,
                        ];
                    }),
                ];
            }),
        ]);
    }

    /**
     * Run bank statement analysis on uploaded documents
     */
    public function runBankAnalysis(MCAApplication $application)
    {
        // Use the bank analysis service
        $result = $this->bankAnalysisService->analyzeApplication($application);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Bank analysis failed.',
            ]);
        }

        // Auto-advance status after successful analysis
        $advanceResult = $this->flowService->autoAdvance($application);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'results' => $result['results'],
            'summary' => $result['summary'],
            'fcs_url' => $result['fcs_url'],
            'fcs_filename' => $result['fcs_filename'],
            'underwriting' => $result['underwriting'],
            'flow_advanced' => $advanceResult['advanced'],
            'current_status' => $application->fresh()->status,
        ]);
    }

    /**
     * Legacy bank analysis method - kept for backwards compatibility
     * @deprecated Use runBankAnalysis instead
     */
    protected function runBankAnalysisLegacy(MCAApplication $application)
    {
        // Get all bank statement documents that are PDFs
        $bankStatements = $application->documents()
            ->where('document_type', 'bank_statement')
            ->where('mime_type', 'application/pdf')
            ->get();

        if ($bankStatements->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No bank statement PDFs found for analysis.',
            ]);
        }

        $results = [];
        $allSessionIds = [];
        $totalTrueRevenue = 0;
        $totalCredits = 0;
        $totalDebits = 0;
        $totalTransactions = 0;

        foreach ($bankStatements as $document) {
            try {
                // Get the file path
                $filePath = Storage::disk('local')->path($document->storage_path);

                if (! file_exists($filePath)) {
                    $results[] = [
                        'document_id' => $document->id,
                        'filename' => $document->original_filename,
                        'success' => false,
                        'error' => 'File not found on server',
                    ];

                    continue;
                }

                // Extract text from PDF
                $scriptPath = storage_path('app/scripts/extract_pdf_text.py');
                $command = 'python3 '.escapeshellarg($scriptPath).' '.escapeshellarg($filePath).' false 2>&1';
                $output = shell_exec($command);
                $extractResult = json_decode($output, true);

                if (! $extractResult || ! $extractResult['success']) {
                    $results[] = [
                        'document_id' => $document->id,
                        'filename' => $document->original_filename,
                        'success' => false,
                        'error' => $extractResult['error'] ?? 'Failed to extract PDF text',
                    ];

                    continue;
                }

                $text = $extractResult['text'];
                $pages = $extractResult['pages'] ?? 0;

                \Log::info("Bank Analysis: Processing '{$document->original_filename}' - {$pages} pages");

                // Parse transactions with AI
                if ($pages > 1 && strlen($text) > 50000) {
                    $transactions = $this->parseTransactionsInChunks($text, $pages);
                } else {
                    $transactions = $this->parseTransactionsWithAI($text);
                }

                // Apply learned corrections
                $transactions = $this->applyLearnedCorrections($transactions);

                // Calculate summary
                $summary = $this->calculateTransactionSummary($transactions);

                // Save analysis session
                $session = null;
                if (! empty($transactions)) {
                    $sessionData = [
                        'file' => $document->original_filename,
                        'pages' => $pages,
                        'transactions' => $transactions,
                        'summary' => $summary,
                    ];
                    $session = $this->saveAnalysisSession($sessionData, $transactions, $application->id);
                    $allSessionIds[] = $session->session_id;
                }

                // Calculate true revenue for this statement
                $trueRevenueData = $this->calculateDocumentTrueRevenue($transactions);

                // Update document with analysis results
                $document->update([
                    'is_processed' => true,
                    'analysis_session_id' => $session?->id,
                    'true_revenue' => $trueRevenueData['true_revenue'],
                    'total_credits' => $summary['total_credits'],
                    'total_debits' => $summary['total_debits'],
                    'transaction_count' => count($transactions),
                    'analyzed_at' => now(),
                    'extracted_data' => [
                        'summary' => $summary,
                        'true_revenue_data' => $trueRevenueData,
                        'session_id' => $session?->session_id,
                    ],
                ]);

                $totalTrueRevenue += $trueRevenueData['true_revenue'];
                $totalCredits += $summary['total_credits'];
                $totalDebits += $summary['total_debits'];
                $totalTransactions += count($transactions);

                $results[] = [
                    'document_id' => $document->id,
                    'filename' => $document->original_filename,
                    'statement_period' => $document->statement_period,
                    'success' => true,
                    'transactions' => count($transactions),
                    'total_credits' => $summary['total_credits'],
                    'total_debits' => $summary['total_debits'],
                    'true_revenue' => $trueRevenueData['true_revenue'],
                    'session_id' => $session?->session_id,
                ];

                \Log::info("Bank Analysis: Completed '{$document->original_filename}' - ".count($transactions).' transactions, True Revenue: $'.number_format($trueRevenueData['true_revenue'], 2));

            } catch (\Exception $e) {
                \Log::error("Bank Analysis Error for {$document->original_filename}: ".$e->getMessage());
                $results[] = [
                    'document_id' => $document->id,
                    'filename' => $document->original_filename,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Add note to application
        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $this->addNote(
            $application,
            'analysis',
            "Bank statement analysis completed: {$successCount} of ".count($bankStatements).' statements analyzed. True Revenue: $'.number_format($totalTrueRevenue, 2)
        );

        // Calculate underwriting decision score BEFORE generating FCS
        $underwritingResult = null;
        if ($successCount > 0) {
            try {
                $underwritingResult = $this->underwritingScoreService->calculateAndSave($application);
                if ($underwritingResult['success']) {
                    $this->addNote(
                        $application,
                        'underwriting',
                        "Underwriting score calculated: {$underwritingResult['score']}/100 - {$underwritingResult['decision']}"
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Underwriting score calculation failed: '.$e->getMessage());
            }
        }

        // Generate FCS Report if we have successful analyses (includes underwriting score)
        $fcsUrl = null;
        $fcsFilename = null;
        if (! empty($allSessionIds)) {
            // Refresh application to get updated underwriting data
            $application->refresh();
            $fcsData = $this->generateApplicationFCS($application, $results, $totalTrueRevenue, $totalCredits, $totalDebits);
            $fcsUrl = $fcsData['url'] ?? null;
            $fcsFilename = $fcsData['filename'] ?? null;
        }

        return response()->json([
            'success' => true,
            'message' => "Analyzed {$successCount} bank statements successfully.",
            'results' => $results,
            'summary' => [
                'statements_analyzed' => $successCount,
                'total_statements' => count($bankStatements),
                'total_transactions' => $totalTransactions,
                'total_credits' => round($totalCredits, 2),
                'total_debits' => round($totalDebits, 2),
                'total_true_revenue' => round($totalTrueRevenue, 2),
            ],
            'fcs_url' => $fcsUrl,
            'fcs_filename' => $fcsFilename,
            'underwriting' => $underwritingResult ? [
                'score' => $underwritingResult['score'],
                'decision' => $underwritingResult['decision'],
                'flags_count' => count($underwritingResult['flags'] ?? []),
            ] : null,
        ]);
    }

    /**
     * Parse transactions in chunks for long statements
     */
    private function parseTransactionsInChunks(string $text, int $pages): array
    {
        $pageTexts = preg_split('/\n*=== PAGE BREAK ===\n*/i', $text);
        $allTransactions = [];
        $seenTransactions = [];

        foreach ($pageTexts as $index => $pageText) {
            $pageText = trim($pageText);
            if (empty($pageText) || strlen($pageText) < 50) {
                continue;
            }

            $contextText = 'PAGE '.($index + 1)." OF {$pages}\n\n".$pageText;
            $transactions = $this->parseTransactionsWithAI($contextText);

            foreach ($transactions as $txn) {
                $key = $txn['date'].'|'.strtolower(substr($txn['description'], 0, 30)).'|'.number_format($txn['amount'], 2);
                if (! isset($seenTransactions[$key])) {
                    $seenTransactions[$key] = true;
                    $allTransactions[] = $txn;
                }
            }
        }

        usort($allTransactions, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $allTransactions;
    }

    /**
     * Parse transactions using Claude AI (Anthropic)
     */
    private function parseTransactionsWithAI(string $text): array
    {
        $apiKey = env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            \Log::error('Bank Analysis: Anthropic API key not configured');

            return [];
        }

        $bankPatternService = new BankPatternService;
        $bankContext = $bankPatternService->buildAIContext($text);

        $tempFile = storage_path('app/uploads/temp_'.Str::random(10).'.txt');
        file_put_contents($tempFile, $text);

        $learnedPatterns = $this->getLearnedPatternsForAI();
        $combinedPatterns = [
            'learned_patterns' => $learnedPatterns,
            'bank_context' => $bankContext,
        ];
        $patternsFile = storage_path('app/uploads/patterns_'.Str::random(10).'.json');
        file_put_contents($patternsFile, json_encode($combinedPatterns));

        try {
            $scriptPath = storage_path('app/scripts/parse_transactions_ai.py');
            $outputFile = storage_path('app/uploads/output_'.Str::random(10).'.json');

            $command = 'timeout 320 python3 '.escapeshellarg($scriptPath).' '.escapeshellarg($tempFile).' '.escapeshellarg($apiKey);
            $command .= ' '.escapeshellarg($patternsFile);
            $command .= ' '.escapeshellarg($outputFile);
            $command .= ' 2>&1';

            shell_exec($command);

            @unlink($tempFile);
            @unlink($patternsFile);

            $result = null;
            if (file_exists($outputFile)) {
                $result = json_decode(file_get_contents($outputFile), true);
                @unlink($outputFile);
            }

            if ($result && $result['success'] && isset($result['transactions'])) {
                $detectedBank = $bankContext['detected_bank'] ?? 'Unknown';
                foreach ($result['transactions'] as &$txn) {
                    $txn['detected_bank'] = $detectedBank;
                }

                return $result['transactions'];
            }

            return [];
        } catch (\Exception $e) {
            \Log::error('Bank Analysis Exception: '.$e->getMessage());
            @unlink($tempFile);
            @unlink($patternsFile);

            return [];
        }
    }

    /**
     * Get learned patterns for AI
     */
    private function getLearnedPatternsForAI(): array
    {
        $corrections = TransactionCorrection::getLearnedPatterns();
        $historical = AnalyzedTransaction::getLearnedPatterns();

        $allPatterns = array_merge($corrections, $historical);
        $unique = [];
        $seen = [];

        foreach ($allPatterns as $pattern) {
            $key = strtolower($pattern['description_pattern'] ?? $pattern['description_normalized'] ?? '');
            if (! empty($key) && ! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = [
                    'description_pattern' => $key,
                    'correct_type' => $pattern['correct_type'],
                ];
            }
        }

        return array_slice($unique, 0, 100);
    }

    /**
     * Apply learned corrections to transactions
     */
    private function applyLearnedCorrections(array $transactions): array
    {
        $corrections = TransactionCorrection::all()->keyBy(fn ($item) => strtolower($item->description_pattern));
        $historicalCorrections = AnalyzedTransaction::where('was_corrected', true)
            ->get()
            ->keyBy(fn ($item) => strtolower($item->description_normalized));

        if ($corrections->isEmpty() && $historicalCorrections->isEmpty()) {
            return $transactions;
        }

        foreach ($transactions as &$txn) {
            $normalizedDesc = TransactionCorrection::normalizePattern($txn['description']);
            $normalizedDescLower = strtolower($normalizedDesc);

            foreach ($corrections as $pattern => $correction) {
                if (stripos($normalizedDescLower, $pattern) !== false) {
                    if ($txn['type'] !== $correction->correct_type) {
                        $txn['type'] = $correction->correct_type;
                        $txn['corrected'] = true;
                        $txn['confidence'] = 0.99;
                    }
                    break;
                }
            }
        }

        return $transactions;
    }

    /**
     * Calculate transaction summary
     */
    private function calculateTransactionSummary(array $transactions): array
    {
        $totalCredits = 0;
        $totalDebits = 0;
        $creditCount = 0;
        $debitCount = 0;

        foreach ($transactions as $txn) {
            if ($txn['type'] === 'credit') {
                $totalCredits += $txn['amount'];
                $creditCount++;
            } else {
                $totalDebits += $txn['amount'];
                $debitCount++;
            }
        }

        return [
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'credit_count' => $creditCount,
            'debit_count' => $debitCount,
            'net_flow' => $totalCredits - $totalDebits,
            'transaction_count' => count($transactions),
        ];
    }

    /**
     * Save analysis session
     */
    private function saveAnalysisSession(array $data, array &$transactions, int $applicationId): AnalysisSession
    {
        $session = AnalysisSession::createFromAnalysis($data, auth()->id());
        $session->update(['application_id' => $applicationId]);
        $transactionIds = AnalyzedTransaction::createFromAnalysis($session->id, $transactions);

        foreach ($transactionIds as $index => $id) {
            if (isset($transactions[$index])) {
                $transactions[$index]['id'] = $id;
            }
        }

        return $session;
    }

    /**
     * Calculate true revenue for a document
     */
    private function calculateDocumentTrueRevenue(array $transactions): array
    {
        $excludePatterns = [
            'transfer from', 'xfer from', 'online transfer', 'wire transfer', 'ach transfer',
            'zelle from', 'venmo from', 'paypal transfer', 'internal transfer', 'move money',
            'loan', 'advance', 'mca', 'merchant cash', 'funding', 'capital', 'lending',
            'refund', 'reversal', 'return', 'rebate', 'chargeback', 'credit adjustment',
            'interest', 'dividend', 'bonus', 'reward', 'cashback',
            'nsf fee reversal', 'overdraft reversal', 'fee waiver',
        ];

        $totalCredits = 0;
        $trueRevenue = 0;
        $excludedAmount = 0;
        $excludedItems = [];

        foreach ($transactions as $txn) {
            if ($txn['type'] !== 'credit') {
                continue;
            }

            $totalCredits += $txn['amount'];
            $description = strtolower($txn['description']);
            $isExcluded = false;

            foreach ($excludePatterns as $pattern) {
                if (stripos($description, $pattern) !== false) {
                    $isExcluded = true;
                    break;
                }
            }

            if ($isExcluded) {
                $excludedAmount += $txn['amount'];
                $excludedItems[] = [
                    'description' => $txn['description'],
                    'amount' => $txn['amount'],
                ];
            } else {
                $trueRevenue += $txn['amount'];
            }
        }

        return [
            'true_revenue' => round($trueRevenue, 2),
            'total_credits' => round($totalCredits, 2),
            'excluded_amount' => round($excludedAmount, 2),
            'excluded_count' => count($excludedItems),
            'revenue_ratio' => $totalCredits > 0 ? round(($trueRevenue / $totalCredits) * 100, 1) : 0,
        ];
    }

    /**
     * Generate FCS report for application
     */
    private function generateApplicationFCS(MCAApplication $application, array $results, float $totalTrueRevenue, float $totalCredits, float $totalDebits): array
    {
        $reportId = sprintf('APP%d_%05d_%s', $application->id, rand(10000, 99999), time());
        $pdfFilename = "fcs_app_{$application->id}_{$reportId}.pdf";

        $statementsData = [];
        foreach ($results as $result) {
            if ($result['success']) {
                $statementsData[] = [
                    'filename' => $result['filename'],
                    'statement_period' => $result['statement_period'] ?? 'N/A',
                    'credits' => $result['total_credits'],
                    'debits' => $result['total_debits'],
                    'true_revenue' => $result['true_revenue'],
                    'transactions' => $result['transactions'],
                ];
            }
        }

        $pdfData = [
            'report_id' => $reportId,
            'application_id' => $application->id,
            'business_name' => $application->business_name,
            'generated_at' => now()->format('F j, Y g:i A'),
            'analyst_name' => auth()->user()->name ?? 'System User',
            'statement_count' => count($statementsData),
            'total_credits' => round($totalCredits, 2),
            'total_debits' => round($totalDebits, 2),
            'true_revenue' => round($totalTrueRevenue, 2),
            'revenue_ratio' => $totalCredits > 0 ? round(($totalTrueRevenue / $totalCredits) * 100, 1) : 0,
            'statements' => $statementsData,
            // Underwriting score data
            'underwriting_score' => $application->underwriting_score,
            'underwriting_decision' => $application->underwriting_decision,
            'underwriting_details' => $application->underwriting_details,
        ];

        try {
            $pdf = Pdf::loadView('pdf.application-fcs', $pdfData);
            $pdf->setPaper('letter', 'portrait');

            $pdfPath = 'fcs_reports/'.$pdfFilename;
            Storage::put($pdfPath, $pdf->output());

            // Update all documents with FCS path
            foreach ($results as $result) {
                if ($result['success']) {
                    ApplicationDocument::where('id', $result['document_id'])
                        ->update(['fcs_report_path' => $pdfPath]);
                }
            }

            \Log::info("Bank Analysis: Generated FCS PDF: {$pdfFilename}");

            return [
                'url' => route('applications.download-fcs', [$application, $pdfFilename]),
                'filename' => $pdfFilename,
            ];
        } catch (\Exception $e) {
            \Log::error('Bank Analysis: Failed to generate FCS PDF: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Download FCS report for application
     */
    public function downloadFCS(MCAApplication $application, string $filename)
    {
        $filename = basename($filename);

        if (! str_starts_with($filename, 'fcs_app_') || ! str_ends_with($filename, '.pdf')) {
            abort(404, 'Invalid file requested');
        }

        $path = 'fcs_reports/'.$filename;

        if (! Storage::exists($path)) {
            abort(404, 'File not found');
        }

        return Storage::download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Calculate/Recalculate underwriting score for application
     */
    public function calculateUnderwritingScore(MCAApplication $application)
    {
        try {
            $result = $this->underwritingScoreService->calculateAndSave($application);

            if ($result['success']) {
                $this->addNote(
                    $application,
                    'underwriting',
                    "Underwriting score recalculated: {$result['score']}/100 - {$result['decision']}"
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Underwriting score calculated successfully',
                    'score' => $result['score'],
                    'decision' => $result['decision'],
                    'component_scores' => $result['component_scores'],
                    'flags_count' => count($result['flags'] ?? []),
                    'summary' => $result['summary'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to calculate underwriting score',
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Underwriting score calculation failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to calculate underwriting score: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send FCS report via email
     */
    public function sendFCSReport(Request $request, MCAApplication $application)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'message' => 'nullable|string|max:1000',
        ]);

        // Find the latest FCS report
        $fcsDocument = $application->documents()
            ->where('document_type', 'bank_statement')
            ->whereNotNull('fcs_report_path')
            ->orderBy('analyzed_at', 'desc')
            ->first();

        if (! $fcsDocument || ! $fcsDocument->fcs_report_path) {
            return response()->json([
                'success' => false,
                'error' => 'No FCS report found. Please run bank statement analysis first.',
            ], 400);
        }

        $pdfPath = $fcsDocument->fcs_report_path;

        if (! Storage::exists($pdfPath)) {
            return response()->json([
                'success' => false,
                'error' => 'FCS report file not found. Please regenerate the report.',
            ], 404);
        }

        $pdfFilename = 'FCS_'.Str::slug($application->business_name).'_'.now()->format('Y-m-d').'.pdf';

        try {
            Mail::to($validated['email'])
                ->send(new FCSReportMail(
                    $application,
                    $pdfPath,
                    $pdfFilename,
                    $validated['message'] ?? null
                ));

            $this->addNote(
                $application,
                'email',
                "FCS report emailed to: {$validated['email']}"
            );

            return response()->json([
                'success' => true,
                'message' => 'FCS report sent successfully to '.$validated['email'],
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send FCS report: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to send email: '.$e->getMessage(),
            ], 500);
        }
    }
}
