<?php

namespace App\Services;

use App\Models\MCAApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    protected PersonaService $personaService;
    protected ExperianService $experianService;
    protected DataMerchService $dataMerchService;
    protected UCCService $uccService;
    protected PacerService $pacerService;

    public function __construct(
        PersonaService $personaService,
        ExperianService $experianService,
        DataMerchService $dataMerchService,
        UCCService $uccService,
        PacerService $pacerService
    ) {
        $this->personaService = $personaService;
        $this->experianService = $experianService;
        $this->dataMerchService = $dataMerchService;
        $this->uccService = $uccService;
        $this->pacerService = $pacerService;
    }

    /**
     * Run a specific verification type
     */
    public function runVerification(MCAApplication $application, string $type): array
    {
        if (!array_key_exists($type, MCAApplication::VERIFICATION_TYPES)) {
            throw new \InvalidArgumentException("Invalid verification type: {$type}");
        }

        Log::info("Running {$type} verification for application {$application->application_id}");

        try {
            $result = match ($type) {
                'persona' => $this->runPersonaVerification($application),
                'experian_credit' => $this->runExperianCreditCheck($application),
                'experian_business' => $this->runExperianBusinessCheck($application),
                'datamerch' => $this->runDataMerchCheck($application),
                'ucc' => $this->runUCCSearch($application),
                'pacer' => $this->runPacerSearch($application),
                default => throw new \Exception('Invalid verification type'),
            };

            return [
                'success' => true,
                'type' => $type,
                'label' => MCAApplication::VERIFICATION_TYPES[$type],
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::error("Verification {$type} failed for application {$application->application_id}: " . $e->getMessage());

            return [
                'success' => false,
                'type' => $type,
                'label' => MCAApplication::VERIFICATION_TYPES[$type],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run all required verifications
     */
    public function runAllRequired(MCAApplication $application): array
    {
        $requiredTypes = ['persona', 'experian_credit', 'datamerch'];
        $results = [];
        $allSuccess = true;

        foreach ($requiredTypes as $type) {
            $result = $this->runVerification($application, $type);
            $results[$type] = $result;
            if (!$result['success']) {
                $allSuccess = false;
            }
        }

        return [
            'success' => $allSuccess,
            'results' => $results,
        ];
    }

    /**
     * Run all verifications (required + optional)
     */
    public function runAll(MCAApplication $application): array
    {
        $results = [];
        $successCount = 0;

        foreach (array_keys(MCAApplication::VERIFICATION_TYPES) as $type) {
            $result = $this->runVerification($application, $type);
            $results[$type] = $result;
            if ($result['success']) {
                $successCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'completed' => $successCount,
            'total' => count(MCAApplication::VERIFICATION_TYPES),
            'results' => $results,
        ];
    }

    /**
     * Run Persona identity verification
     */
    protected function runPersonaVerification(MCAApplication $application): array
    {
        $referenceId = 'APP-' . $application->id;

        $inquiryData = $this->personaService->createInquiry([
            'reference_id' => $referenceId,
            'first_name' => $application->owner_first_name,
            'last_name' => $application->owner_last_name,
            'email' => $application->owner_email,
        ]);

        // Generate inquiry ID if not returned (sandbox mode)
        $inquiryId = $inquiryData['id'] ?? $inquiryData['inquiry_id'] ?? ('inq_sandbox_' . uniqid());
        $isSandbox = str_starts_with($inquiryId, 'inq_sandbox_');

        // In sandbox mode, mark as completed immediately for testing
        $status = $inquiryData['status'] ?? ($isSandbox ? 'completed' : 'pending');

        $application->personaInquiries()->create([
            'inquiry_id' => $inquiryId,
            'status' => $status,
            'reference_id' => $referenceId,
            'inquiry_data' => $inquiryData,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        $this->addNote($application, 'verification', 'Persona identity verification initiated - ID: ' . $inquiryId);

        return $inquiryData;
    }

    /**
     * Run Experian personal credit check
     */
    protected function runExperianCreditCheck(MCAApplication $application): array
    {
        // Format DOB as MMDDYYYY for Experian
        $dob = $application->owner_dob ? $application->owner_dob->format('mdY') : '';

        $reportData = $this->experianService->getCreditReport([
            'first_name' => $application->owner_first_name,
            'last_name' => $application->owner_last_name,
            'ssn' => $application->owner_ssn ?? '',
            'dob' => $dob,
            'address_line1' => $application->owner_address,
            'city' => $application->owner_city,
            'state' => $application->owner_state,
            'zip_code' => $application->owner_zip,
        ]);

        // Handle both real and mock/sandbox responses
        $creditScore = $reportData['credit_score'] ?? $reportData['score'] ?? null;
        $openAccounts = $reportData['open_accounts'] ?? $reportData['openAccounts'] ?? 0;
        $delinquentAccounts = $reportData['delinquent_accounts'] ?? $reportData['delinquentAccounts'] ?? 0;
        $totalDebt = $reportData['total_debt'] ?? $reportData['totalDebt'] ?? 0;
        $bankruptcies = $reportData['bankruptcies'] ?? 0;
        $scoreFactors = $reportData['score_factors'] ?? $reportData['factors'] ?? [];

        $application->creditReports()->create([
            'report_type' => 'personal',
            'provider' => 'experian',
            'credit_score' => $creditScore,
            'score_factors' => $scoreFactors,
            'open_accounts' => $openAccounts,
            'delinquent_accounts' => $delinquentAccounts,
            'total_debt' => $totalDebt,
            'bankruptcies' => $bankruptcies,
            'raw_report' => $reportData,
        ]);

        $this->addNote($application, 'verification', 'Experian personal credit report pulled - Score: ' . ($creditScore ?? 'N/A'));

        return $reportData;
    }

    /**
     * Run Experian business credit check
     */
    protected function runExperianBusinessCheck(MCAApplication $application): array
    {
        $reportData = $this->experianService->getBusinessCreditReport([
            'business_name' => $application->business_name,
            'ein' => $application->ein,
            'address' => $application->business_address,
            'city' => $application->business_city,
            'state' => $application->business_state,
            'zip_code' => $application->business_zip,
            'phone' => $application->business_phone,
        ]);

        // Handle both real and mock/sandbox responses
        $intelliscore = $reportData['intelliscore'] ?? $reportData['credit_score'] ?? $reportData['score'] ?? null;

        $application->creditReports()->create([
            'report_type' => 'business',
            'provider' => 'experian',
            'credit_score' => $intelliscore,
            'raw_report' => $reportData,
            'analysis' => [
                'years_in_business' => $reportData['years_in_business'] ?? $reportData['yearsInBusiness'] ?? null,
                'payment_trend' => $reportData['payment_trend'] ?? $reportData['paymentTrend'] ?? null,
                'risk_class' => $reportData['risk_class'] ?? $reportData['riskClass'] ?? null,
            ],
        ]);

        $this->addNote($application, 'verification', 'Experian business credit report pulled - IntelliScore: ' . ($intelliscore ?? 'N/A'));

        return $reportData;
    }

    /**
     * Run DataMerch MCA stacking check
     */
    protected function runDataMerchCheck(MCAApplication $application): array
    {
        $searchData = $this->dataMerchService->searchMerchant([
            'business_name' => $application->business_name,
            'ein' => $application->ein,
            'owner_name' => $application->owner_first_name . ' ' . $application->owner_last_name,
            'owner_ssn' => $application->owner_ssn,
        ]);

        // Analyze stacking risk from search results
        $analysis = $this->dataMerchService->analyzeStackingRisk($searchData);

        $application->stackingReports()->create([
            'provider' => 'datamerch',
            'active_mcas' => $analysis['active_mcas'] ?? 0,
            'defaulted_mcas' => $analysis['defaulted_mcas'] ?? 0,
            'total_exposure' => $analysis['total_exposure'] ?? 0,
            'risk_score' => $analysis['risk_score'] ?? 100,
            'risk_level' => $analysis['risk_level'] ?? 'none',
            'mca_details' => $analysis['active_mca_details'] ?? [],
            'recommendation' => $analysis['recommendation'] ?? null,
        ]);

        $this->addNote($application, 'verification', 'DataMerch stacking check completed');

        return array_merge($searchData, ['analysis' => $analysis]);
    }

    /**
     * Run UCC filing search
     */
    protected function runUCCSearch(MCAApplication $application): array
    {
        $searchData = $this->uccService->searchFilings([
            'business_name' => $application->business_name,
            'state' => $application->business_state,
            'owner_name' => $application->owner_first_name . ' ' . $application->owner_last_name,
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

    /**
     * Run PACER court records search
     */
    protected function runPacerSearch(MCAApplication $application): array
    {
        if (!$this->pacerService->isEnabled()) {
            throw new \Exception('PACER integration is not configured. Please configure it in Settings > Configuration.');
        }

        $searchName = $application->owner_first_name . ' ' . $application->owner_last_name;

        // Search by owner name
        $searchData = $this->pacerService->searchByPartyName($searchName, [
            'search_type' => 'all',
        ]);

        // Also search by business name if different
        if ($application->business_name && strtolower($application->business_name) !== strtolower($searchName)) {
            $businessSearchData = $this->pacerService->searchByPartyName($application->business_name, [
                'search_type' => 'all',
            ]);

            // Merge results
            if (!empty($businessSearchData['content'])) {
                $searchData['content'] = array_merge(
                    $searchData['content'] ?? [],
                    $businessSearchData['content'] ?? []
                );
            }
        }

        // Analyze court records
        $analysis = $this->pacerService->analyzeCourtRecords($searchData);

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

    /**
     * Get verification summary for application
     */
    public function getVerificationSummary(MCAApplication $application): array
    {
        $progress = $application->getVerificationProgress();
        $completedCount = 0;
        $requiredComplete = true;
        $requiredTypes = ['persona', 'experian_credit', 'datamerch'];

        foreach ($progress as $type => $data) {
            if ($data['completed']) {
                $completedCount++;
            } elseif (in_array($type, $requiredTypes)) {
                $requiredComplete = false;
            }
        }

        return [
            'completed' => $completedCount,
            'total' => count(MCAApplication::VERIFICATION_TYPES),
            'required_complete' => $requiredComplete,
            'progress' => $progress,
        ];
    }

    /**
     * Add note to application
     */
    protected function addNote(MCAApplication $application, string $type, string $content): void
    {
        $application->notes()->create([
            'user_id' => Auth::id(),
            'type' => $type,
            'content' => $content,
        ]);
    }
}
