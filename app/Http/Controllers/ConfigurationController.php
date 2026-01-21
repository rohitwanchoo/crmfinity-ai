<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfigurationController extends Controller
{
    /**
     * Display the configuration page
     */
    public function index()
    {
        $integrations = IntegrationSetting::getAllIntegrations();
        $dbSettings = IntegrationSetting::all()->keyBy('integration');

        return view('configuration.index', compact('integrations', 'dbSettings'));
    }

    /**
     * Show edit form for a specific integration
     */
    public function edit(string $integration)
    {
        $integrations = IntegrationSetting::getAllIntegrations();

        if (! isset($integrations[$integration])) {
            return redirect()->route('configuration.index')
                ->with('error', 'Integration not found.');
        }

        $integrationConfig = $integrations[$integration];
        $setting = IntegrationSetting::where('integration', $integration)->first();

        return view('configuration.edit', compact('integration', 'integrationConfig', 'setting'));
    }

    /**
     * Update integration settings
     */
    public function update(Request $request, string $integration)
    {
        $integrations = IntegrationSetting::getAllIntegrations();

        if (! isset($integrations[$integration])) {
            return redirect()->route('configuration.index')
                ->with('error', 'Integration not found.');
        }

        $integrationConfig = $integrations[$integration];

        // Build validation rules based on schema
        $rules = [
            'enabled' => 'boolean',
            'environment' => 'required|in:'.implode(',', $integrationConfig['environments']),
        ];

        foreach ($integrationConfig['credentials_schema'] as $key => $field) {
            $rule = $field['required'] ? 'required_if:enabled,1' : 'nullable';
            if ($field['type'] === 'url') {
                $rule .= '|url';
            }
            $rules["credentials.{$key}"] = $rule;
        }

        $validated = $request->validate($rules);

        // Get or create the setting
        $setting = IntegrationSetting::firstOrNew(['integration' => $integration]);

        $setting->display_name = $integrationConfig['name'];
        $setting->description = $integrationConfig['description'];
        $setting->enabled = $request->boolean('enabled');
        $setting->environment = $validated['environment'];

        // Only update credentials if provided (don't overwrite with empty values)
        $credentials = $request->input('credentials', []);
        $existingCredentials = $setting->credentials ?? [];

        foreach ($credentials as $key => $value) {
            if (! empty($value)) {
                $existingCredentials[$key] = $value;
            }
        }
        $setting->credentials = $existingCredentials;

        // Update settings
        $settings = $request->input('settings', []);
        $setting->settings = array_merge($setting->settings ?? [], $settings, [
            'base_urls' => $this->getBaseUrls($integration),
        ]);

        $setting->save();

        return redirect()->route('configuration.index')
            ->with('success', "{$integrationConfig['name']} configuration saved successfully.");
    }

    /**
     * Test an integration connection
     */
    public function test(string $integration)
    {
        $setting = IntegrationSetting::where('integration', $integration)->first();

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Integration not configured. Please save configuration first.',
            ]);
        }

        try {
            $result = $this->testIntegration($integration, $setting);

            $setting->last_tested_at = now();
            $setting->last_test_status = $result['success'] ? 'success' : 'failed';
            $setting->last_test_message = $result['message'];
            $setting->save();

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("Integration test failed for {$integration}", [
                'error' => $e->getMessage(),
            ]);

            $setting->last_tested_at = now();
            $setting->last_test_status = 'failed';
            $setting->last_test_message = $e->getMessage();
            $setting->save();

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Toggle integration enabled status
     */
    public function toggle(Request $request, string $integration)
    {
        $setting = IntegrationSetting::where('integration', $integration)->first();

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Integration not configured. Please configure it first.',
            ]);
        }

        $setting->enabled = ! $setting->enabled;
        $setting->save();

        return response()->json([
            'success' => true,
            'enabled' => $setting->enabled,
            'message' => $setting->enabled ? 'Integration enabled.' : 'Integration disabled.',
        ]);
    }

    /**
     * Test a specific integration
     */
    private function testIntegration(string $integration, IntegrationSetting $setting): array
    {
        switch ($integration) {
            case 'plaid':
                return $this->testPlaid($setting);
            case 'experian':
                return $this->testExperian($setting);
            case 'persona':
                return $this->testPersona($setting);
            case 'datamerch':
                return $this->testDataMerch($setting);
            case 'ucc':
                return $this->testUCC($setting);
            case 'pacer':
                return $this->testPacer($setting);
            default:
                return ['success' => false, 'message' => 'Unknown integration'];
        }
    }

    private function testPlaid(IntegrationSetting $setting): array
    {
        $clientId = $setting->getCredential('client_id');
        $secret = $setting->getCredential('secret');

        if (empty($clientId) || empty($secret)) {
            return ['success' => false, 'message' => 'Missing Client ID or Secret'];
        }

        $baseUrls = [
            'sandbox' => 'https://sandbox.plaid.com',
            'development' => 'https://development.plaid.com',
            'production' => 'https://production.plaid.com',
        ];

        $baseUrl = $baseUrls[$setting->environment] ?? $baseUrls['sandbox'];

        $response = \Http::post("{$baseUrl}/institutions/get", [
            'client_id' => $clientId,
            'secret' => $secret,
            'count' => 1,
            'offset' => 0,
            'country_codes' => ['US'],
        ]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Successfully connected to Plaid API'];
        }

        $error = $response->json('error_message') ?? 'Connection failed';

        return ['success' => false, 'message' => $error];
    }

    private function testExperian(IntegrationSetting $setting): array
    {
        $clientId = $setting->getCredential('client_id');
        $clientSecret = $setting->getCredential('client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'message' => 'Missing Client ID or Client Secret'];
        }

        // Experian uses OAuth, so we test by getting an access token
        $baseUrls = [
            'sandbox' => 'https://sandbox-us-api.experian.com',
            'production' => 'https://us-api.experian.com',
        ];

        $baseUrl = $baseUrls[$setting->environment] ?? $baseUrls['sandbox'];

        $response = \Http::asForm()->post("{$baseUrl}/oauth2/v1/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if ($response->successful() && $response->json('access_token')) {
            return ['success' => true, 'message' => 'Successfully authenticated with Experian API'];
        }

        $error = $response->json('error_description') ?? 'Authentication failed';

        return ['success' => false, 'message' => $error];
    }

    private function testPersona(IntegrationSetting $setting): array
    {
        $apiKey = $setting->getCredential('api_key');

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Missing API Key'];
        }

        $response = \Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Persona-Version' => $setting->getSetting('api_version', '2023-01-05'),
        ])->get('https://withpersona.com/api/v1/inquiries', [
            'page[size]' => 1,
        ]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Successfully connected to Persona API'];
        }

        $error = $response->json('errors.0.title') ?? 'Connection failed';

        return ['success' => false, 'message' => $error];
    }

    private function testDataMerch(IntegrationSetting $setting): array
    {
        $apiKey = $setting->getCredential('api_key');

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Missing API Key'];
        }

        $baseUrls = [
            'sandbox' => 'https://sandbox.datamerch.com/api/v1',
            'production' => 'https://api.datamerch.com/v1',
        ];

        $baseUrl = $baseUrls[$setting->environment] ?? $baseUrls['sandbox'];

        $response = \Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->get("{$baseUrl}/health");

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Successfully connected to DataMerch API'];
        }

        // If health endpoint doesn't exist, try a search with empty params
        $response = \Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->get("{$baseUrl}/merchants", ['limit' => 1]);

        if ($response->successful() || $response->status() === 404) {
            return ['success' => true, 'message' => 'API key validated successfully'];
        }

        return ['success' => false, 'message' => 'Invalid API key or connection failed'];
    }

    private function testUCC(IntegrationSetting $setting): array
    {
        $apiKey = $setting->getCredential('api_key');
        $apiUrl = $setting->getCredential('api_url') ?? 'https://api.uccfilings.com';

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Missing API Key'];
        }

        $response = \Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->get("{$apiUrl}/health");

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Successfully connected to UCC API'];
        }

        if ($response->status() === 401) {
            return ['success' => false, 'message' => 'Invalid API key'];
        }

        return ['success' => true, 'message' => 'API endpoint accessible (health check not available)'];
    }

    private function testPacer(IntegrationSetting $setting): array
    {
        $username = $setting->getCredential('username');
        $password = $setting->getCredential('password');
        $clientCode = $setting->getCredential('client_code', '');

        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Missing PACER username or password'];
        }

        $response = \Http::post('https://pcl.uscourts.gov/pcl-public-api/rest/loginRequest', [
            'loginId' => $username,
            'password' => $password,
            'clientCode' => $clientCode,
            'redactFlag' => '1',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['loginResult']) && $data['loginResult'] === 'success') {
                return ['success' => true, 'message' => 'Successfully authenticated with PACER'];
            }
            $error = $data['errorDescription'] ?? 'Authentication failed';

            return ['success' => false, 'message' => $error];
        }

        return ['success' => false, 'message' => 'Connection to PACER failed'];
    }

    private function getBaseUrls(string $integration): array
    {
        $urls = [
            'plaid' => [
                'sandbox' => 'https://sandbox.plaid.com',
                'development' => 'https://development.plaid.com',
                'production' => 'https://production.plaid.com',
            ],
            'experian' => [
                'sandbox' => 'https://sandbox-us-api.experian.com',
                'production' => 'https://us-api.experian.com',
            ],
            'persona' => [
                'sandbox' => 'https://withpersona.com/api/v1',
                'production' => 'https://withpersona.com/api/v1',
            ],
            'datamerch' => [
                'sandbox' => 'https://sandbox.datamerch.com/api/v1',
                'production' => 'https://api.datamerch.com/v1',
            ],
            'ucc' => [
                'sandbox' => 'https://api.uccfilings.com',
                'production' => 'https://api.uccfilings.com',
            ],
            'pacer' => [
                'production' => 'https://pcl.uscourts.gov/pcl-public-api/rest',
            ],
        ];

        return $urls[$integration] ?? [];
    }
}
