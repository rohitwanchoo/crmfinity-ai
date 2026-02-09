<?php

namespace App\Services;

use App\Models\HubspotConnection;
use App\Models\HubspotSyncedOffer;
use App\Models\McaOffer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HubspotService
{
    protected ?HubspotConnection $connection = null;
    protected string $apiBaseUrl;
    protected string $oauthBaseUrl;
    protected string $tokenUrl;

    public function __construct()
    {
        $this->apiBaseUrl = config('hubspot.api_base_url');
        $this->oauthBaseUrl = config('hubspot.oauth_base_url');
        $this->tokenUrl = config('hubspot.token_url');
    }

    /**
     * Set the connection to use for API calls.
     */
    public function setConnection(HubspotConnection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Get the OAuth authorization URL.
     */
    public function getAuthorizationUrl(string $state = null): string
    {
        $params = [
            'client_id' => config('hubspot.client_id'),
            'redirect_uri' => url(config('hubspot.redirect_uri')),
            'scope' => implode(' ', config('hubspot.scopes')),
            'state' => $state ?? Str::random(40),
        ];

        return $this->oauthBaseUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('hubspot.client_id'),
            'client_secret' => config('hubspot.client_secret'),
            'redirect_uri' => url(config('hubspot.redirect_uri')),
            'code' => $code,
        ]);

        if (!$response->successful()) {
            Log::error('HubSpot token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange code for tokens: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Refresh the access token.
     */
    public function refreshAccessToken(HubspotConnection $connection): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => config('hubspot.client_id'),
            'client_secret' => config('hubspot.client_secret'),
            'refresh_token' => $connection->getDecryptedRefreshToken(),
        ]);

        if (!$response->successful()) {
            Log::error('HubSpot token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to refresh access token');
        }

        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $data;
    }

    /**
     * Get a valid access token, refreshing if necessary.
     */
    protected function getValidAccessToken(): string
    {
        if (!$this->connection) {
            throw new \Exception('No HubSpot connection set');
        }

        if ($this->connection->tokenExpiresSoon()) {
            $this->refreshAccessToken($this->connection);
            $this->connection->refresh();
        }

        return $this->connection->getDecryptedAccessToken();
    }

    /**
     * Make an authenticated API request.
     */
    protected function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getValidAccessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->$method($this->apiBaseUrl . $endpoint, $data);

        if (!$response->successful()) {
            Log::error('HubSpot API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('HubSpot API error: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Get account info for the connected portal.
     */
    public function getAccountInfo(): array
    {
        return $this->apiRequest('get', '/account-info/v3/api-usage/daily/private-apps');
    }

    /**
     * Get access token info.
     */
    public function getTokenInfo(): array
    {
        $token = $this->getValidAccessToken();

        $response = Http::get($this->apiBaseUrl . '/oauth/v1/access-tokens/' . $token);

        return $response->json() ?? [];
    }

    // ==========================================
    // CONTACTS
    // ==========================================

    /**
     * Get a contact by ID.
     */
    public function getContact(string $contactId, array $properties = []): array
    {
        $query = [];
        if (!empty($properties)) {
            $query['properties'] = implode(',', $properties);
        }

        $endpoint = '/crm/v3/objects/contacts/' . $contactId;
        if (!empty($query)) {
            $endpoint .= '?' . http_build_query($query);
        }

        return $this->apiRequest('get', $endpoint);
    }

    /**
     * Search contacts.
     */
    public function searchContacts(array $filters, array $properties = [], int $limit = 10): array
    {
        $data = [
            'filterGroups' => [['filters' => $filters]],
            'properties' => $properties,
            'limit' => $limit,
        ];

        return $this->apiRequest('post', '/crm/v3/objects/contacts/search', $data);
    }

    /**
     * Get contacts list.
     */
    public function getContacts(int $limit = 100, string $after = null, array $properties = []): array
    {
        $query = ['limit' => $limit];

        if ($after) {
            $query['after'] = $after;
        }

        if (!empty($properties)) {
            $query['properties'] = implode(',', $properties);
        }

        return $this->apiRequest('get', '/crm/v3/objects/contacts?' . http_build_query($query));
    }

    // ==========================================
    // COMPANIES
    // ==========================================

    /**
     * Get a company by ID.
     */
    public function getCompany(string $companyId, array $properties = []): array
    {
        $query = [];
        if (!empty($properties)) {
            $query['properties'] = implode(',', $properties);
        }

        $endpoint = '/crm/v3/objects/companies/' . $companyId;
        if (!empty($query)) {
            $endpoint .= '?' . http_build_query($query);
        }

        return $this->apiRequest('get', $endpoint);
    }

    /**
     * Search companies.
     */
    public function searchCompanies(array $filters, array $properties = [], int $limit = 10): array
    {
        $data = [
            'filterGroups' => [['filters' => $filters]],
            'properties' => $properties,
            'limit' => $limit,
        ];

        return $this->apiRequest('post', '/crm/v3/objects/companies/search', $data);
    }

    // ==========================================
    // DEALS
    // ==========================================

    /**
     * Create a deal.
     */
    public function createDeal(array $properties, array $associations = []): array
    {
        $data = ['properties' => $properties];

        if (!empty($associations)) {
            $data['associations'] = $associations;
        }

        return $this->apiRequest('post', '/crm/v3/objects/deals', $data);
    }

    /**
     * Update a deal.
     */
    public function updateDeal(string $dealId, array $properties): array
    {
        return $this->apiRequest('patch', '/crm/v3/objects/deals/' . $dealId, [
            'properties' => $properties,
        ]);
    }

    /**
     * Get a deal by ID.
     */
    public function getDeal(string $dealId, array $properties = []): array
    {
        $query = [];
        if (!empty($properties)) {
            $query['properties'] = implode(',', $properties);
        }

        $endpoint = '/crm/v3/objects/deals/' . $dealId;
        if (!empty($query)) {
            $endpoint .= '?' . http_build_query($query);
        }

        return $this->apiRequest('get', $endpoint);
    }

    /**
     * Search deals.
     */
    public function searchDeals(array $filters, array $properties = [], int $limit = 10): array
    {
        $data = [
            'filterGroups' => [['filters' => $filters]],
            'properties' => $properties,
            'limit' => $limit,
        ];

        return $this->apiRequest('post', '/crm/v3/objects/deals/search', $data);
    }

    /**
     * Get deal pipelines.
     */
    public function getDealPipelines(): array
    {
        return $this->apiRequest('get', '/crm/v3/pipelines/deals');
    }

    // ==========================================
    // CUSTOM PROPERTIES
    // ==========================================

    /**
     * Create a custom property for deals.
     */
    public function createDealProperty(array $property): array
    {
        return $this->apiRequest('post', '/crm/v3/properties/deals', $property);
    }

    /**
     * Get all deal properties.
     */
    public function getDealProperties(): array
    {
        return $this->apiRequest('get', '/crm/v3/properties/deals');
    }

    /**
     * Ensure MCA custom properties exist in HubSpot.
     */
    public function ensureCustomPropertiesExist(): array
    {
        $existingProperties = $this->getDealProperties();
        $existingNames = collect($existingProperties['results'] ?? [])->pluck('name')->toArray();

        $created = [];
        $customProperties = config('hubspot.custom_properties');

        foreach ($customProperties as $key => $property) {
            if (!in_array($property['name'], $existingNames)) {
                try {
                    $this->createDealProperty($property);
                    $created[] = $property['name'];
                } catch (\Exception $e) {
                    Log::warning('Failed to create HubSpot property: ' . $property['name'], [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $created;
    }

    // ==========================================
    // MCA OFFER SYNC
    // ==========================================

    /**
     * Sync an MCA offer to HubSpot as a deal.
     */
    public function syncMcaOfferToDeal(McaOffer $offer, ?string $contactId = null, ?string $companyId = null): HubspotSyncedOffer
    {
        // Calculate values
        $fundedAmount = $offer->advance_amount;
        $factorRate = $offer->factor_rate;
        $totalPayback = $fundedAmount * $factorRate;
        $monthlyPayment = $offer->term_months > 0 ? $totalPayback / $offer->term_months : 0;
        $dailyPayment = $monthlyPayment / 21.67;

        $properties = [
            'dealname' => 'MCA Offer - ' . ($offer->offer_name ?? $offer->offer_id),
            'amount' => round($fundedAmount, 2),
            'pipeline' => config('hubspot.deal_pipeline'),
            'dealstage' => config('hubspot.deal_stages.pending'),
            'mca_funded_amount' => round($fundedAmount, 2),
            'mca_factor_rate' => round($factorRate, 2),
            'mca_total_payback' => round($totalPayback, 2),
            'mca_monthly_payment' => round($monthlyPayment, 2),
            'mca_daily_payment' => round($dailyPayment, 2),
            'mca_term_months' => $offer->term_months,
            'mca_true_revenue' => round($offer->true_revenue_monthly, 2),
            'mca_offer_id' => $offer->offer_id,
        ];

        // Build associations
        $associations = [];
        if ($contactId) {
            $associations[] = [
                'to' => ['id' => $contactId],
                'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => 3]],
            ];
        }
        if ($companyId) {
            $associations[] = [
                'to' => ['id' => $companyId],
                'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => 5]],
            ];
        }

        // Check if already synced
        $existingSync = HubspotSyncedOffer::where('hubspot_connection_id', $this->connection->id)
            ->where('mca_offer_id', $offer->offer_id)
            ->first();

        try {
            if ($existingSync) {
                // Update existing deal
                $deal = $this->updateDeal($existingSync->hubspot_deal_id, $properties);
                $existingSync->markAsSynced();
                return $existingSync;
            } else {
                // Create new deal
                $deal = $this->createDeal($properties, $associations);

                return HubspotSyncedOffer::create([
                    'hubspot_connection_id' => $this->connection->id,
                    'mca_offer_id' => $offer->offer_id,
                    'hubspot_deal_id' => $deal['id'],
                    'hubspot_contact_id' => $contactId,
                    'hubspot_company_id' => $companyId,
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync MCA offer to HubSpot', [
                'offer_id' => $offer->offer_id,
                'error' => $e->getMessage(),
            ]);

            if ($existingSync) {
                $existingSync->markAsFailed($e->getMessage());
                return $existingSync;
            }

            $syncedOffer = HubspotSyncedOffer::create([
                'hubspot_connection_id' => $this->connection->id,
                'mca_offer_id' => $offer->offer_id,
                'hubspot_deal_id' => '',
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'last_synced_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Get CRM card data for a contact/company.
     */
    public function getCrmCardData(string $objectType, string $objectId): array
    {
        // Search for deals associated with this object that have MCA data
        $filters = [
            [
                'propertyName' => 'mca_offer_id',
                'operator' => 'HAS_PROPERTY',
            ],
        ];

        $properties = [
            'dealname',
            'amount',
            'dealstage',
            'mca_funded_amount',
            'mca_factor_rate',
            'mca_total_payback',
            'mca_monthly_payment',
            'mca_daily_payment',
            'mca_term_months',
            'mca_true_revenue',
            'mca_offer_id',
        ];

        try {
            $deals = $this->searchDeals($filters, $properties, 10);

            $results = [];
            foreach ($deals['results'] ?? [] as $deal) {
                $props = $deal['properties'] ?? [];

                $results[] = [
                    'objectId' => $deal['id'],
                    'title' => $props['dealname'] ?? 'MCA Offer',
                    'properties' => [
                        [
                            'label' => 'Funded Amount',
                            'dataType' => 'CURRENCY',
                            'value' => $props['mca_funded_amount'] ?? 0,
                        ],
                        [
                            'label' => 'Factor Rate',
                            'dataType' => 'NUMERIC',
                            'value' => $props['mca_factor_rate'] ?? 0,
                        ],
                        [
                            'label' => 'Total Payback',
                            'dataType' => 'CURRENCY',
                            'value' => $props['mca_total_payback'] ?? 0,
                        ],
                        [
                            'label' => 'Monthly Payment',
                            'dataType' => 'CURRENCY',
                            'value' => $props['mca_monthly_payment'] ?? 0,
                        ],
                        [
                            'label' => 'Daily Payment',
                            'dataType' => 'CURRENCY',
                            'value' => $props['mca_daily_payment'] ?? 0,
                        ],
                        [
                            'label' => 'Term',
                            'dataType' => 'STRING',
                            'value' => ($props['mca_term_months'] ?? 0) . ' months',
                        ],
                    ],
                ];
            }

            return [
                'results' => $results,
                'primaryAction' => [
                    'type' => 'IFRAME',
                    'width' => 890,
                    'height' => 748,
                    'uri' => url('/hubspot/calculator'),
                    'label' => 'Open MCA Calculator',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get CRM card data', [
                'objectType' => $objectType,
                'objectId' => $objectId,
                'error' => $e->getMessage(),
            ]);

            return [
                'results' => [],
                'message' => 'Unable to load MCA offers',
            ];
        }
    }
}
