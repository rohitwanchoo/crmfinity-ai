<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExperianService
{
    protected Client $client;

    protected string $baseUrl;

    protected ?string $accessToken = null;

    protected string $clientId;

    protected string $clientSecret;

    protected string $username;

    protected string $password;

    protected string $subscriberCode;

    protected bool $enabled = false;

    public function __construct()
    {
        $this->loadConfiguration();

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 60,
        ]);
    }

    /**
     * Load configuration from database with fallback to config files
     */
    protected function loadConfiguration(): void
    {
        $dbSetting = IntegrationSetting::where('integration', 'experian')->first();

        if ($dbSetting && $dbSetting->isConfigured()) {
            $this->enabled = $dbSetting->enabled;
            $this->clientId = $dbSetting->getCredential('client_id', '');
            $this->clientSecret = $dbSetting->getCredential('client_secret', '');
            $this->username = $dbSetting->getCredential('username', '');
            $this->password = $dbSetting->getCredential('password', '');
            $this->subscriberCode = $dbSetting->getCredential('subscriber_code', '');

            $environment = $dbSetting->environment ?? 'sandbox';
            $baseUrls = [
                'sandbox' => 'https://sandbox-us-api.experian.com',
                'production' => 'https://us-api.experian.com',
            ];
            $this->baseUrl = $baseUrls[$environment] ?? $baseUrls['sandbox'];
        } else {
            $this->enabled = ! empty(config('experian.client_id'));
            $this->clientId = config('experian.client_id', '');
            $this->clientSecret = config('experian.client_secret', '');
            $this->username = config('experian.username', '');
            $this->password = config('experian.password', '');
            $this->subscriberCode = config('experian.subscriber_code', '');
            $env = config('experian.env', 'sandbox');
            $this->baseUrl = config("experian.base_urls.{$env}");
        }
    }

    /**
     * Check if the integration is enabled and configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled && ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    /**
     * Get OAuth access token
     */
    protected function getAccessToken(): string
    {
        $cacheKey = 'experian_access_token';

        return Cache::remember($cacheKey, 3500, function () {
            $response = $this->client->request('POST', '/oauth2/v1/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['access_token'];
        });
    }

    /**
     * Pull consumer credit report
     */
    public function getCreditReport(array $consumer): array
    {
        $payload = [
            'consumerPii' => [
                'primaryApplicant' => [
                    'name' => [
                        'lastName' => $consumer['last_name'],
                        'firstName' => $consumer['first_name'],
                        'middleName' => $consumer['middle_name'] ?? '',
                    ],
                    'ssn' => [
                        'ssn' => preg_replace('/[^0-9]/', '', $consumer['ssn']),
                    ],
                    'dob' => [
                        'dob' => $consumer['dob'], // MMDDYYYY format
                    ],
                    'currentAddress' => [
                        'line1' => $consumer['address_line1'],
                        'line2' => $consumer['address_line2'] ?? '',
                        'city' => $consumer['city'],
                        'state' => $consumer['state'],
                        'zipCode' => $consumer['zip_code'],
                    ],
                ],
            ],
            'requestor' => [
                'subscriberCode' => $this->subscriberCode,
            ],
            'permissiblePurpose' => [
                'type' => '3F', // Credit transaction
            ],
            'addOns' => [
                'riskModels' => [
                    'modelIndicator' => ['V4'],
                ],
            ],
        ];

        return $this->request('POST', '/consumerservices/credit-profile/v2/credit-report', $payload);
    }

    /**
     * Get credit score only
     */
    public function getCreditScore(array $consumer): array
    {
        $report = $this->getCreditReport($consumer);

        if (isset($report['error'])) {
            return $report;
        }

        $riskModel = $report['creditProfile']['riskModel'][0] ?? null;

        return [
            'score' => $riskModel['score'] ?? null,
            'score_factors' => $riskModel['scoreFactors'] ?? [],
            'model_indicator' => $riskModel['modelIndicator'] ?? null,
        ];
    }

    /**
     * Get business credit report
     */
    public function getBusinessCreditReport(array $business): array
    {
        $payload = [
            'bin' => $business['bin'] ?? null,
            'businessName' => $business['business_name'],
            'address' => [
                'street' => $business['address'],
                'city' => $business['city'],
                'state' => $business['state'],
                'zip' => $business['zip_code'],
            ],
            'phone' => $business['phone'] ?? null,
            'taxId' => $business['ein'] ?? null,
        ];

        return $this->request('POST', '/businessinformation/businesses/v1/reports/premier-profile', $payload);
    }

    /**
     * Perform identity verification
     */
    public function verifyIdentity(array $consumer): array
    {
        $payload = [
            'firstName' => $consumer['first_name'],
            'lastName' => $consumer['last_name'],
            'ssn' => preg_replace('/[^0-9]/', '', $consumer['ssn']),
            'dob' => $consumer['dob'],
            'address' => [
                'line1' => $consumer['address_line1'],
                'city' => $consumer['city'],
                'state' => $consumer['state'],
                'zipCode' => $consumer['zip_code'],
            ],
        ];

        return $this->request('POST', '/decisioniq/v1/precise-id', $payload);
    }

    /**
     * Parse credit report for MCA underwriting
     */
    public function analyzeCreditForMCA(array $creditReport): array
    {
        $profile = $creditReport['creditProfile'] ?? [];

        $score = $profile['riskModel'][0]['score'] ?? 0;
        $tradelines = $profile['tradeline'] ?? [];
        $publicRecords = $profile['publicRecord'] ?? [];
        $inquiries = $profile['inquiry'] ?? [];

        // Calculate metrics
        $totalDebt = 0;
        $openAccounts = 0;
        $delinquentAccounts = 0;
        $collectionsCount = 0;

        foreach ($tradelines as $trade) {
            if ($trade['openIndicator'] === 'O') {
                $openAccounts++;
                $totalDebt += $trade['balanceAmount'] ?? 0;
            }
            if (isset($trade['paymentStatus']) && $trade['paymentStatus'] !== 'C') {
                $delinquentAccounts++;
            }
            if ($trade['accountType'] === 'Collection') {
                $collectionsCount++;
            }
        }

        $bankruptcies = array_filter($publicRecords, fn ($r) => str_contains($r['type'] ?? '', 'Bankruptcy'));
        $recentInquiries = count(array_filter($inquiries, fn ($i) => strtotime($i['date'] ?? '') > strtotime('-90 days')
        ));

        return [
            'credit_score' => $score,
            'total_debt' => $totalDebt,
            'open_accounts' => $openAccounts,
            'delinquent_accounts' => $delinquentAccounts,
            'collections_count' => $collectionsCount,
            'bankruptcies' => count($bankruptcies),
            'recent_inquiries_90d' => $recentInquiries,
            'risk_score' => $this->calculateCreditRiskScore($score, $delinquentAccounts, count($bankruptcies)),
            'risk_level' => $this->getCreditRiskLevel($score),
            'flags' => $this->getCreditFlags($score, $delinquentAccounts, count($bankruptcies), $collectionsCount),
        ];
    }

    /**
     * Calculate credit risk score for MCA
     */
    protected function calculateCreditRiskScore(int $creditScore, int $delinquent, int $bankruptcies): int
    {
        $score = 0;

        // Credit score component (40%)
        if ($creditScore >= 750) {
            $score += 40;
        } elseif ($creditScore >= 700) {
            $score += 32;
        } elseif ($creditScore >= 650) {
            $score += 24;
        } elseif ($creditScore >= 600) {
            $score += 16;
        } elseif ($creditScore >= 550) {
            $score += 8;
        }

        // Delinquency component (30%)
        if ($delinquent === 0) {
            $score += 30;
        } elseif ($delinquent === 1) {
            $score += 20;
        } elseif ($delinquent === 2) {
            $score += 10;
        }

        // Bankruptcy component (30%)
        if ($bankruptcies === 0) {
            $score += 30;
        } elseif ($bankruptcies === 1) {
            $score += 10;
        }

        return $score;
    }

    /**
     * Get credit risk level
     */
    protected function getCreditRiskLevel(int $score): string
    {
        if ($score >= 700) {
            return 'low';
        }
        if ($score >= 600) {
            return 'medium';
        }

        return 'high';
    }

    /**
     * Get credit flags
     */
    protected function getCreditFlags(int $score, int $delinquent, int $bankruptcies, int $collections): array
    {
        $flags = [];

        if ($score < 550) {
            $flags[] = 'Very low credit score';
        }
        if ($score < 600) {
            $flags[] = 'Below average credit score';
        }
        if ($delinquent > 0) {
            $flags[] = "{$delinquent} delinquent account(s)";
        }
        if ($bankruptcies > 0) {
            $flags[] = 'Bankruptcy on record';
        }
        if ($collections > 2) {
            $flags[] = 'Multiple collections accounts';
        }

        return $flags;
    }

    /**
     * Make authenticated API request
     */
    protected function request(string $method, string $endpoint, array $payload = []): array
    {
        try {
            $token = $this->getAccessToken();

            $options = [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
            ];

            if (! empty($payload)) {
                $options['json'] = $payload;
            }

            $response = $this->client->request($method, $endpoint, $options);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Experian API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }
}
