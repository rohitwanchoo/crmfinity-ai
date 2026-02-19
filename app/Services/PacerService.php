<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PacerService
{
    protected Client $client;

    protected ?string $username = null;

    protected ?string $password = null;

    protected ?string $clientCode = null;

    protected string $baseUrl;

    protected bool $enabled = false;

    protected ?string $authToken = null;

    protected array $settings = [];

    const BASE_URL = 'https://pcl.uscourts.gov/pcl-public-api/rest';

    const LOGIN_URL = 'https://pacer.uscourts.gov/pscof/login.jsf';

    public function __construct()
    {
        $this->loadConfiguration();

        $this->client = new Client([
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Load configuration from database with fallback to config files
     */
    protected function loadConfiguration(): void
    {
        $dbSetting = IntegrationSetting::where('integration', 'pacer')->first();

        if ($dbSetting && $dbSetting->isConfigured()) {
            $this->enabled = $dbSetting->enabled;
            $this->username = (string) ($dbSetting->getCredential('username') ?? '');
            $this->password = (string) ($dbSetting->getCredential('password') ?? '');
            $this->clientCode = (string) ($dbSetting->getCredential('client_code') ?? '');
            $this->settings = $dbSetting->settings ?? [];
            $this->baseUrl = self::BASE_URL;
        } else {
            $this->enabled = ! empty(config('pacer.username'));
            $this->username = (string) (config('pacer.username') ?? '');
            $this->password = (string) (config('pacer.password') ?? '');
            $this->clientCode = (string) (config('pacer.client_code') ?? '');
            $this->settings = [
                'default_court' => config('pacer.default_court', ''),
                'search_type' => config('pacer.search_type', 'all'),
                'max_results' => config('pacer.max_results', 100),
            ];
            $this->baseUrl = config('pacer.base_url', self::BASE_URL);
        }
    }

    /**
     * Check if the integration is enabled and configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled && ! empty($this->username) && ! empty($this->password);
    }

    /**
     * Authenticate with PACER and get token
     */
    public function authenticate(): bool
    {
        try {
            $response = $this->client->post(self::BASE_URL.'/loginRequest', [
                'json' => [
                    'loginId' => $this->username,
                    'password' => $this->password,
                    'clientCode' => $this->clientCode,
                    'redactFlag' => '1',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['loginResult']) && $data['loginResult'] === 'success') {
                $this->authToken = $data['nextGenCSO'] ?? null;

                return true;
            }

            Log::warning('PACER authentication failed', ['response' => $data]);

            return false;
        } catch (GuzzleException $e) {
            Log::error('PACER authentication error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Search cases by party name
     */
    public function searchByPartyName(string $partyName, array $options = []): array
    {
        $payload = [
            'lastName' => $partyName,
            'courtTypeId' => $this->mapSearchType($options['search_type'] ?? $this->settings['search_type'] ?? 'all'),
            'pageNumber' => $options['page'] ?? 1,
            'numberOfResults' => $options['limit'] ?? $this->settings['max_results'] ?? 100,
        ];

        if (! empty($options['court_id'])) {
            $payload['courtId'] = $options['court_id'];
        }

        if (! empty($options['first_name'])) {
            $payload['firstName'] = $options['first_name'];
        }

        if (! empty($options['date_from'])) {
            $payload['dateFiledFrom'] = $options['date_from'];
        }

        if (! empty($options['date_to'])) {
            $payload['dateFiledTo'] = $options['date_to'];
        }

        return $this->request('POST', '/parties', $payload);
    }

    /**
     * Search cases by case number
     */
    public function searchByCaseNumber(string $caseNumber, ?string $courtId = null): array
    {
        $payload = [
            'caseNumber' => $caseNumber,
        ];

        if ($courtId) {
            $payload['courtId'] = $courtId;
        }

        return $this->request('POST', '/cases', $payload);
    }

    /**
     * Search bankruptcy cases
     */
    public function searchBankruptcy(string $debtorName, array $options = []): array
    {
        $options['search_type'] = 'bankruptcy';

        return $this->searchByPartyName($debtorName, $options);
    }

    /**
     * Search civil cases
     */
    public function searchCivil(string $partyName, array $options = []): array
    {
        $options['search_type'] = 'civil';

        return $this->searchByPartyName($partyName, $options);
    }

    /**
     * Get case details
     */
    public function getCaseDetails(string $caseId, string $courtId): array
    {
        return $this->request('GET', "/cases/{$courtId}/{$caseId}");
    }

    /**
     * Get docket entries for a case
     */
    public function getDocketEntries(string $caseId, string $courtId, array $options = []): array
    {
        $payload = [
            'caseId' => $caseId,
            'courtId' => $courtId,
            'pageNumber' => $options['page'] ?? 1,
        ];

        return $this->request('POST', '/docketEntries', $payload);
    }

    /**
     * Analyze court records for MCA risk assessment
     */
    public function analyzeCourtRecords(array $searchResults): array
    {
        $cases = $searchResults['content'] ?? $searchResults['cases'] ?? [];
        $bankruptcyCases = [];
        $civilLitigation = [];
        $judgments = [];
        $flags = [];

        foreach ($cases as $case) {
            $caseType = strtolower($case['caseType'] ?? $case['case_type'] ?? '');
            $caseStatus = strtolower($case['caseStatus'] ?? $case['status'] ?? '');

            // Categorize cases
            if (str_contains($caseType, 'bk') || str_contains($caseType, 'bankruptcy')) {
                $bankruptcyCases[] = $case;

                // Check for recent or active bankruptcies
                if (in_array($caseStatus, ['open', 'active', 'pending'])) {
                    $flags[] = 'Active bankruptcy case: '.($case['caseNumber'] ?? 'Unknown');
                }
            }

            if (str_contains($caseType, 'cv') || str_contains($caseType, 'civil')) {
                $civilLitigation[] = $case;
            }

            // Look for judgment indicators
            if (str_contains(strtolower($case['caseTitle'] ?? ''), 'judgment') ||
                str_contains($caseType, 'judgment')) {
                $judgments[] = $case;
            }
        }

        $riskScore = $this->calculateRiskScore($bankruptcyCases, $civilLitigation, $judgments);

        if (count($bankruptcyCases) > 0) {
            $flags[] = count($bankruptcyCases).' bankruptcy case(s) found';
        }
        if (count($civilLitigation) > 3) {
            $flags[] = 'High civil litigation activity ('.count($civilLitigation).' cases)';
        }
        if (count($judgments) > 0) {
            $flags[] = count($judgments).' judgment(s) found';
        }

        return [
            'risk_score' => $riskScore,
            'risk_level' => $this->getRiskLevel($riskScore),
            'total_cases' => count($cases),
            'bankruptcy_cases' => count($bankruptcyCases),
            'bankruptcy_details' => $bankruptcyCases,
            'civil_litigation' => count($civilLitigation),
            'civil_details' => $civilLitigation,
            'judgments' => count($judgments),
            'judgment_details' => $judgments,
            'flags' => $flags,
            'recommendation' => $this->getRecommendation($bankruptcyCases, $civilLitigation, $judgments),
        ];
    }

    /**
     * Calculate risk score based on court records
     */
    protected function calculateRiskScore(array $bankruptcyCases, array $civilLitigation, array $judgments): int
    {
        $score = 100;

        // Active bankruptcy is high risk
        foreach ($bankruptcyCases as $case) {
            $status = strtolower($case['caseStatus'] ?? $case['status'] ?? '');
            if (in_array($status, ['open', 'active', 'pending'])) {
                $score -= 40;
            } else {
                $score -= 10;
            }
        }

        // Civil litigation reduces score
        $score -= min(count($civilLitigation) * 5, 25);

        // Judgments are significant red flags
        $score -= min(count($judgments) * 15, 30);

        return max(0, $score);
    }

    /**
     * Get risk level from score
     */
    protected function getRiskLevel(int $score): string
    {
        if ($score >= 75) {
            return 'low';
        }
        if ($score >= 50) {
            return 'medium';
        }
        if ($score >= 25) {
            return 'high';
        }

        return 'critical';
    }

    /**
     * Get recommendation based on court record analysis
     */
    protected function getRecommendation(array $bankruptcyCases, array $civilLitigation, array $judgments): string
    {
        // Check for active bankruptcy
        foreach ($bankruptcyCases as $case) {
            $status = strtolower($case['caseStatus'] ?? $case['status'] ?? '');
            if (in_array($status, ['open', 'active', 'pending'])) {
                return 'DECLINE - Active bankruptcy proceeding detected';
            }
        }

        if (count($judgments) >= 2) {
            return 'HIGH RISK - Multiple judgments on record require careful review';
        }

        if (count($bankruptcyCases) >= 2) {
            return 'CAUTION - Multiple bankruptcy filings in history';
        }

        if (count($civilLitigation) > 5) {
            return 'REVIEW - High litigation activity warrants further investigation';
        }

        if (count($bankruptcyCases) > 0 || count($judgments) > 0) {
            return 'REVIEW - Court records require manual verification';
        }

        return 'LOW RISK - No significant court record concerns';
    }

    /**
     * Map search type to PACER court type ID
     */
    protected function mapSearchType(string $type): string
    {
        $map = [
            'all' => '',
            'bankruptcy' => 'bk',
            'civil' => 'cv',
            'criminal' => 'cr',
            'appellate' => 'ap',
        ];

        return $map[$type] ?? '';
    }

    /**
     * Make API request
     */
    protected function request(string $method, string $endpoint, array $payload = []): array
    {
        try {
            // Ensure authenticated
            if (! $this->authToken && ! $this->authenticate()) {
                return [
                    'success' => false,
                    'error' => true,
                    'message' => 'Authentication failed',
                    'content' => [],
                ];
            }

            $options = [
                'headers' => [
                    'X-NEXT-GEN-CSO' => $this->authToken,
                ],
            ];

            if (! empty($payload)) {
                $options['json'] = $payload;
            }

            $response = $this->client->request($method, $this->baseUrl.$endpoint, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'content' => $data['content'] ?? $data,
                'page_info' => $data['pageInfo'] ?? null,
            ];
        } catch (GuzzleException $e) {
            Log::error('PACER API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => true,
                'message' => $e->getMessage(),
                'content' => [],
            ];
        }
    }

    /**
     * Test connection to PACER
     */
    public function testConnection(): array
    {
        try {
            if ($this->authenticate()) {
                return [
                    'success' => true,
                    'message' => 'Successfully authenticated with PACER',
                ];
            }

            return [
                'success' => false,
                'message' => 'Authentication failed - check username and password',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }
}
