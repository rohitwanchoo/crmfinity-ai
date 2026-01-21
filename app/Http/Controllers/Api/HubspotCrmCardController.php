<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HubspotConnection;
use App\Models\HubspotSyncedOffer;
use App\Services\HubspotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HubspotCrmCardController extends Controller
{
    protected HubspotService $hubspotService;

    public function __construct(HubspotService $hubspotService)
    {
        $this->hubspotService = $hubspotService;
    }

    /**
     * Handle CRM card data request from HubSpot.
     *
     * This endpoint is called by HubSpot when displaying a CRM card.
     */
    public function fetch(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('HubSpot CRM Card fetch request', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        // Get parameters from HubSpot
        $portalId = $request->input('portalId');
        $objectType = $request->input('objectType'); // CONTACT, COMPANY, DEAL
        $objectId = $request->input('objectId');
        $userId = $request->input('userId');
        $userEmail = $request->input('userEmail');

        // Find the connection for this portal
        $connection = HubspotConnection::where('hubspot_portal_id', $portalId)
            ->active()
            ->first();

        if (!$connection) {
            return response()->json([
                'results' => [],
                'primaryAction' => [
                    'type' => 'IFRAME',
                    'width' => 890,
                    'height' => 748,
                    'uri' => url('/hubspot/connect-prompt'),
                    'label' => 'Connect MCA Calculator',
                ],
                'secondaryActions' => [],
            ]);
        }

        try {
            $this->hubspotService->setConnection($connection);

            // Get synced offers for this portal
            $syncedOffers = HubspotSyncedOffer::where('hubspot_connection_id', $connection->id)
                ->synced()
                ->with('mcaOffer')
                ->orderBy('last_synced_at', 'desc')
                ->limit(5)
                ->get();

            $results = [];

            foreach ($syncedOffers as $syncedOffer) {
                $offer = $syncedOffer->mcaOffer;
                if (!$offer) continue;

                $fundedAmount = $offer->advance_amount;
                $factorRate = $offer->factor_rate;
                $totalPayback = $fundedAmount * $factorRate;
                $monthlyPayment = $offer->term_months > 0 ? $totalPayback / $offer->term_months : 0;
                $weeklyPayment = $monthlyPayment / 4.33;
                $dailyPayment = $monthlyPayment / 21.67;

                $results[] = [
                    'objectId' => $syncedOffer->hubspot_deal_id ?: $offer->offer_id,
                    'title' => $offer->offer_name ?: ('MCA Offer - ' . substr($offer->offer_id, 0, 8)),
                    'link' => url('/bankstatement/view-analysis?sessions[]=' . $offer->session_uuid),
                    'created' => $offer->created_at->toIso8601String(),
                    'priority' => $offer->is_favorite ? 'HIGH' : 'MEDIUM',
                    'properties' => [
                        [
                            'label' => 'Funded Amount',
                            'dataType' => 'CURRENCY',
                            'value' => number_format($fundedAmount, 2),
                            'currencyCode' => 'USD',
                        ],
                        [
                            'label' => 'Factor Rate',
                            'dataType' => 'NUMERIC',
                            'value' => number_format($factorRate, 2),
                        ],
                        [
                            'label' => 'Total Payback',
                            'dataType' => 'CURRENCY',
                            'value' => number_format($totalPayback, 2),
                            'currencyCode' => 'USD',
                        ],
                        [
                            'label' => 'Monthly Payment',
                            'dataType' => 'CURRENCY',
                            'value' => number_format($monthlyPayment, 2),
                            'currencyCode' => 'USD',
                        ],
                        [
                            'label' => 'Weekly Payment',
                            'dataType' => 'CURRENCY',
                            'value' => number_format($weeklyPayment, 2),
                            'currencyCode' => 'USD',
                        ],
                        [
                            'label' => 'Daily Payment',
                            'dataType' => 'CURRENCY',
                            'value' => number_format($dailyPayment, 2),
                            'currencyCode' => 'USD',
                        ],
                        [
                            'label' => 'Term',
                            'dataType' => 'STRING',
                            'value' => $offer->term_months . ' months',
                        ],
                        [
                            'label' => 'True Revenue',
                            'dataType' => 'CURRENCY',
                            'value' => number_format($offer->true_revenue_monthly, 2),
                            'currencyCode' => 'USD',
                        ],
                    ],
                    'actions' => [
                        [
                            'type' => 'IFRAME',
                            'width' => 890,
                            'height' => 748,
                            'uri' => url('/hubspot/calculator?offer_id=' . $offer->offer_id),
                            'label' => 'View Details',
                        ],
                    ],
                ];
            }

            return response()->json([
                'results' => $results,
                'primaryAction' => [
                    'type' => 'IFRAME',
                    'width' => 890,
                    'height' => 748,
                    'uri' => url('/hubspot/calculator'),
                    'label' => 'New MCA Calculation',
                ],
                'secondaryActions' => [
                    [
                        'type' => 'IFRAME',
                        'width' => 400,
                        'height' => 400,
                        'uri' => url('/hubspot/sync-modal?object_type=' . $objectType . '&object_id=' . $objectId),
                        'label' => 'Sync Existing Offer',
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('HubSpot CRM Card fetch error', [
                'error' => $e->getMessage(),
                'portal_id' => $portalId,
            ]);

            return response()->json([
                'results' => [],
                'message' => 'Error loading MCA offers',
            ]);
        }
    }

    /**
     * Verify request signature from HubSpot (for security).
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-HubSpot-Signature');
        if (!$signature) {
            return false;
        }

        $clientSecret = config('hubspot.client_secret');
        $requestBody = $request->getContent();

        $sourceString = $clientSecret . $requestBody;
        $expectedSignature = hash('sha256', $sourceString);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle webhook events from HubSpot.
     */
    public function webhook(Request $request)
    {
        Log::info('HubSpot webhook received', [
            'payload' => $request->all(),
        ]);

        // Verify signature in production
        // if (!$this->verifySignature($request)) {
        //     return response()->json(['error' => 'Invalid signature'], 401);
        // }

        $events = $request->all();

        foreach ($events as $event) {
            try {
                $this->processWebhookEvent($event);
            } catch (\Exception $e) {
                Log::error('Failed to process HubSpot webhook event', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process a single webhook event.
     */
    protected function processWebhookEvent(array $event): void
    {
        $eventType = $event['subscriptionType'] ?? '';
        $objectType = $event['objectType'] ?? '';
        $objectId = $event['objectId'] ?? '';
        $portalId = $event['portalId'] ?? '';

        // Log the event
        \App\Models\HubspotWebhookLog::create([
            'event_type' => $eventType,
            'object_type' => $objectType,
            'object_id' => (string) $objectId,
            'portal_id' => (string) $portalId,
            'payload' => $event,
            'status' => 'received',
        ]);

        // Handle specific events
        switch ($eventType) {
            case 'deal.propertyChange':
                // Handle deal updates (e.g., stage changes)
                break;

            case 'deal.deletion':
                // Handle deal deletion - mark synced offer as deleted
                HubspotSyncedOffer::where('hubspot_deal_id', (string) $objectId)
                    ->delete();
                break;
        }
    }
}
