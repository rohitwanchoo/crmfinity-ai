<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PersonaService
{
    protected Client $client;

    protected string $apiKey;

    protected string $templateId;

    protected string $apiVersion;

    protected string $webhookSecret;

    protected bool $enabled = false;

    public function __construct()
    {
        $this->loadConfiguration();

        $this->client = new Client([
            'base_uri' => 'https://withpersona.com/api/v1',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Persona-Version' => $this->apiVersion,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Load configuration from database with fallback to config files
     */
    protected function loadConfiguration(): void
    {
        $dbSetting = IntegrationSetting::where('integration', 'persona')->first();

        if ($dbSetting && $dbSetting->isConfigured()) {
            $this->enabled = $dbSetting->enabled;
            $this->apiKey = $dbSetting->getCredential('api_key', '');
            $this->templateId = $dbSetting->getCredential('template_id', '');
            $this->webhookSecret = $dbSetting->getCredential('webhook_secret', '');
            $this->apiVersion = $dbSetting->getSetting('api_version', '2023-01-05');
        } else {
            $this->enabled = ! empty(config('persona.api_key'));
            $this->apiKey = config('persona.api_key', '');
            $this->templateId = config('persona.template_id', '');
            $this->apiVersion = config('persona.api_version', '2023-01-05');
            $this->webhookSecret = config('persona.webhook_secret', '');
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
     * Create a new inquiry for identity verification
     */
    public function createInquiry(array $data): array
    {
        $payload = [
            'data' => [
                'attributes' => [
                    'inquiry-template-id' => $this->templateId,
                    'reference-id' => $data['reference_id'] ?? null,
                    'note' => $data['note'] ?? null,
                    'fields' => [
                        'name-first' => $data['first_name'] ?? null,
                        'name-last' => $data['last_name'] ?? null,
                        'email-address' => $data['email'] ?? null,
                        'phone-number' => $data['phone'] ?? null,
                        'birthdate' => $data['birthdate'] ?? null,
                        'address-street-1' => $data['address_line1'] ?? null,
                        'address-street-2' => $data['address_line2'] ?? null,
                        'address-city' => $data['city'] ?? null,
                        'address-subdivision' => $data['state'] ?? null,
                        'address-postal-code' => $data['zip_code'] ?? null,
                    ],
                ],
            ],
        ];

        return $this->request('POST', '/inquiries', $payload);
    }

    /**
     * Get inquiry details
     */
    public function getInquiry(string $inquiryId): array
    {
        return $this->request('GET', "/inquiries/{$inquiryId}");
    }

    /**
     * Resume an inquiry
     */
    public function resumeInquiry(string $inquiryId): array
    {
        return $this->request('POST', "/inquiries/{$inquiryId}/resume");
    }

    /**
     * Get verification details
     */
    public function getVerification(string $verificationId): array
    {
        return $this->request('GET', "/verifications/{$verificationId}");
    }

    /**
     * List all verifications for an inquiry
     */
    public function listVerifications(string $inquiryId): array
    {
        return $this->request('GET', "/inquiries/{$inquiryId}/verifications");
    }

    /**
     * Get account information
     */
    public function getAccount(string $accountId): array
    {
        return $this->request('GET', "/accounts/{$accountId}");
    }

    /**
     * Redact an inquiry (for GDPR compliance)
     */
    public function redactInquiry(string $inquiryId): array
    {
        return $this->request('DELETE', "/inquiries/{$inquiryId}/redact");
    }

    /**
     * Parse inquiry status to risk score
     */
    public function calculateRiskScore(array $inquiry): array
    {
        $status = $inquiry['data']['attributes']['status'] ?? 'pending';
        $checks = $inquiry['data']['relationships']['verifications']['data'] ?? [];

        $score = 0;
        $flags = [];
        $passedChecks = 0;
        $totalChecks = count($checks);

        switch ($status) {
            case 'completed':
                $score = 100;
                break;
            case 'approved':
                $score = 90;
                break;
            case 'needs_review':
                $score = 50;
                $flags[] = 'Manual review required';
                break;
            case 'declined':
                $score = 10;
                $flags[] = 'Identity verification declined';
                break;
            case 'failed':
                $score = 0;
                $flags[] = 'Identity verification failed';
                break;
            default:
                $score = 50;
                $flags[] = 'Verification pending';
        }

        return [
            'score' => $score,
            'status' => $status,
            'flags' => $flags,
            'checks_passed' => $passedChecks,
            'total_checks' => $totalChecks,
            'risk_level' => $this->getRiskLevel($score),
        ];
    }

    /**
     * Get risk level from score
     */
    protected function getRiskLevel(int $score): string
    {
        if ($score >= 80) {
            return 'low';
        }
        if ($score >= 50) {
            return 'medium';
        }

        return 'high';
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
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
            Log::error('Persona API request failed', [
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
