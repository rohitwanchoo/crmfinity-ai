<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration',
        'display_name',
        'description',
        'enabled',
        'environment',
        'credentials',
        'settings',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * Get a specific credential value
     */
    public function getCredential(string $key, $default = null)
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * Get a specific setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Check if integration is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->enabled && ! empty($this->credentials);
    }

    /**
     * Get the base URL for the current environment
     */
    public function getBaseUrl(): ?string
    {
        $urls = $this->getSetting('base_urls', []);

        return $urls[$this->environment] ?? null;
    }

    /**
     * Static helper to get integration config with fallback to file config
     */
    public static function getConfig(string $integration): array
    {
        $setting = self::where('integration', $integration)->first();

        if ($setting && $setting->isConfigured()) {
            return [
                'enabled' => $setting->enabled,
                'environment' => $setting->environment,
                'credentials' => $setting->credentials ?? [],
                'settings' => $setting->settings ?? [],
                'base_url' => $setting->getBaseUrl(),
            ];
        }

        // Fallback to file-based config
        return [
            'enabled' => true,
            'environment' => config("{$integration}.env", 'sandbox'),
            'credentials' => config($integration, []),
            'settings' => config($integration, []),
            'base_url' => null,
        ];
    }

    /**
     * Get all integrations with their status
     */
    public static function getAllIntegrations(): array
    {
        $integrations = [
            'plaid' => [
                'name' => 'Plaid',
                'description' => 'Bank account verification and transaction data',
                'icon' => 'bank',
                'credentials_schema' => [
                    'client_id' => ['label' => 'Client ID', 'type' => 'text', 'required' => true],
                    'secret' => ['label' => 'Secret', 'type' => 'password', 'required' => true],
                    'webhook_url' => ['label' => 'Webhook URL', 'type' => 'url', 'required' => false],
                ],
                'settings_schema' => [
                    'products' => ['label' => 'Products', 'type' => 'multiselect', 'options' => ['transactions', 'auth', 'identity', 'assets', 'investments', 'liabilities']],
                    'country_codes' => ['label' => 'Country Codes', 'type' => 'text', 'default' => 'US'],
                ],
                'environments' => ['sandbox', 'development', 'production'],
            ],
            'experian' => [
                'name' => 'Experian',
                'description' => 'Credit reports and business verification',
                'icon' => 'credit-card',
                'credentials_schema' => [
                    'client_id' => ['label' => 'Client ID', 'type' => 'text', 'required' => true],
                    'client_secret' => ['label' => 'Client Secret', 'type' => 'password', 'required' => true],
                    'username' => ['label' => 'Username', 'type' => 'text', 'required' => true],
                    'password' => ['label' => 'Password', 'type' => 'password', 'required' => true],
                    'subscriber_code' => ['label' => 'Subscriber Code', 'type' => 'text', 'required' => true],
                ],
                'settings_schema' => [],
                'environments' => ['sandbox', 'production'],
            ],
            'persona' => [
                'name' => 'Persona',
                'description' => 'Identity verification and KYC',
                'icon' => 'user-check',
                'credentials_schema' => [
                    'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true],
                    'template_id' => ['label' => 'Template ID', 'type' => 'text', 'required' => true],
                    'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
                ],
                'settings_schema' => [
                    'api_version' => ['label' => 'API Version', 'type' => 'text', 'default' => '2023-01-05'],
                ],
                'environments' => ['sandbox', 'production'],
            ],
            'datamerch' => [
                'name' => 'DataMerch',
                'description' => 'MCA stacking detection and merchant database',
                'icon' => 'database',
                'credentials_schema' => [
                    'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true],
                ],
                'settings_schema' => [
                    'high_risk_threshold' => ['label' => 'High Risk Threshold', 'type' => 'number', 'default' => 3],
                    'medium_risk_threshold' => ['label' => 'Medium Risk Threshold', 'type' => 'number', 'default' => 2],
                ],
                'environments' => ['sandbox', 'production'],
            ],
            'ucc' => [
                'name' => 'UCC Filings',
                'description' => 'UCC lien search and filing verification',
                'icon' => 'file-text',
                'credentials_schema' => [
                    'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true],
                    'api_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false],
                ],
                'settings_schema' => [],
                'environments' => ['sandbox', 'production'],
            ],
            'pacer' => [
                'name' => 'PACER',
                'description' => 'Federal court records and case search (Public Access to Court Electronic Records)',
                'icon' => 'scale',
                'credentials_schema' => [
                    'username' => ['label' => 'PACER Username', 'type' => 'text', 'required' => true],
                    'password' => ['label' => 'PACER Password', 'type' => 'password', 'required' => true],
                    'client_code' => ['label' => 'Client Code', 'type' => 'text', 'required' => false],
                ],
                'settings_schema' => [
                    'default_court' => ['label' => 'Default Court', 'type' => 'text', 'default' => ''],
                    'search_type' => ['label' => 'Default Search Type', 'type' => 'select', 'options' => ['all', 'bankruptcy', 'civil', 'criminal', 'appellate'], 'default' => 'all'],
                    'max_results' => ['label' => 'Max Results', 'type' => 'number', 'default' => 100],
                ],
                'environments' => ['production'],
            ],
        ];

        // Merge with database settings
        $dbSettings = self::all()->keyBy('integration');

        foreach ($integrations as $key => &$integration) {
            $dbSetting = $dbSettings->get($key);
            $integration['enabled'] = $dbSetting?->enabled ?? false;
            $integration['environment'] = $dbSetting?->environment ?? 'sandbox';
            $integration['configured'] = $dbSetting?->isConfigured() ?? false;
            $integration['last_tested_at'] = $dbSetting?->last_tested_at;
            $integration['last_test_status'] = $dbSetting?->last_test_status;
        }

        return $integrations;
    }
}
