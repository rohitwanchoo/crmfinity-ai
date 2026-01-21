<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class DataMerchService
{
    protected Client $client;

    protected string $apiKey;

    protected string $baseUrl;

    protected string $environment;

    protected array $stackingThresholds;

    protected bool $enabled = false;

    public function __construct()
    {
        $this->loadConfiguration();

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Load configuration from database with fallback to config files
     */
    protected function loadConfiguration(): void
    {
        $dbSetting = IntegrationSetting::where('integration', 'datamerch')->first();

        if ($dbSetting && $dbSetting->isConfigured()) {
            $this->enabled = $dbSetting->enabled;
            $this->apiKey = $dbSetting->getCredential('api_key', '');
            $this->environment = $dbSetting->environment ?? 'sandbox';

            $baseUrls = [
                'sandbox' => 'https://sandbox.datamerch.com/api/v1',
                'production' => 'https://api.datamerch.com/v1',
            ];
            $this->baseUrl = $baseUrls[$this->environment] ?? $baseUrls['sandbox'];

            $this->stackingThresholds = [
                'high_risk' => (int) $dbSetting->getSetting('high_risk_threshold', 3),
                'medium_risk' => (int) $dbSetting->getSetting('medium_risk_threshold', 2),
                'low_risk' => 1,
            ];
        } else {
            $this->enabled = ! empty(config('datamerch.api_key'));
            $this->apiKey = config('datamerch.api_key', '');
            $this->environment = config('datamerch.env', 'sandbox');
            $this->baseUrl = config("datamerch.base_urls.{$this->environment}");
            $this->stackingThresholds = config('datamerch.stacking_thresholds', [
                'high_risk' => 3,
                'medium_risk' => 2,
                'low_risk' => 1,
            ]);
        }
    }

    /**
     * Check if the integration is enabled and configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled && ! empty($this->apiKey);
    }

    /**
     * Search for merchant in DataMerch database
     */
    public function searchMerchant(array $merchant): array
    {
        $payload = [
            'business_name' => $merchant['business_name'],
            'ein' => $merchant['ein'] ?? null,
            'owner_name' => $merchant['owner_name'] ?? null,
            'owner_ssn' => isset($merchant['owner_ssn']) ? substr($merchant['owner_ssn'], -4) : null,
            'address' => $merchant['address'] ?? null,
            'city' => $merchant['city'] ?? null,
            'state' => $merchant['state'] ?? null,
            'zip' => $merchant['zip_code'] ?? null,
            'phone' => $merchant['phone'] ?? null,
        ];

        return $this->request('POST', '/merchants/search', $payload);
    }

    /**
     * Get detailed merchant record
     */
    public function getMerchantDetails(string $merchantId): array
    {
        return $this->request('GET', "/merchants/{$merchantId}");
    }

    /**
     * Report a new MCA to DataMerch
     */
    public function reportMCA(array $data): array
    {
        $payload = [
            'merchant' => [
                'business_name' => $data['business_name'],
                'ein' => $data['ein'] ?? null,
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'zip' => $data['zip_code'],
            ],
            'funding' => [
                'funded_amount' => $data['funded_amount'],
                'payback_amount' => $data['payback_amount'],
                'factor_rate' => $data['factor_rate'],
                'term_days' => $data['term_days'] ?? null,
                'daily_payment' => $data['daily_payment'] ?? null,
                'funding_date' => $data['funding_date'],
                'position' => $data['position'] ?? 1,
            ],
        ];

        return $this->request('POST', '/fundings', $payload);
    }

    /**
     * Update MCA status (paid off, default, etc.)
     */
    public function updateMCAStatus(string $fundingId, string $status): array
    {
        return $this->request('PATCH', "/fundings/{$fundingId}", [
            'status' => $status,
        ]);
    }

    /**
     * Analyze stacking risk
     */
    public function analyzeStackingRisk(array $searchResults): array
    {
        $activeMCAs = [];
        $defaultedMCAs = [];
        $paidOffMCAs = [];
        $totalExposure = 0;

        $records = $searchResults['records'] ?? [];

        foreach ($records as $record) {
            $status = $record['status'] ?? 'unknown';
            $amount = $record['funded_amount'] ?? 0;

            switch ($status) {
                case 'active':
                case 'current':
                    $activeMCAs[] = $record;
                    $totalExposure += $amount;
                    break;
                case 'default':
                case 'collections':
                    $defaultedMCAs[] = $record;
                    break;
                case 'paid_off':
                case 'completed':
                    $paidOffMCAs[] = $record;
                    break;
            }
        }

        $activeCount = count($activeMCAs);

        // Determine risk level
        if ($activeCount >= $this->stackingThresholds['high_risk']) {
            $riskLevel = 'high';
            $riskScore = 20;
        } elseif ($activeCount >= $this->stackingThresholds['medium_risk']) {
            $riskLevel = 'medium';
            $riskScore = 50;
        } elseif ($activeCount >= $this->stackingThresholds['low_risk']) {
            $riskLevel = 'low';
            $riskScore = 75;
        } else {
            $riskLevel = 'none';
            $riskScore = 100;
        }

        // Adjust for defaults
        if (count($defaultedMCAs) > 0) {
            $riskScore = max(0, $riskScore - (count($defaultedMCAs) * 20));
            $riskLevel = 'high';
        }

        $flags = [];
        if ($activeCount > 0) {
            $flags[] = "{$activeCount} active MCA position(s)";
        }
        if (count($defaultedMCAs) > 0) {
            $flags[] = count($defaultedMCAs).' defaulted MCA(s) on record';
        }
        if ($totalExposure > 100000) {
            $flags[] = 'High total MCA exposure: $'.number_format($totalExposure);
        }

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'active_mcas' => $activeCount,
            'active_mca_details' => $activeMCAs,
            'defaulted_mcas' => count($defaultedMCAs),
            'defaulted_mca_details' => $defaultedMCAs,
            'paid_off_mcas' => count($paidOffMCAs),
            'total_exposure' => $totalExposure,
            'flags' => $flags,
            'recommendation' => $this->getStackingRecommendation($activeCount, count($defaultedMCAs)),
        ];
    }

    /**
     * Get stacking recommendation
     */
    protected function getStackingRecommendation(int $active, int $defaulted): string
    {
        if ($defaulted > 0) {
            return 'DECLINE - Previous MCA default(s) on record';
        }

        if ($active >= 3) {
            return 'DECLINE - Too many active positions';
        }

        if ($active === 2) {
            return 'CAUTION - Consider as 3rd position only with strong metrics';
        }

        if ($active === 1) {
            return 'REVIEW - Verify current position payment performance';
        }

        return 'PROCEED - No stacking concerns';
    }

    /**
     * Make API request
     */
    protected function request(string $method, string $endpoint, array $payload = []): array
    {
        try {
            $options = [];
            if (! empty($payload)) {
                $options['json'] = $payload;
            }

            $response = $this->client->request($method, $endpoint, $options);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('DataMerch API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            // Return mock data for sandbox/development
            if ($this->environment === 'sandbox') {
                return $this->getMockResponse($endpoint);
            }

            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get mock response for sandbox testing
     */
    protected function getMockResponse(string $endpoint): array
    {
        if (str_contains($endpoint, '/search')) {
            return [
                'success' => true,
                'records' => [],
                'total_count' => 0,
            ];
        }

        return ['success' => true];
    }
}
