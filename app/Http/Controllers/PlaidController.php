<?php

namespace App\Http\Controllers;

use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Models\PlaidAccount;
use App\Models\PlaidItem;
use App\Models\PlaidTransaction;
use App\Services\PlaidService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlaidController extends Controller
{
    protected PlaidService $plaidService;

    public function __construct(PlaidService $plaidService)
    {
        $this->plaidService = $plaidService;
    }

    /**
     * Show the Plaid integration page
     */
    public function index()
    {
        $linkedAccounts = PlaidItem::where('user_id', Auth::id())
            ->with('accounts')
            ->get();

        return view('plaid.index', compact('linkedAccounts'));
    }

    /**
     * Create a link token for Plaid Link
     */
    public function createLinkToken(Request $request): JsonResponse
    {
        try {
            $accessToken = null;

            // Check if this is for update mode
            if ($request->has('item_id')) {
                $item = PlaidItem::where('id', $request->item_id)
                    ->where('user_id', Auth::id())
                    ->first();

                if ($item) {
                    $accessToken = $item->access_token;
                }
            }

            $response = $this->plaidService->createLinkToken(Auth::id(), $accessToken);

            if (isset($response['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $response['display_message'] ?? 'Failed to create link token',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'link_token' => $response['link_token'],
                'expiration' => $response['expiration'],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create Plaid link token', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize bank connection',
            ], 500);
        }
    }

    /**
     * Exchange public token and store access token
     */
    public function exchangeToken(Request $request): JsonResponse
    {
        $request->validate([
            'public_token' => 'required|string',
            'metadata' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $response = $this->plaidService->exchangePublicToken($request->public_token);

            if (isset($response['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $response['display_message'] ?? 'Failed to connect bank',
                ], 400);
            }

            $accessToken = $response['access_token'];
            $itemId = $response['item_id'];

            // Get institution details
            $institutionId = $request->metadata['institution']['institution_id'] ?? null;
            $institutionName = $request->metadata['institution']['name'] ?? 'Unknown Bank';

            // Store the Plaid item
            $plaidItem = PlaidItem::updateOrCreate(
                ['plaid_item_id' => $itemId],
                [
                    'user_id' => Auth::id(),
                    'access_token' => encrypt($accessToken),
                    'institution_id' => $institutionId,
                    'institution_name' => $institutionName,
                    'status' => 'active',
                    'consent_expiration_time' => now()->addDays(90),
                ]
            );

            // Get and store accounts
            $accountsResponse = $this->plaidService->getAccounts($accessToken);

            if (! isset($accountsResponse['error'])) {
                foreach ($accountsResponse['accounts'] as $account) {
                    PlaidAccount::updateOrCreate(
                        ['plaid_account_id' => $account['account_id']],
                        [
                            'plaid_item_id' => $plaidItem->id,
                            'name' => $account['name'],
                            'official_name' => $account['official_name'],
                            'type' => $account['type'],
                            'subtype' => $account['subtype'],
                            'mask' => $account['mask'],
                            'current_balance' => $account['balances']['current'],
                            'available_balance' => $account['balances']['available'],
                            'iso_currency_code' => $account['balances']['iso_currency_code'] ?? 'USD',
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bank account connected successfully',
                'item_id' => $plaidItem->id,
                'institution_name' => $institutionName,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to exchange Plaid token', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect bank account',
            ], 500);
        }
    }

    /**
     * Sync transactions from Plaid
     */
    public function syncTransactions(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:plaid_items,id',
        ]);

        try {
            $plaidItem = PlaidItem::where('id', $request->item_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $accessToken = decrypt($plaidItem->access_token);

            // Sync transactions
            $response = $this->plaidService->getAllTransactions(
                $accessToken,
                $plaidItem->transaction_cursor
            );

            if (isset($response['error'])) {
                // Handle item error (e.g., needs re-authentication)
                if ($response['error_code'] === 'ITEM_LOGIN_REQUIRED') {
                    $plaidItem->update(['status' => 'login_required']);

                    return response()->json([
                        'success' => false,
                        'requires_reauth' => true,
                        'message' => 'Please re-authenticate your bank connection',
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => $response['display_message'] ?? 'Failed to sync transactions',
                ], 400);
            }

            // Store transactions
            $addedCount = 0;
            $modifiedCount = 0;

            foreach ($response['added'] as $transaction) {
                $formatted = $this->plaidService->formatTransaction($transaction);

                $account = PlaidAccount::where('plaid_account_id', $transaction['account_id'])->first();

                PlaidTransaction::updateOrCreate(
                    ['plaid_transaction_id' => $formatted['plaid_transaction_id']],
                    array_merge($formatted, ['plaid_account_id' => $account?->id])
                );
                $addedCount++;
            }

            foreach ($response['modified'] as $transaction) {
                $formatted = $this->plaidService->formatTransaction($transaction);

                PlaidTransaction::where('plaid_transaction_id', $formatted['plaid_transaction_id'])
                    ->update($formatted);
                $modifiedCount++;
            }

            // Handle removed transactions
            foreach ($response['removed'] as $removed) {
                PlaidTransaction::where('plaid_transaction_id', $removed['transaction_id'])
                    ->delete();
            }

            // Update cursor and sync time
            $plaidItem->update([
                'transaction_cursor' => $response['cursor'],
                'last_synced_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Synced {$addedCount} new transactions",
                'added' => $addedCount,
                'modified' => $modifiedCount,
                'removed' => count($response['removed']),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to sync Plaid transactions', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync transactions',
            ], 500);
        }
    }

    /**
     * Get accounts for a linked item
     */
    public function getAccounts(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:plaid_items,id',
        ]);

        try {
            $plaidItem = PlaidItem::where('id', $request->item_id)
                ->where('user_id', Auth::id())
                ->with('accounts')
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'accounts' => $plaidItem->accounts,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get accounts',
            ], 500);
        }
    }

    /**
     * Get transactions for analysis
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|exists:plaid_accounts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        try {
            $account = PlaidAccount::where('id', $request->account_id)
                ->whereHas('plaidItem', function ($q) {
                    $q->where('user_id', Auth::id());
                })
                ->firstOrFail();

            $query = PlaidTransaction::where('plaid_account_id', $account->id)
                ->orderBy('transaction_date', 'desc');

            if ($request->start_date) {
                $query->where('transaction_date', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $query->where('transaction_date', '<=', $request->end_date);
            }

            $transactions = $query->get();

            return response()->json([
                'success' => true,
                'transactions' => $transactions,
                'count' => $transactions->count(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get transactions',
            ], 500);
        }
    }

    /**
     * Analyze Plaid transactions (integrate with SmartMCA)
     */
    public function analyzeTransactions(Request $request)
    {
        $request->validate([
            'account_ids' => 'required|array',
            'account_ids.*' => 'exists:plaid_accounts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            // Get transactions from selected accounts
            $transactions = PlaidTransaction::whereIn('plaid_account_id', $request->account_ids)
                ->whereHas('plaidAccount.plaidItem', function ($q) {
                    $q->where('user_id', Auth::id());
                })
                ->when($request->start_date, fn ($q) => $q->where('transaction_date', '>=', $request->start_date))
                ->when($request->end_date, fn ($q) => $q->where('transaction_date', '<=', $request->end_date))
                ->orderBy('transaction_date', 'asc')
                ->get();

            if ($transactions->isEmpty()) {
                return back()->with('error', 'No transactions found for the selected criteria');
            }

            // Get institution name for the session
            $firstAccount = PlaidAccount::find($request->account_ids[0]);
            $institutionName = $firstAccount->plaidItem->institution_name ?? 'Plaid';

            // Create analysis session
            $sessionId = 'plaid_'.Str::random(10);

            $analysisSession = AnalysisSession::create([
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'filename' => "Plaid - {$institutionName}",
                'pages' => 1,
                'total_transactions' => $transactions->count(),
                'total_credits' => 0,
                'total_debits' => 0,
                'net_flow' => 0,
                'processing_status' => 'processing',
                'source_type' => 'plaid',
            ]);

            $totalCredits = 0;
            $totalDebits = 0;

            // Convert Plaid transactions to AnalyzedTransactions
            foreach ($transactions as $plaidTxn) {
                AnalyzedTransaction::create([
                    'analysis_session_id' => $analysisSession->id,
                    'transaction_date' => $plaidTxn->transaction_date,
                    'description' => $plaidTxn->description,
                    'description_normalized' => strtolower(trim($plaidTxn->description)),
                    'amount' => $plaidTxn->amount,
                    'type' => $plaidTxn->type,
                    'original_type' => $plaidTxn->type,
                    'was_corrected' => false,
                    'confidence' => 0.95, // High confidence for Plaid data
                    'confidence_label' => 'High',
                    'category' => $plaidTxn->category,
                    'merchant_name' => $plaidTxn->merchant_name,
                    'plaid_transaction_id' => $plaidTxn->plaid_transaction_id,
                ]);

                if ($plaidTxn->type === 'credit') {
                    $totalCredits += $plaidTxn->amount;
                } else {
                    $totalDebits += $plaidTxn->amount;
                }
            }

            // Update session totals
            $analysisSession->update([
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'net_flow' => $totalCredits - $totalDebits,
                'high_confidence_count' => $transactions->count(),
                'processing_status' => 'completed',
            ]);

            DB::commit();

            // Redirect to SmartMCA results page
            return redirect()->route('smartmca.session', ['sessionId' => $sessionId])
                ->with('success', "Analyzed {$transactions->count()} transactions from Plaid");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to analyze Plaid transactions', ['error' => $e->getMessage()]);

            return back()->with('error', 'Failed to analyze transactions');
        }
    }

    /**
     * Disconnect a bank account
     */
    public function disconnect(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:plaid_items,id',
        ]);

        try {
            $plaidItem = PlaidItem::where('id', $request->item_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $accessToken = decrypt($plaidItem->access_token);

            // Remove from Plaid
            $this->plaidService->removeItem($accessToken);

            // Delete from our database
            $plaidItem->accounts()->delete();
            $plaidItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bank account disconnected successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to disconnect Plaid item', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect bank account',
            ], 500);
        }
    }

    /**
     * Handle Plaid webhooks
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Plaid webhook received', ['type' => $payload['webhook_type'] ?? 'unknown']);

        try {
            $webhookType = $payload['webhook_type'] ?? '';
            $webhookCode = $payload['webhook_code'] ?? '';
            $itemId = $payload['item_id'] ?? '';

            $plaidItem = PlaidItem::where('plaid_item_id', $itemId)->first();

            if (! $plaidItem) {
                Log::warning('Plaid webhook for unknown item', ['item_id' => $itemId]);

                return response()->json(['received' => true]);
            }

            switch ($webhookType) {
                case 'TRANSACTIONS':
                    $this->handleTransactionsWebhook($webhookCode, $plaidItem, $payload);
                    break;

                case 'ITEM':
                    $this->handleItemWebhook($webhookCode, $plaidItem, $payload);
                    break;

                case 'AUTH':
                    $this->handleAuthWebhook($webhookCode, $plaidItem, $payload);
                    break;
            }

            return response()->json(['received' => true]);
        } catch (Exception $e) {
            Log::error('Plaid webhook processing failed', ['error' => $e->getMessage()]);

            return response()->json(['received' => true]);
        }
    }

    /**
     * Handle transactions webhooks
     */
    protected function handleTransactionsWebhook(string $code, PlaidItem $item, array $payload): void
    {
        switch ($code) {
            case 'SYNC_UPDATES_AVAILABLE':
            case 'INITIAL_UPDATE':
            case 'HISTORICAL_UPDATE':
                // Queue a job to sync transactions
                // For now, just mark that new transactions are available
                $item->update(['has_pending_sync' => true]);
                break;

            case 'TRANSACTIONS_REMOVED':
                // Handle removed transactions
                $removedIds = $payload['removed_transactions'] ?? [];
                PlaidTransaction::whereIn('plaid_transaction_id', $removedIds)->delete();
                break;
        }
    }

    /**
     * Handle item webhooks
     */
    protected function handleItemWebhook(string $code, PlaidItem $item, array $payload): void
    {
        switch ($code) {
            case 'ERROR':
                $item->update([
                    'status' => 'error',
                    'error_code' => $payload['error']['error_code'] ?? null,
                    'error_message' => $payload['error']['error_message'] ?? null,
                ]);
                break;

            case 'LOGIN_REPAIRED':
                $item->update([
                    'status' => 'active',
                    'error_code' => null,
                    'error_message' => null,
                ]);
                break;

            case 'PENDING_EXPIRATION':
                $item->update(['status' => 'pending_expiration']);
                break;

            case 'USER_PERMISSION_REVOKED':
                $item->update(['status' => 'revoked']);
                break;
        }
    }

    /**
     * Handle auth webhooks
     */
    protected function handleAuthWebhook(string $code, PlaidItem $item, array $payload): void
    {
        if ($code === 'AUTOMATICALLY_VERIFIED') {
            $item->update(['auth_verified' => true]);
        }
    }

    /**
     * Create sandbox test account (development only)
     */
    public function createSandboxAccount(): JsonResponse
    {
        if (config('plaid.env') !== 'sandbox') {
            return response()->json([
                'success' => false,
                'message' => 'Sandbox only endpoint',
            ], 403);
        }

        try {
            $response = $this->plaidService->createSandboxPublicToken();

            if (isset($response['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $response['display_message'] ?? 'Failed to create sandbox token',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'public_token' => $response['public_token'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sandbox account',
            ], 500);
        }
    }
}
