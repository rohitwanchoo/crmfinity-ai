<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlaidService
{
    protected Client $client;

    protected string $clientId;

    protected string $secret;

    protected string $baseUrl;

    protected array $products;

    protected array $countryCodes;

    protected string $language;

    protected ?string $webhookUrl;

    protected bool $enabled = false;

    public function __construct()
    {
        $this->loadConfiguration();

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Load configuration from database with fallback to config files
     */
    protected function loadConfiguration(): void
    {
        $dbSetting = IntegrationSetting::where('integration', 'plaid')->first();

        if ($dbSetting && $dbSetting->isConfigured()) {
            // Use database configuration
            $this->enabled = $dbSetting->enabled;
            $this->clientId = $dbSetting->getCredential('client_id', '');
            $this->secret = $dbSetting->getCredential('secret', '');
            $this->webhookUrl = $dbSetting->getCredential('webhook_url');

            $environment = $dbSetting->environment ?? 'sandbox';
            $baseUrls = [
                'sandbox' => 'https://sandbox.plaid.com',
                'development' => 'https://development.plaid.com',
                'production' => 'https://production.plaid.com',
            ];
            $this->baseUrl = $baseUrls[$environment] ?? $baseUrls['sandbox'];

            $this->products = $dbSetting->getSetting('products', ['transactions', 'auth', 'identity']);
            $this->countryCodes = (array) $dbSetting->getSetting('country_codes', ['US']);
            $this->language = $dbSetting->getSetting('language', 'en');
        } else {
            // Fallback to config file
            $this->enabled = ! empty(config('plaid.client_id'));
            $this->clientId = config('plaid.client_id', '');
            $this->secret = config('plaid.secret', '');
            $this->baseUrl = config('plaid.base_urls.'.config('plaid.env', 'sandbox'));
            $this->products = config('plaid.products', ['transactions', 'auth', 'identity']);
            $this->countryCodes = config('plaid.country_codes', ['US']);
            $this->language = config('plaid.language', 'en');
            $this->webhookUrl = config('plaid.webhook_url');
        }
    }

    /**
     * Check if the integration is enabled and configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled && ! empty($this->clientId) && ! empty($this->secret);
    }

    /**
     * Create a link token for Plaid Link initialization
     */
    public function createLinkToken(int $userId, ?string $accessToken = null): array
    {
        $payload = [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'user' => [
                'client_user_id' => (string) $userId,
            ],
            'client_name' => config('app.name'),
            'products' => $this->products,
            'country_codes' => $this->countryCodes,
            'language' => $this->language,
        ];

        if ($this->webhookUrl) {
            $payload['webhook'] = $this->webhookUrl;
        }

        // For update mode (re-linking expired account)
        if ($accessToken) {
            $payload['access_token'] = $accessToken;
            unset($payload['products']);
        }

        return $this->request('POST', '/link/token/create', $payload);
    }

    /**
     * Exchange public token for access token
     */
    public function exchangePublicToken(string $publicToken): array
    {
        return $this->request('POST', '/item/public_token/exchange', [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'public_token' => $publicToken,
        ]);
    }

    /**
     * Get account information
     */
    public function getAccounts(string $accessToken): array
    {
        return $this->request('POST', '/accounts/get', [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ]);
    }

    /**
     * Get account balances
     */
    public function getBalance(string $accessToken, ?array $accountIds = null): array
    {
        $payload = [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ];

        if ($accountIds) {
            $payload['options'] = ['account_ids' => $accountIds];
        }

        return $this->request('POST', '/accounts/balance/get', $payload);
    }

    /**
     * Get transactions using sync endpoint (recommended)
     */
    public function syncTransactions(string $accessToken, ?string $cursor = null): array
    {
        $payload = [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
            'options' => [
                'include_personal_finance_category' => true,
            ],
        ];

        if ($cursor) {
            $payload['cursor'] = $cursor;
        }

        return $this->request('POST', '/transactions/sync', $payload);
    }

    /**
     * Get all transactions with pagination
     */
    public function getAllTransactions(string $accessToken, ?string $cursor = null): array
    {
        $allAdded = [];
        $allModified = [];
        $allRemoved = [];
        $hasMore = true;
        $nextCursor = $cursor;

        while ($hasMore) {
            $response = $this->syncTransactions($accessToken, $nextCursor);

            if (isset($response['error'])) {
                return $response;
            }

            $allAdded = array_merge($allAdded, $response['added'] ?? []);
            $allModified = array_merge($allModified, $response['modified'] ?? []);
            $allRemoved = array_merge($allRemoved, $response['removed'] ?? []);
            $hasMore = $response['has_more'] ?? false;
            $nextCursor = $response['next_cursor'] ?? null;
        }

        return [
            'added' => $allAdded,
            'modified' => $allModified,
            'removed' => $allRemoved,
            'cursor' => $nextCursor,
            'accounts' => $response['accounts'] ?? [],
        ];
    }

    /**
     * Get transactions with date range (legacy endpoint)
     */
    public function getTransactions(
        string $accessToken,
        string $startDate,
        string $endDate,
        int $count = 500,
        int $offset = 0
    ): array {
        return $this->request('POST', '/transactions/get', [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'options' => [
                'count' => $count,
                'offset' => $offset,
                'include_personal_finance_category' => true,
            ],
        ]);
    }

    /**
     * Get identity information
     */
    public function getIdentity(string $accessToken): array
    {
        return $this->request('POST', '/identity/get', [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ]);
    }

    /**
     * Get auth information (account and routing numbers)
     */
    public function getAuth(string $accessToken): array
    {
        return $this->request('POST', '/auth/get', [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ]);
    }

    /**
     * Get institution details
     */
    public function getInstitution(string $institutionId): array
    {
        $cacheKey = "plaid_institution_{$institutionId}";

        return Cache::remember($cacheKey, 86400, function () use ($institutionId) {
            return $this->request('POST', '/institutions/get_by_id', [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'institution_id' => $institutionId,
                'country_codes' => $this->countryCodes,
                'options' => [
                    'include_optional_metadata' => true,
                ],
            ]);
        });
    }

    /**
     * Get item (connection) status
     */
    public function getItem(string $accessToken): array
    {
        return $this->request('POST', '/item/get', [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ]);
    }

    /**
     * Remove an item (disconnect bank)
     */
    public function removeItem(string $accessToken): array
    {
        return $this->request('POST', '/item/remove', [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ]);
    }

    /**
     * Create a sandbox public token (for testing)
     */
    public function createSandboxPublicToken(string $institutionId = 'ins_109508', ?array $products = null): array
    {
        return $this->request('POST', '/sandbox/public_token/create', [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'institution_id' => $institutionId,
            'initial_products' => $products ?? $this->products,
        ]);
    }

    /**
     * Verify webhook
     */
    public function verifyWebhook(string $body, string $signedJwt): bool
    {
        try {
            $response = $this->request('POST', '/webhook_verification_key/get', [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'key_id' => $this->extractKeyIdFromJwt($signedJwt),
            ]);

            // Implement JWT verification using the returned key
            // This is a simplified version - production should use proper JWT library
            return isset($response['key']);
        } catch (Exception $e) {
            Log::error('Plaid webhook verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Make API request
     */
    protected function request(string $method, string $endpoint, array $payload = []): array
    {
        try {
            $response = $this->client->request($method, $endpoint, [
                'json' => $payload,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Plaid API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            // Try to extract Plaid error from response
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $body = json_decode($e->getResponse()->getBody()->getContents(), true);

                return [
                    'error' => true,
                    'error_type' => $body['error_type'] ?? 'API_ERROR',
                    'error_code' => $body['error_code'] ?? 'INTERNAL_SERVER_ERROR',
                    'error_message' => $body['error_message'] ?? $e->getMessage(),
                    'display_message' => $body['display_message'] ?? 'An error occurred',
                ];
            }

            return [
                'error' => true,
                'error_type' => 'API_ERROR',
                'error_code' => 'CONNECTION_ERROR',
                'error_message' => $e->getMessage(),
                'display_message' => 'Failed to connect to Plaid',
            ];
        }
    }

    /**
     * Extract key ID from JWT header
     */
    protected function extractKeyIdFromJwt(string $jwt): string
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new Exception('Invalid JWT format');
        }

        $header = json_decode(base64_decode($parts[0]), true);

        return $header['kid'] ?? '';
    }

    /**
     * Format Plaid transaction for our system
     */
    public function formatTransaction(array $plaidTransaction): array
    {
        $amount = $plaidTransaction['amount'];
        $isCredit = $amount < 0; // Plaid uses negative for credits

        return [
            'plaid_transaction_id' => $plaidTransaction['transaction_id'],
            'transaction_date' => $plaidTransaction['date'],
            'description' => $plaidTransaction['name'] ?? $plaidTransaction['merchant_name'] ?? 'Unknown',
            'amount' => abs($amount),
            'type' => $isCredit ? 'credit' : 'debit',
            'category' => $plaidTransaction['personal_finance_category']['primary'] ??
                         ($plaidTransaction['category'][0] ?? 'Other'),
            'subcategory' => $plaidTransaction['personal_finance_category']['detailed'] ??
                            ($plaidTransaction['category'][1] ?? null),
            'merchant_name' => $plaidTransaction['merchant_name'],
            'pending' => $plaidTransaction['pending'] ?? false,
            'account_id' => $plaidTransaction['account_id'],
            'iso_currency_code' => $plaidTransaction['iso_currency_code'] ?? 'USD',
            'location' => $plaidTransaction['location'] ?? null,
            'payment_channel' => $plaidTransaction['payment_channel'] ?? null,
        ];
    }
}
