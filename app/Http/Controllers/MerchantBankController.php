<?php

namespace App\Http\Controllers;

use App\Models\BankLinkRequest;
use App\Models\PlaidAccount;
use App\Models\PlaidItem;
use App\Services\PlaidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MerchantBankController extends Controller
{
    protected PlaidService $plaidService;

    public function __construct(PlaidService $plaidService)
    {
        $this->plaidService = $plaidService;
    }

    /**
     * Show the bank connection page for merchants
     */
    public function show(string $token)
    {
        $linkRequest = BankLinkRequest::where('token', $token)->first();

        if (! $linkRequest) {
            return view('merchant.bank.invalid', [
                'message' => 'This link is invalid or has been removed.',
            ]);
        }

        if ($linkRequest->is_expired) {
            $linkRequest->markAsExpired();

            return view('merchant.bank.expired', [
                'linkRequest' => $linkRequest,
            ]);
        }

        if ($linkRequest->status === 'completed') {
            return view('merchant.bank.completed', [
                'linkRequest' => $linkRequest,
            ]);
        }

        // Mark as opened if first time viewing
        $linkRequest->markAsOpened();

        // Check if Plaid is configured
        if (! $this->plaidService->isEnabled()) {
            return view('merchant.bank.unavailable', [
                'message' => 'Bank connection service is currently unavailable. Please contact support.',
            ]);
        }

        return view('merchant.bank.connect', [
            'linkRequest' => $linkRequest,
        ]);
    }

    /**
     * Create a Plaid Link token for the merchant
     */
    public function createLinkToken(string $token)
    {
        $linkRequest = BankLinkRequest::where('token', $token)->first();

        if (! $linkRequest || ! $linkRequest->is_valid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired link request.',
            ], 400);
        }

        try {
            // Use application ID as the user ID for Plaid
            $result = $this->plaidService->createLinkToken($linkRequest->application_id);

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error_message'] ?? 'Failed to create link token.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'link_token' => $result['link_token'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create Plaid link token for merchant', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize bank connection. Please try again.',
            ], 500);
        }
    }

    /**
     * Exchange public token and save the connected bank
     */
    public function exchangeToken(Request $request, string $token)
    {
        $linkRequest = BankLinkRequest::where('token', $token)->first();

        if (! $linkRequest || ! $linkRequest->is_valid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired link request.',
            ], 400);
        }

        $validated = $request->validate([
            'public_token' => 'required|string',
            'institution' => 'required|array',
            'accounts' => 'required|array',
        ]);

        try {
            // Exchange public token for access token
            $result = $this->plaidService->exchangePublicToken($validated['public_token']);

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error_message'] ?? 'Failed to connect bank account.',
                ], 500);
            }

            $accessToken = $result['access_token'];
            $itemId = $result['item_id'];

            // Create PlaidItem for the application
            $plaidItem = PlaidItem::create([
                'user_id' => $linkRequest->application->user_id,
                'application_id' => $linkRequest->application_id,
                'plaid_item_id' => $itemId,
                'access_token' => encrypt($accessToken),
                'institution_id' => $validated['institution']['institution_id'] ?? null,
                'institution_name' => $validated['institution']['name'] ?? 'Unknown Bank',
                'status' => 'active',
            ]);

            // Fetch and store account details
            $accountsResult = $this->plaidService->getAccounts($accessToken);
            $connectedAccounts = [];

            if (! isset($accountsResult['error']) && isset($accountsResult['accounts'])) {
                foreach ($accountsResult['accounts'] as $account) {
                    PlaidAccount::create([
                        'plaid_item_id' => $plaidItem->id,
                        'plaid_account_id' => $account['account_id'],
                        'name' => $account['name'],
                        'official_name' => $account['official_name'] ?? null,
                        'type' => $account['type'],
                        'subtype' => $account['subtype'] ?? null,
                        'mask' => $account['mask'] ?? null,
                        'current_balance' => $account['balances']['current'] ?? 0,
                        'available_balance' => $account['balances']['available'] ?? null,
                        'currency' => $account['balances']['iso_currency_code'] ?? 'USD',
                    ]);

                    $connectedAccounts[] = [
                        'name' => $account['name'],
                        'mask' => $account['mask'],
                        'type' => $account['type'],
                    ];
                }
            }

            // Mark the link request as completed
            $linkRequest->markAsCompleted(
                $itemId,
                $validated['institution']['name'] ?? 'Unknown Bank',
                $connectedAccounts
            );

            // Add note to application
            $linkRequest->application->addNote(
                "Bank account connected via Plaid: {$plaidItem->institution_name} ({$plaidItem->accounts->count()} account(s))",
                'bank_connected'
            );

            return response()->json([
                'success' => true,
                'message' => 'Bank account connected successfully!',
                'institution' => $plaidItem->institution_name,
                'accounts_count' => count($connectedAccounts),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to exchange Plaid token for merchant', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            $linkRequest->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect bank account. Please try again.',
            ], 500);
        }
    }
}
