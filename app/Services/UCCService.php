<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class UCCService
{
    protected Client $client;

    protected string $apiKey;

    protected string $apiUrl;

    protected bool $enabled = false;

    public function __construct()
    {
        $this->loadConfiguration();

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
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
        $dbSetting = IntegrationSetting::where('integration', 'ucc')->first();

        if ($dbSetting && $dbSetting->isConfigured()) {
            $this->enabled = $dbSetting->enabled;
            $this->apiKey = $dbSetting->getCredential('api_key', '');
            $this->apiUrl = $dbSetting->getCredential('api_url', 'https://api.uccfilings.com');
        } else {
            $this->enabled = ! empty(config('ucc.api_key'));
            $this->apiKey = config('ucc.api_key', '');
            $this->apiUrl = config('ucc.api_url', 'https://api.uccfilings.com');
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
     * Search UCC filings by debtor name
     */
    public function searchByDebtorName(string $businessName, ?string $state = null): array
    {
        $payload = [
            'search_type' => 'debtor_name',
            'debtor_name' => $businessName,
            'state' => $state,
            'include_amendments' => true,
        ];

        return $this->request('POST', '/search', $payload);
    }

    /**
     * Search UCC filings by EIN/Tax ID
     */
    public function searchByEIN(string $ein): array
    {
        $payload = [
            'search_type' => 'tax_id',
            'tax_id' => preg_replace('/[^0-9]/', '', $ein),
        ];

        return $this->request('POST', '/search', $payload);
    }

    /**
     * Search UCC filings by secured party
     */
    public function searchBySecuredParty(string $partyName, ?string $state = null): array
    {
        $payload = [
            'search_type' => 'secured_party',
            'secured_party_name' => $partyName,
            'state' => $state,
        ];

        return $this->request('POST', '/search', $payload);
    }

    /**
     * Get filing details
     */
    public function getFilingDetails(string $filingNumber, string $state): array
    {
        return $this->request('GET', "/filings/{$state}/{$filingNumber}");
    }

    /**
     * Analyze UCC filings for MCA risk
     */
    public function analyzeFilings(array $searchResults): array
    {
        $filings = $searchResults['filings'] ?? [];
        $activeFilings = [];
        $mcaRelatedFilings = [];
        $blanketLiens = [];
        $totalSecuredAmount = 0;

        // MCA-related secured party keywords
        $mcaKeywords = [
            'capital', 'funding', 'finance', 'merchant', 'cash advance',
            'business funding', 'working capital', 'funder', 'factor',
        ];

        foreach ($filings as $filing) {
            $status = $filing['status'] ?? 'unknown';
            $securedParty = strtolower($filing['secured_party'] ?? '');
            $collateral = strtolower($filing['collateral_description'] ?? '');

            // Check if filing is active
            if (in_array($status, ['active', 'open', 'current'])) {
                $activeFilings[] = $filing;

                // Check if it's MCA-related
                foreach ($mcaKeywords as $keyword) {
                    if (str_contains($securedParty, $keyword)) {
                        $mcaRelatedFilings[] = $filing;
                        break;
                    }
                }

                // Check for blanket lien
                if (str_contains($collateral, 'all assets') ||
                    str_contains($collateral, 'all inventory') ||
                    str_contains($collateral, 'accounts receivable')) {
                    $blanketLiens[] = $filing;
                }

                $totalSecuredAmount += $filing['secured_amount'] ?? 0;
            }
        }

        $riskScore = $this->calculateUCCRiskScore(
            count($activeFilings),
            count($mcaRelatedFilings),
            count($blanketLiens)
        );

        $flags = [];
        if (count($activeFilings) > 5) {
            $flags[] = "High number of UCC filings ({count($activeFilings)})";
        }
        if (count($mcaRelatedFilings) > 0) {
            $flags[] = count($mcaRelatedFilings).' MCA-related UCC filing(s)';
        }
        if (count($blanketLiens) > 0) {
            $flags[] = count($blanketLiens).' blanket lien(s) on file';
        }

        return [
            'risk_score' => $riskScore,
            'risk_level' => $this->getRiskLevel($riskScore),
            'total_filings' => count($filings),
            'active_filings' => count($activeFilings),
            'active_filing_details' => $activeFilings,
            'mca_related_filings' => count($mcaRelatedFilings),
            'blanket_liens' => count($blanketLiens),
            'total_secured_amount' => $totalSecuredAmount,
            'flags' => $flags,
            'recommendation' => $this->getRecommendation(count($mcaRelatedFilings), count($blanketLiens)),
        ];
    }

    /**
     * Calculate UCC risk score
     */
    protected function calculateUCCRiskScore(int $active, int $mcaRelated, int $blanketLiens): int
    {
        $score = 100;

        // Deduct for active filings
        $score -= min($active * 5, 25);

        // Deduct more for MCA-related filings
        $score -= min($mcaRelated * 15, 45);

        // Deduct for blanket liens
        $score -= min($blanketLiens * 10, 30);

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

        return 'high';
    }

    /**
     * Get recommendation based on findings
     */
    protected function getRecommendation(int $mcaRelated, int $blanketLiens): string
    {
        if ($mcaRelated >= 3) {
            return 'HIGH RISK - Multiple MCA positions indicated by UCC filings';
        }

        if ($blanketLiens >= 2) {
            return 'CAUTION - Multiple blanket liens may limit collateral availability';
        }

        if ($mcaRelated > 0 || $blanketLiens > 0) {
            return 'REVIEW - Verify position and collateral priority';
        }

        return 'LOW RISK - No significant UCC concerns';
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
            Log::error('UCC API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            // Return empty results for graceful degradation
            return [
                'success' => false,
                'error' => true,
                'message' => $e->getMessage(),
                'filings' => [],
            ];
        }
    }
}
