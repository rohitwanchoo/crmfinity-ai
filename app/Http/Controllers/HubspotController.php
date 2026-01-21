<?php

namespace App\Http\Controllers;

use App\Models\HubspotConnection;
use App\Models\HubspotSyncedOffer;
use App\Models\McaOffer;
use App\Services\HubspotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HubspotController extends Controller
{
    protected HubspotService $hubspotService;

    public function __construct(HubspotService $hubspotService)
    {
        $this->hubspotService = $hubspotService;
    }

    /**
     * Display HubSpot integration settings page.
     */
    public function index()
    {
        $connection = HubspotConnection::where('user_id', Auth::id())
            ->active()
            ->first();

        $syncedOffers = [];
        $portalInfo = null;

        if ($connection) {
            $syncedOffers = HubspotSyncedOffer::where('hubspot_connection_id', $connection->id)
                ->with('mcaOffer')
                ->orderBy('last_synced_at', 'desc')
                ->limit(20)
                ->get();

            try {
                $this->hubspotService->setConnection($connection);
                $portalInfo = $this->hubspotService->getTokenInfo();
            } catch (\Exception $e) {
                Log::warning('Failed to get HubSpot portal info', ['error' => $e->getMessage()]);
            }
        }

        return view('hubspot.index', [
            'connection' => $connection,
            'syncedOffers' => $syncedOffers,
            'portalInfo' => $portalInfo,
        ]);
    }

    /**
     * Initiate OAuth connection to HubSpot.
     */
    public function connect(Request $request)
    {
        $state = Str::random(40);
        session(['hubspot_oauth_state' => $state]);

        $authUrl = $this->hubspotService->getAuthorizationUrl($state);

        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback from HubSpot.
     */
    public function callback(Request $request)
    {
        // Verify state
        $state = session('hubspot_oauth_state');
        if (!$state || $state !== $request->input('state')) {
            return redirect()->route('hubspot.index')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }

        session()->forget('hubspot_oauth_state');

        // Check for errors
        if ($request->has('error')) {
            return redirect()->route('hubspot.index')
                ->with('error', 'HubSpot authorization failed: ' . $request->input('error_description', 'Unknown error'));
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect()->route('hubspot.index')
                ->with('error', 'No authorization code received.');
        }

        try {
            // Exchange code for tokens
            $tokens = $this->hubspotService->exchangeCodeForTokens($code);

            // Get token info to get portal ID
            $tokenInfo = $this->hubspotService->setConnection(
                new HubspotConnection([
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                ])
            )->getTokenInfo();

            // Create or update connection
            $connection = HubspotConnection::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'hubspot_portal_id' => $tokenInfo['hub_id'] ?? null,
                ],
                [
                    'hubspot_user_id' => $tokenInfo['user_id'] ?? null,
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                    'scopes' => explode(' ', $tokenInfo['scopes'] ?? ''),
                    'is_active' => true,
                ]
            );

            // Ensure custom properties exist
            $this->hubspotService->setConnection($connection);
            $createdProperties = $this->hubspotService->ensureCustomPropertiesExist();

            $message = 'Successfully connected to HubSpot!';
            if (!empty($createdProperties)) {
                $message .= ' Created ' . count($createdProperties) . ' custom properties.';
            }

            return redirect()->route('hubspot.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('HubSpot OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('hubspot.index')
                ->with('error', 'Failed to connect to HubSpot: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect from HubSpot.
     */
    public function disconnect(Request $request)
    {
        $connection = HubspotConnection::where('user_id', Auth::id())
            ->active()
            ->first();

        if ($connection) {
            $connection->update(['is_active' => false]);
        }

        return redirect()->route('hubspot.index')
            ->with('success', 'Disconnected from HubSpot.');
    }

    /**
     * Sync an MCA offer to HubSpot.
     */
    public function syncOffer(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|string',
            'contact_id' => 'nullable|string',
            'company_id' => 'nullable|string',
        ]);

        $connection = HubspotConnection::where('user_id', Auth::id())
            ->active()
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'No active HubSpot connection. Please connect first.',
            ], 400);
        }

        $offer = McaOffer::where('offer_id', $request->offer_id)->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'MCA offer not found.',
            ], 404);
        }

        try {
            $this->hubspotService->setConnection($connection);
            $syncedOffer = $this->hubspotService->syncMcaOfferToDeal(
                $offer,
                $request->contact_id,
                $request->company_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Offer synced to HubSpot successfully.',
                'data' => [
                    'deal_id' => $syncedOffer->hubspot_deal_id,
                    'sync_status' => $syncedOffer->sync_status,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync offer: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get contacts from HubSpot for selection.
     */
    public function getContacts(Request $request)
    {
        $connection = HubspotConnection::where('user_id', Auth::id())
            ->active()
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'No active HubSpot connection.',
            ], 400);
        }

        try {
            $this->hubspotService->setConnection($connection);

            $search = $request->input('search');
            $properties = ['firstname', 'lastname', 'email', 'company'];

            if ($search) {
                $contacts = $this->hubspotService->searchContacts([
                    [
                        'propertyName' => 'email',
                        'operator' => 'CONTAINS_TOKEN',
                        'value' => $search,
                    ],
                ], $properties, 20);
            } else {
                $contacts = $this->hubspotService->getContacts(20, null, $properties);
            }

            $results = [];
            foreach ($contacts['results'] ?? [] as $contact) {
                $props = $contact['properties'] ?? [];
                $results[] = [
                    'id' => $contact['id'],
                    'name' => trim(($props['firstname'] ?? '') . ' ' . ($props['lastname'] ?? '')),
                    'email' => $props['email'] ?? '',
                    'company' => $props['company'] ?? '',
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contacts: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get companies from HubSpot for selection.
     */
    public function getCompanies(Request $request)
    {
        $connection = HubspotConnection::where('user_id', Auth::id())
            ->active()
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'No active HubSpot connection.',
            ], 400);
        }

        try {
            $this->hubspotService->setConnection($connection);

            $search = $request->input('search');
            $properties = ['name', 'domain', 'industry'];

            if ($search) {
                $companies = $this->hubspotService->searchCompanies([
                    [
                        'propertyName' => 'name',
                        'operator' => 'CONTAINS_TOKEN',
                        'value' => $search,
                    ],
                ], $properties, 20);
            } else {
                // Get recent companies
                $response = $this->hubspotService->apiRequest('get', '/crm/v3/objects/companies?limit=20&properties=' . implode(',', $properties));
                $companies = $response;
            }

            $results = [];
            foreach ($companies['results'] ?? [] as $company) {
                $props = $company['properties'] ?? [];
                $results[] = [
                    'id' => $company['id'],
                    'name' => $props['name'] ?? 'Unknown',
                    'domain' => $props['domain'] ?? '',
                    'industry' => $props['industry'] ?? '',
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch companies: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh connection / re-sync properties.
     */
    public function refresh(Request $request)
    {
        $connection = HubspotConnection::where('user_id', Auth::id())
            ->active()
            ->first();

        if (!$connection) {
            return redirect()->route('hubspot.index')
                ->with('error', 'No active HubSpot connection.');
        }

        try {
            $this->hubspotService->setConnection($connection);
            $this->hubspotService->refreshAccessToken($connection);
            $createdProperties = $this->hubspotService->ensureCustomPropertiesExist();

            $connection->update(['last_synced_at' => now()]);

            $message = 'Connection refreshed successfully.';
            if (!empty($createdProperties)) {
                $message .= ' Created ' . count($createdProperties) . ' new properties.';
            }

            return redirect()->route('hubspot.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->route('hubspot.index')
                ->with('error', 'Failed to refresh connection: ' . $e->getMessage());
        }
    }

    /**
     * Display calculator in HubSpot iframe.
     */
    public function calculator(Request $request)
    {
        $offerId = $request->input('offer_id');
        $offer = null;

        if ($offerId) {
            $offer = McaOffer::where('offer_id', $offerId)->first();
        }

        return view('hubspot.calculator', [
            'offer' => $offer,
        ]);
    }
}
