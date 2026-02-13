<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBankStatement;
use App\Models\AnalysisSession;
use App\Models\AnalyzedTransaction;
use App\Models\LearnedTransactionPattern;
use App\Models\McaPattern;
use App\Models\RevenueClassification;
use App\Models\TransactionCorrection;
use App\Services\TrueRevenueEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Bank Statement Analyzer API',
    description: 'API for analyzing bank statements using AI-powered transaction extraction and classification. Supports PDF bank statement uploads, transaction analysis, MCA (Merchant Cash Advance) detection, and revenue classification.',
    contact: new OA\Contact(
        name: 'CRMFinity Support',
        email: 'support@crmfinity.com'
    )
)]
#[OA\Server(
    url: '/api/v1',
    description: 'API Server v1'
)]
#[OA\Tag(
    name: 'Bank Statement Analysis',
    description: 'Endpoints for uploading and analyzing bank statements'
)]
#[OA\Tag(
    name: 'Sessions',
    description: 'Endpoints for managing analysis sessions'
)]
#[OA\Tag(
    name: 'Transactions',
    description: 'Endpoints for transaction corrections and classifications'
)]
#[OA\Tag(
    name: 'Reference Data',
    description: 'Endpoints for reference data like MCA lenders'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctumAuth',
    type: 'oauth2',
    description: 'Login with email and password to get access token',
    flows: [
        new OA\Flow(
            flow: 'password',
            tokenUrl: '/api/v1/auth/login',
            scopes: []
        )
    ]
)]
class BankStatementApiController extends Controller
{
    /**
     * Analyze uploaded bank statement(s).
     */
    #[OA\Post(
        path: '/bank-statement/analyze',
        summary: 'Analyze bank statement PDF(s) asynchronously',
        description: 'Upload one or more PDF bank statements for AI-powered analysis. Files are queued for parallel processing. Returns session IDs immediately. Use the sessions endpoint to retrieve results once processing completes.',
        tags: ['Bank Statement Analysis'],
        security: [['sanctumAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['statements'],
                    properties: [
                        new OA\Property(
                            property: 'statements',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'PDF bank statement file(s) to analyze (max 20MB each)'
                        ),
                        new OA\Property(
                            property: 'model',
                            type: 'string',
                            enum: ['claude-opus-4-6', 'claude-sonnet-4-5', 'claude-haiku-4-5'],
                            default: 'claude-haiku-4-5',
                            description: 'Claude model to use for analysis'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Files queued for processing successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: '2 statements queued for parallel processing'),
                        new OA\Property(property: 'batch_id', type: 'string', example: 'BATCH-ABC123DEF4567890'),
                        new OA\Property(
                            property: 'sessions',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'session_id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'filename', type: 'string'),
                                    new OA\Property(property: 'status', type: 'string', example: 'queued')
                                ],
                                type: 'object'
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function analyze(Request $request): JsonResponse
    {
        // Handle both single file and array of files (Swagger sends single file)
        $statements = $request->file('statements');

        // Normalize to array - handle single file upload from Swagger
        if ($statements === null) {
            return response()->json([
                'success' => false,
                'error' => 'No files uploaded. Please upload at least one PDF file.',
            ], 422);
        }

        if (!is_array($statements)) {
            $statements = [$statements];
        }

        // Validate each file manually since we normalized the input
        foreach ($statements as $index => $file) {
            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'error' => "File upload error for file {$index}",
                ], 422);
            }
            if ($file->getClientOriginalExtension() !== 'pdf' && $file->getMimeType() !== 'application/pdf') {
                return response()->json([
                    'success' => false,
                    'error' => 'Only PDF files are allowed.',
                ], 422);
            }
            if ($file->getSize() > 20480 * 1024) {
                return response()->json([
                    'success' => false,
                    'error' => 'File size exceeds 20MB limit.',
                ], 422);
            }
        }

        $request->validate([
            'model' => 'nullable|in:claude-opus-4-6,claude-sonnet-4-5,claude-haiku-4-5',
        ]);

        $defaultModel = config('services.anthropic.default_model', 'claude-haiku-4-5');
        $model = $request->input('model', $defaultModel);
        $apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'Anthropic API key not configured. Please add ANTHROPIC_API_KEY to your .env file.',
            ], 500);
        }

        // Generate a unique batch ID for all statements uploaded together
        $batchId = 'BATCH-' . strtoupper(Str::random(16));

        $uploadPath = storage_path('app/uploads');
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $jobsDispatched = [];

        // Save files and dispatch jobs for parallel processing
        foreach ($statements as $file) {
            $filename = $file->getClientOriginalName();
            $sessionId = Str::uuid()->toString();
            $savedPath = $uploadPath . '/' . $sessionId . '_' . $filename;

            // Save the file
            $file->move($uploadPath, $sessionId . '_' . $filename);

            // Dispatch job to queue for parallel processing
            ProcessBankStatement::dispatch(
                $sessionId,
                $batchId,
                $filename,
                $savedPath,
                $model,
                $apiKey,
                auth()->id()
            );

            $jobsDispatched[] = [
                'session_id' => $sessionId,
                'filename' => $filename,
                'status' => 'queued',
            ];

            Log::info("API: Job dispatched to queue", [
                'session_id' => $sessionId,
                'filename' => $filename,
                'batch_id' => $batchId,
            ]);
        }

        $count = count($jobsDispatched);
        $message = $count === 1
            ? "1 statement queued for processing"
            : "{$count} statements queued for parallel processing";

        return response()->json([
            'success' => true,
            'message' => $message,
            'batch_id' => $batchId,
            'sessions' => $jobsDispatched,
        ], 202);
    }

    /**
     * Get all analysis sessions.
     */
    #[OA\Get(
        path: '/bank-statement/sessions',
        summary: 'Get all analysis sessions',
        description: 'Retrieve a paginated list of all bank statement analysis sessions.',
        tags: ['Sessions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Page number',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Items per page',
                schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sessions retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/SessionSummary')
                        ),
                        new OA\Property(
                            property: 'pagination',
                            ref: '#/components/schemas/Pagination'
                        )
                    ]
                )
            )
        ]
    )]
    public function getSessions(Request $request): JsonResponse
    {
        $perPage = min($request->input('per_page', 20), 100);

        $sessions = AnalysisSession::where('analysis_type', 'openai')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sessions->items(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Get a specific session.
     */
    #[OA\Get(
        path: '/bank-statement/sessions/{sessionId}',
        summary: 'Get session details',
        description: 'Retrieve detailed information about a specific analysis session.',
        tags: ['Sessions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sessionId',
                in: 'path',
                required: true,
                description: 'Session UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Session retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SessionDetail')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Session not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function getSession(string $sessionId): JsonResponse
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('analysis_type', 'openai')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    /**
     * Get transactions for a session.
     */
    #[OA\Get(
        path: '/bank-statement/sessions/{sessionId}/transactions',
        summary: 'Get session transactions',
        description: 'Retrieve all transactions from a specific analysis session.',
        tags: ['Sessions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sessionId',
                in: 'path',
                required: true,
                description: 'Session UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'type',
                in: 'query',
                description: 'Filter by transaction type',
                schema: new OA\Schema(type: 'string', enum: ['credit', 'debit'])
            ),
            new OA\Parameter(
                name: 'date_from',
                in: 'query',
                description: 'Filter transactions from this date',
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'date_to',
                in: 'query',
                description: 'Filter transactions up to this date',
                schema: new OA\Schema(type: 'string', format: 'date')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transactions retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Transaction')
                        ),
                        new OA\Property(
                            property: 'summary',
                            ref: '#/components/schemas/TransactionSummary'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Session not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function getTransactions(Request $request, string $sessionId): JsonResponse
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('analysis_type', 'openai')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        $query = $session->transactions()->orderBy('transaction_date');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $transactions = $query->get();
        $credits = $transactions->where('type', 'credit');
        $debits = $transactions->where('type', 'debit');

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'summary' => [
                'total_transactions' => $transactions->count(),
                'credit_count' => $credits->count(),
                'debit_count' => $debits->count(),
                'total_credits' => $credits->sum('amount'),
                'total_debits' => $debits->sum('amount'),
                'net_flow' => $credits->sum('amount') - $debits->sum('amount'),
            ],
        ]);
    }

    /**
     * Get session summary.
     */
    #[OA\Get(
        path: '/bank-statement/sessions/{sessionId}/summary',
        summary: 'Get session summary',
        description: 'Retrieve a summary of the analysis session including totals and statistics.',
        tags: ['Sessions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sessionId',
                in: 'path',
                required: true,
                description: 'Session UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Summary retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SessionSummaryDetail')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Session not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function getSummary(string $sessionId): JsonResponse
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('analysis_type', 'openai')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        $transactions = $session->transactions;
        $credits = $transactions->where('type', 'credit');
        $debits = $transactions->where('type', 'debit');

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_id,
                'filename' => $session->filename,
                'analyzed_at' => $session->created_at,
                'total_transactions' => $transactions->count(),
                'credit_count' => $credits->count(),
                'debit_count' => $debits->count(),
                'total_credits' => (float) $credits->sum('amount'),
                'total_debits' => (float) $debits->sum('amount'),
                'net_flow' => (float) $session->net_flow,
                'api_cost' => (float) $session->api_cost,
                'model_used' => $session->model_used,
                'pages' => $session->pages,
            ],
        ]);
    }

    /**
     * Get monthly breakdown data.
     */
    #[OA\Get(
        path: '/bank-statement/sessions/{sessionId}/monthly',
        summary: 'Get monthly breakdown',
        description: 'Retrieve monthly breakdown of transactions including deposits, adjustments, and true revenue calculations.',
        tags: ['Sessions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sessionId',
                in: 'path',
                required: true,
                description: 'Session UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Monthly data retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/MonthlyBreakdown')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Session not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function getMonthlyData(string $sessionId): JsonResponse
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('analysis_type', 'openai')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        $transactions = $session->transactions()
            ->orderBy('transaction_date')
            ->get()
            ->map(fn ($txn) => [
                'date' => $txn->transaction_date,
                'description' => $txn->description,
                'amount' => (float) $txn->amount,
                'type' => $txn->type,
            ])
            ->toArray();

        $monthlyData = $this->groupTransactionsByMonth($transactions);

        return response()->json([
            'success' => true,
            'data' => $monthlyData,
        ]);
    }

    /**
     * Get MCA analysis for a session.
     */
    #[OA\Get(
        path: '/bank-statement/sessions/{sessionId}/mca-analysis',
        summary: 'Get MCA analysis',
        description: 'Retrieve Merchant Cash Advance (MCA) payment analysis for a session.',
        tags: ['Sessions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sessionId',
                in: 'path',
                required: true,
                description: 'Session UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'MCA analysis retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/McaAnalysis')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Session not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function getMcaAnalysis(string $sessionId): JsonResponse
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('analysis_type', 'openai')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        $transactions = $session->transactions()
            ->orderBy('transaction_date')
            ->get()
            ->map(fn ($txn) => [
                'date' => $txn->transaction_date,
                'description' => $txn->description,
                'amount' => (float) $txn->amount,
                'type' => $txn->type,
            ])
            ->toArray();

        $mcaAnalysis = $this->detectMcaPayments($transactions);

        return response()->json([
            'success' => true,
            'data' => $mcaAnalysis,
        ]);
    }

    /**
     * Download transactions as CSV.
     */
    #[OA\Get(
        path: '/bank-statement/sessions/{sessionId}/download',
        summary: 'Download transactions as CSV',
        description: 'Download all transactions from a session as a CSV file.',
        tags: ['Sessions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sessionId',
                in: 'path',
                required: true,
                description: 'Session UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'CSV file download',
                content: new OA\MediaType(
                    mediaType: 'text/csv',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Session not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function downloadCsv(string $sessionId)
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('analysis_type', 'openai')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        $transactions = $session->transactions()->orderBy('transaction_date')->get();

        $filename = 'transactions_'.$sessionId.'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Description', 'Amount', 'Type', 'Was Corrected']);

            foreach ($transactions as $txn) {
                fputcsv($file, [
                    $txn->transaction_date,
                    $txn->description,
                    $txn->amount,
                    $txn->type,
                    $txn->was_corrected ? 'Yes' : 'No',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Delete a session.
     */
    #[OA\Delete(
        path: '/bank-statement/sessions/{sessionId}',
        summary: 'Delete a session',
        description: 'Delete an analysis session and all its transactions.',
        tags: ['Sessions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'sessionId',
                in: 'path',
                required: true,
                description: 'Session UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Session deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Session deleted successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Session not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function deleteSession(string $sessionId): JsonResponse
    {
        $session = AnalysisSession::where('session_id', $sessionId)
            ->where('analysis_type', 'openai')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        $session->transactions()->delete();
        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session deleted successfully',
        ]);
    }

    /**
     * Toggle transaction type (credit <-> debit).
     */
    #[OA\Post(
        path: '/bank-statement/transactions/{transactionId}/toggle-type',
        summary: 'Toggle transaction type',
        description: 'Toggle a transaction between credit and debit. This correction is saved for AI learning.',
        tags: ['Transactions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'transactionId',
                in: 'path',
                required: true,
                description: 'Transaction ID',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transaction type toggled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Transaction type updated. AI will learn from this correction.'),
                        new OA\Property(property: 'new_type', type: 'string', enum: ['credit', 'debit']),
                        new OA\Property(
                            property: 'session_totals',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_credits', type: 'string', example: '15,000.00'),
                                new OA\Property(property: 'total_debits', type: 'string', example: '10,000.00'),
                                new OA\Property(property: 'net_flow', type: 'string', example: '5,000.00')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Transaction not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function toggleType(int $transactionId): JsonResponse
    {
        $transaction = AnalyzedTransaction::find($transactionId);

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found',
            ], 404);
        }

        $oldType = $transaction->type;
        $newType = $oldType === 'credit' ? 'debit' : 'credit';

        $transaction->update([
            'type' => $newType,
            'was_corrected' => true,
            'confidence' => 1.0,
            'confidence_label' => 'high',
        ]);

        TransactionCorrection::recordCorrection(
            $transaction->description,
            $oldType,
            $newType,
            $transaction->amount,
            auth()->id()
        );

        // Save manual override to learned patterns (high priority for future auto-correction)
        LearnedTransactionPattern::learnFromTransaction(
            $transaction->description,
            $newType,
            $transaction->amount,
            'manual',
            true, // is manual override
            auth()->id()
        );

        $session = $transaction->session;
        if ($session) {
            $amount = (float) $transaction->amount;

            if ($oldType === 'credit' && $newType === 'debit') {
                $session->total_credits -= $amount;
                $session->total_debits += $amount;
            } else {
                $session->total_debits -= $amount;
                $session->total_credits += $amount;
            }
            $session->net_flow = $session->total_credits - $session->total_debits;
            $session->save();
        }

        Log::info("API: Transaction type changed from {$oldType} to {$newType}", [
            'transaction_id' => $transactionId,
            'description' => $transaction->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction type updated. AI will learn from this correction.',
            'new_type' => $newType,
            'session_totals' => [
                'total_credits' => $session ? number_format($session->total_credits, 2) : 0,
                'total_debits' => $session ? number_format($session->total_debits, 2) : 0,
                'net_flow' => $session ? number_format($session->net_flow, 2) : 0,
            ],
        ]);
    }

    /**
     * Toggle revenue classification.
     */
    #[OA\Post(
        path: '/bank-statement/transactions/{transactionId}/toggle-revenue',
        summary: 'Toggle revenue classification',
        description: 'Toggle a credit transaction between true_revenue and adjustment. This helps AI learn revenue classification.',
        tags: ['Transactions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'transactionId',
                in: 'path',
                required: true,
                description: 'Transaction ID',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_classification'],
                properties: [
                    new OA\Property(
                        property: 'current_classification',
                        type: 'string',
                        enum: ['true_revenue', 'adjustment'],
                        description: 'Current classification to toggle from'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Revenue classification toggled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'new_classification', type: 'string', enum: ['true_revenue', 'adjustment']),
                        new OA\Property(property: 'amount', type: 'number', format: 'float')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Transaction not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function toggleRevenue(Request $request, int $transactionId): JsonResponse
    {
        $request->validate([
            'current_classification' => 'required|in:true_revenue,adjustment',
        ]);

        $transaction = AnalyzedTransaction::find($transactionId);

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found',
            ], 404);
        }

        $newClassification = $request->current_classification === 'true_revenue' ? 'adjustment' : 'true_revenue';

        RevenueClassification::recordClassification(
            $transaction->description,
            $newClassification,
            $newClassification === 'adjustment' ? 'user_marked' : null,
            auth()->id()
        );

        Log::info("API: Revenue classification changed to {$newClassification}", [
            'transaction_id' => $transactionId,
            'description' => $transaction->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Transaction marked as ".str_replace('_', ' ', $newClassification).'. AI will learn from this.',
            'new_classification' => $newClassification,
            'amount' => (float) $transaction->amount,
        ]);
    }

    /**
     * Toggle MCA status.
     */
    #[OA\Post(
        path: '/bank-statement/transactions/{transactionId}/toggle-mca',
        summary: 'Toggle MCA status',
        description: 'Mark or unmark a debit transaction as an MCA (Merchant Cash Advance) payment.',
        tags: ['Transactions'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'transactionId',
                in: 'path',
                required: true,
                description: 'Transaction ID',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['is_mca'],
                properties: [
                    new OA\Property(
                        property: 'is_mca',
                        type: 'boolean',
                        description: 'Whether this is an MCA payment'
                    ),
                    new OA\Property(
                        property: 'lender_id',
                        type: 'string',
                        description: 'MCA lender ID (required if is_mca is true)'
                    ),
                    new OA\Property(
                        property: 'lender_name',
                        type: 'string',
                        description: 'MCA lender name (required if is_mca is true)'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'MCA status toggled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'is_mca', type: 'boolean'),
                        new OA\Property(property: 'lender_id', type: 'string'),
                        new OA\Property(property: 'lender_name', type: 'string'),
                        new OA\Property(property: 'amount', type: 'number', format: 'float')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Transaction not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function toggleMca(Request $request, int $transactionId): JsonResponse
    {
        $request->validate([
            'is_mca' => 'required|boolean',
            'lender_id' => 'required_if:is_mca,true|nullable|string',
            'lender_name' => 'required_if:is_mca,true|nullable|string',
        ]);

        $transaction = AnalyzedTransaction::find($transactionId);

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found',
            ], 404);
        }

        $isMca = (bool) $request->is_mca;
        $lenderId = $request->lender_id ?? 'unknown';
        $lenderName = $request->lender_name ?? 'Unknown Lender';

        McaPattern::recordPattern(
            $transaction->description,
            $lenderId,
            $lenderName,
            $isMca,
            auth()->id()
        );

        $message = $isMca
            ? "Transaction marked as MCA payment from {$lenderName}. AI will learn from this."
            : 'Transaction removed from MCA payments. AI will learn from this.';

        Log::info('API: MCA status changed', [
            'transaction_id' => $transactionId,
            'description' => $transaction->description,
            'is_mca' => $isMca,
            'lender' => $lenderName,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_mca' => $isMca,
            'lender_id' => $lenderId,
            'lender_name' => $lenderName,
            'amount' => (float) $transaction->amount,
        ]);
    }

    /**
     * Get list of known MCA lenders.
     */
    #[OA\Get(
        path: '/bank-statement/mca-lenders',
        summary: 'Get MCA lenders list',
        description: 'Retrieve a list of known MCA (Merchant Cash Advance) lenders for dropdown selection.',
        tags: ['Reference Data'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lenders retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'lenders',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/McaLender')
                        )
                    ]
                )
            )
        ]
    )]
    public function getMcaLenders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'lenders' => McaPattern::getKnownLenders(),
        ]);
    }

    /**
     * Get overall statistics.
     */
    #[OA\Get(
        path: '/bank-statement/stats',
        summary: 'Get overall statistics',
        description: 'Retrieve overall statistics for the bank statement analyzer.',
        tags: ['Reference Data'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistics retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_sessions', type: 'integer', example: 150),
                                new OA\Property(property: 'total_transactions', type: 'integer', example: 5000),
                                new OA\Property(property: 'total_credits', type: 'number', format: 'float', example: 500000.00),
                                new OA\Property(property: 'total_debits', type: 'number', format: 'float', example: 450000.00),
                                new OA\Property(property: 'total_corrections', type: 'integer', example: 25),
                                new OA\Property(property: 'mca_patterns_learned', type: 'integer', example: 15)
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function getStats(): JsonResponse
    {
        $stats = [
            'total_sessions' => AnalysisSession::where('analysis_type', 'openai')->count(),
            'total_transactions' => AnalyzedTransaction::whereHas('session', function ($q) {
                $q->where('analysis_type', 'openai');
            })->count(),
            'total_credits' => (float) AnalysisSession::where('analysis_type', 'openai')->sum('total_credits'),
            'total_debits' => (float) AnalysisSession::where('analysis_type', 'openai')->sum('total_debits'),
            'total_corrections' => TransactionCorrection::count(),
            'mca_patterns_learned' => McaPattern::where('is_mca', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Group transactions by month and calculate true revenue using TrueRevenueEngine.
     *
     * This method now uses the centralized TrueRevenueEngine for accurate revenue
     * classification with comprehensive pattern matching for:
     * - Card processor settlements (Square, Stripe, Shopify, etc.)
     * - MCA funding exclusion (40+ known funders)
     * - Owner injection exclusion
     * - Tax refund exclusion
     * - Transfer detection
     * - Business day calculations (21.67 days/month average)
     */
    private function groupTransactionsByMonth(array $transactions, ?string $industry = null): array
    {
        $engine = new TrueRevenueEngine();

        // Get monthly breakdown from TrueRevenueEngine
        $monthlyBreakdown = $engine->getMonthlyBreakdown($transactions, $industry);

        // Transform to maintain backward compatibility with existing API consumers
        $monthlyGroups = [];
        $totals = [
            'deposits' => 0,
            'adjustments' => 0,
            'true_revenue' => 0,
            'debits' => 0,
            'needs_review' => 0,
        ];

        // Calculate debits separately (TrueRevenueEngine focuses on credits)
        $debitsByMonth = [];
        foreach ($transactions as $txn) {
            $date = $txn['date'] ?? null;
            if (!$date || ($txn['type'] ?? '') !== 'debit') {
                continue;
            }
            $timestamp = strtotime($date);
            if (!$timestamp) {
                continue;
            }
            $monthKey = date('Y-m', $timestamp);
            if (!isset($debitsByMonth[$monthKey])) {
                $debitsByMonth[$monthKey] = ['amount' => 0, 'count' => 0];
            }
            $debitsByMonth[$monthKey]['amount'] += (float) ($txn['amount'] ?? 0);
            $debitsByMonth[$monthKey]['count']++;
        }

        // Calculate average daily balance for each month (using ending balances if available)
        $balancesByMonth = [];
        $transactionsByMonthDate = [];
        foreach ($transactions as $txn) {
            $date = $txn['date'] ?? null;
            if (!$date) continue;

            $timestamp = strtotime($date);
            if (!$timestamp) continue;

            $monthKey = date('Y-m', $timestamp);

            if (!isset($transactionsByMonthDate[$monthKey])) {
                $transactionsByMonthDate[$monthKey] = [];
            }
            if (!isset($transactionsByMonthDate[$monthKey][$date])) {
                $transactionsByMonthDate[$monthKey][$date] = [];
            }
            $transactionsByMonthDate[$monthKey][$date][] = $txn;
        }

        // Calculate average daily balance for each month using ALL calendar days
        foreach ($transactionsByMonthDate as $monthKey => $dateGroups) {
            // Step 1: Get statement period for this month
            $monthDates = array_keys($dateGroups);
            if (empty($monthDates)) {
                $balancesByMonth[$monthKey] = [
                    'average_daily_balance' => null,
                    'method' => 'no_transactions',
                    'days_count' => 0,
                    'statement_period_days' => 0,
                ];
                continue;
            }

            $periodStart = min($monthDates);
            $periodEnd = max($monthDates);

            // Step 2: Check if we have ending balance data
            $hasEndingBalances = false;
            foreach ($dateGroups as $date => $dayTransactions) {
                $lastTxn = end($dayTransactions);
                if (isset($lastTxn['ending_balance']) && $lastTxn['ending_balance'] !== null) {
                    $hasEndingBalances = true;
                    break;
                }
            }

            // Step 3: Build daily balance table for ALL calendar days in period
            if ($hasEndingBalances) {
                $dailyBalances = [];
                $currentBalance = null;

                // Try to get opening balance from first transaction
                $firstDate = min($monthDates);
                if (isset($dateGroups[$firstDate])) {
                    $firstTxn = $dateGroups[$firstDate][0];
                    if (isset($firstTxn['beginning_balance'])) {
                        $currentBalance = (float) $firstTxn['beginning_balance'];
                    }
                }

                // Iterate through EVERY calendar day in the statement period
                $currentDate = new \DateTime($periodStart);
                $endDate = new \DateTime($periodEnd);

                while ($currentDate <= $endDate) {
                    $dateStr = $currentDate->format('Y-m-d');

                    if (isset($dateGroups[$dateStr])) {
                        // Day has transactions - get last transaction's ending balance
                        $dayTransactions = $dateGroups[$dateStr];
                        $lastTxn = end($dayTransactions);

                        if (isset($lastTxn['ending_balance']) && $lastTxn['ending_balance'] !== null) {
                            $currentBalance = (float) $lastTxn['ending_balance'];
                        }
                    }

                    // Store balance for this day (from transaction or carried forward)
                    if ($currentBalance !== null) {
                        $dailyBalances[$dateStr] = $currentBalance;
                    }

                    // Move to next day
                    $currentDate->modify('+1 day');
                }

                // Step 4: Calculate ADB = sum of all daily balances / total calendar days
                if (count($dailyBalances) > 0) {
                    $balancesByMonth[$monthKey] = [
                        'average_daily_balance' => array_sum($dailyBalances) / count($dailyBalances),
                        'method' => 'actual_balances',
                        'days_count' => count($dailyBalances),
                        'statement_period_days' => count($dailyBalances),
                    ];
                } else {
                    $balancesByMonth[$monthKey] = [
                        'average_daily_balance' => null,
                        'method' => 'no_opening_balance',
                        'days_count' => 0,
                        'statement_period_days' => 0,
                    ];
                }
            } else {
                $balancesByMonth[$monthKey] = [
                    'average_daily_balance' => null,
                    'method' => 'no_balance_data',
                    'days_count' => 0,
                    'statement_period_days' => 0,
                ];
            }
        }

        $totalAverageBalance = 0;
        $balanceMonthsCount = 0;

        foreach ($monthlyBreakdown as $month) {
            $monthKey = $month['month_key'];
            $debits = $debitsByMonth[$monthKey] ?? ['amount' => 0, 'count' => 0];
            $balanceInfo = $balancesByMonth[$monthKey] ?? ['average_daily_balance' => null, 'method' => 'no_data', 'days_count' => 0];

            $monthlyGroups[$monthKey] = [
                'month_key' => $monthKey,
                'month_name' => $month['month_name'],
                'deposits' => $month['total_credits'],
                'deposit_count' => $month['transaction_count'],
                'adjustments' => $month['excluded'],
                'adjustment_count' => count($month['excluded_transactions'] ?? []),
                'true_revenue' => $month['true_revenue'],
                'needs_review' => $month['needs_review'],
                'needs_review_count' => count($month['needs_review_transactions'] ?? []),
                'debits' => $debits['amount'],
                'debit_count' => $debits['count'],
                'days_in_month' => $month['calendar_days'],
                'business_days' => $month['business_days'],
                'average_daily_revenue' => $month['daily_true_revenue'],
                'average_daily' => $month['daily_true_revenue'], // Deprecated, use average_daily_revenue
                'average_daily_balance' => $balanceInfo['average_daily_balance'],
                'average_daily_balance_method' => $balanceInfo['method'],
                'balance_days_count' => $balanceInfo['days_count'],
                'revenue_ratio' => $month['revenue_ratio'],
                // Include classification details for transparency
                'excluded_transactions' => $month['excluded_transactions'] ?? [],
                'needs_review_transactions' => $month['needs_review_transactions'] ?? [],
            ];

            $totals['deposits'] += $month['total_credits'];
            $totals['adjustments'] += $month['excluded'];
            $totals['true_revenue'] += $month['true_revenue'];
            $totals['needs_review'] += $month['needs_review'];
            $totals['debits'] += $debits['amount'];

            // Sum average daily balances for overall average
            if ($balanceInfo['average_daily_balance'] !== null) {
                $totalAverageBalance += $balanceInfo['average_daily_balance'];
                $balanceMonthsCount++;
            }
        }

        ksort($monthlyGroups);
        $monthCount = count($monthlyGroups);

        // Calculate volatility metrics
        $volatility = $engine->getVolatilityMetrics($monthlyBreakdown);

        // Detect MCA payments
        $mcaPayments = $engine->detectMcaPayments($transactions);

        // Calculate MCA capacity
        $avgMonthlyRevenue = $monthCount > 0 ? $totals['true_revenue'] / $monthCount : 0;
        $capacity = $engine->calculateMcaCapacity(
            $avgMonthlyRevenue,
            $mcaPayments['total_daily_payment']
        );

        return [
            'months' => array_values($monthlyGroups),
            'totals' => $totals,
            'averages' => [
                'deposits' => $monthCount > 0 ? $totals['deposits'] / $monthCount : 0,
                'adjustments' => $monthCount > 0 ? $totals['adjustments'] / $monthCount : 0,
                'true_revenue' => $monthCount > 0 ? $totals['true_revenue'] / $monthCount : 0,
                'debits' => $monthCount > 0 ? $totals['debits'] / $monthCount : 0,
                'average_daily_balance' => $balanceMonthsCount > 0
                    ? $totalAverageBalance / $balanceMonthsCount
                    : null,
            ],
            'month_count' => $monthCount,
            'balance_months_count' => $balanceMonthsCount,
            // New enhanced metrics
            'volatility' => $volatility,
            'mca_exposure' => $mcaPayments,
            'mca_capacity' => $capacity,
            'classification_engine' => 'TrueRevenueEngine v1.0',
        ];
    }

    /**
     * Detect MCA payments from transactions.
     */
    private function detectMcaPayments(array $transactions): array
    {
        $mcaLenders = [
            'ondeck' => ['name' => 'OnDeck Capital', 'patterns' => ['ondeck', 'on deck']],
            'kabbage' => ['name' => 'Kabbage', 'patterns' => ['kabbage']],
            'fundbox' => ['name' => 'Fundbox', 'patterns' => ['fundbox']],
            'bluevine' => ['name' => 'BlueVine', 'patterns' => ['bluevine', 'blue vine']],
            'credibly' => ['name' => 'Credibly', 'patterns' => ['credibly']],
            'kapitus' => ['name' => 'Kapitus', 'patterns' => ['kapitus']],
            'rapid_finance' => ['name' => 'Rapid Finance', 'patterns' => ['rapid finance', 'rapidfinance']],
            'can_capital' => ['name' => 'CAN Capital', 'patterns' => ['can capital']],
            'square_capital' => ['name' => 'Square Capital', 'patterns' => ['square capital', 'sq capital']],
            'paypal_working' => ['name' => 'PayPal Working Capital', 'patterns' => ['paypal working', 'paypal wc']],
            'amazon_lending' => ['name' => 'Amazon Lending', 'patterns' => ['amazon lending']],
            'shopify_capital' => ['name' => 'Shopify Capital', 'patterns' => ['shopify capital']],
            'stripe_capital' => ['name' => 'Stripe Capital', 'patterns' => ['stripe capital']],
            'clearco' => ['name' => 'Clearco', 'patterns' => ['clearco', 'clearbanc']],
            'libertas' => ['name' => 'Libertas Funding', 'patterns' => ['libertas']],
        ];

        $mcaPayments = [];

        foreach ($transactions as $txn) {
            if (($txn['type'] ?? '') !== 'debit') {
                continue;
            }

            $description = $txn['description'] ?? '';
            $descriptionLower = strtolower($description);
            $matchedLenderId = null;
            $matchedLenderName = null;

            if (! McaPattern::isExcluded($description)) {
                $learnedMatch = McaPattern::checkMcaPattern($description);
                if ($learnedMatch) {
                    $matchedLenderId = $learnedMatch['lender_id'];
                    $matchedLenderName = $learnedMatch['lender_name'];
                }
            }

            if (! $matchedLenderId) {
                foreach ($mcaLenders as $lenderId => $lenderInfo) {
                    foreach ($lenderInfo['patterns'] as $pattern) {
                        if (stripos($descriptionLower, $pattern) !== false) {
                            $matchedLenderId = $lenderId;
                            $matchedLenderName = $lenderInfo['name'];
                            break 2;
                        }
                    }
                }
            }

            if ($matchedLenderId) {
                if (! isset($mcaPayments[$matchedLenderId])) {
                    $mcaPayments[$matchedLenderId] = [
                        'lender_id' => $matchedLenderId,
                        'lender_name' => $matchedLenderName,
                        'payment_count' => 0,
                        'total_amount' => 0,
                    ];
                }

                $amount = (float) ($txn['amount'] ?? 0);
                $mcaPayments[$matchedLenderId]['payment_count']++;
                $mcaPayments[$matchedLenderId]['total_amount'] += $amount;
            }
        }

        $totalMcaCount = count($mcaPayments);
        $totalMcaPayments = 0;
        $totalMcaAmount = 0;

        foreach ($mcaPayments as &$lender) {
            $lender['average_payment'] = $lender['payment_count'] > 0
                ? $lender['total_amount'] / $lender['payment_count']
                : 0;
            $totalMcaPayments += $lender['payment_count'];
            $totalMcaAmount += $lender['total_amount'];
        }

        return [
            'total_mca_count' => $totalMcaCount,
            'total_mca_payments' => $totalMcaPayments,
            'total_mca_amount' => $totalMcaAmount,
            'lenders' => array_values($mcaPayments),
        ];
    }

    /**
     * Get learned patterns statistics.
     */
    #[OA\Get(
        path: '/bank-statement/learned-patterns',
        summary: 'Get learned patterns',
        description: 'Retrieve statistics and list of learned transaction patterns used for auto-correction.',
        tags: ['Reference Data'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Page number',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Items per page',
                schema: new OA\Schema(type: 'integer', default: 50)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Patterns retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'stats',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_patterns', type: 'integer'),
                                new OA\Property(property: 'manual_overrides', type: 'integer'),
                                new OA\Property(property: 'openai_learned', type: 'integer'),
                                new OA\Property(property: 'high_confidence', type: 'integer'),
                                new OA\Property(property: 'credit_patterns', type: 'integer'),
                                new OA\Property(property: 'debit_patterns', type: 'integer')
                            ]
                        ),
                        new OA\Property(property: 'patterns', type: 'array', items: new OA\Items(type: 'object'))
                    ]
                )
            )
        ]
    )]
    public function getLearnedPatterns(Request $request): JsonResponse
    {
        $perPage = min($request->input('per_page', 50), 100);

        $patterns = LearnedTransactionPattern::orderByDesc('is_manual_override')
            ->orderByDesc('confidence_score')
            ->orderByDesc('occurrence_count')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'stats' => LearnedTransactionPattern::getStats(),
            'patterns' => $patterns->items(),
            'pagination' => [
                'current_page' => $patterns->currentPage(),
                'last_page' => $patterns->lastPage(),
                'per_page' => $patterns->perPage(),
                'total' => $patterns->total(),
            ],
        ]);
    }

    /**
     * Reset a learned pattern (remove it so OpenAI can re-learn).
     */
    #[OA\Delete(
        path: '/bank-statement/learned-patterns/{patternId}',
        summary: 'Reset a learned pattern',
        description: 'Delete a learned pattern so it can be re-learned from future transactions.',
        tags: ['Reference Data'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'patternId',
                in: 'path',
                required: true,
                description: 'Pattern ID to reset',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pattern reset successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Pattern reset successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Pattern not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function resetLearnedPattern(int $patternId): JsonResponse
    {
        $pattern = LearnedTransactionPattern::find($patternId);

        if (! $pattern) {
            return response()->json([
                'success' => false,
                'error' => 'Pattern not found',
            ], 404);
        }

        $description = $pattern->original_description;
        $pattern->delete();

        Log::info("Learned pattern reset", [
            'pattern_id' => $patternId,
            'description' => $description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pattern reset successfully. It will be re-learned from future transactions.',
        ]);
    }

    /**
     * Clear all learned patterns.
     */
    #[OA\Delete(
        path: '/bank-statement/learned-patterns',
        summary: 'Clear all learned patterns',
        description: 'Delete all learned patterns. Use with caution - this will reset all AI learning.',
        tags: ['Reference Data'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All patterns cleared',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'patterns_deleted', type: 'integer')
                    ]
                )
            )
        ]
    )]
    public function clearAllLearnedPatterns(): JsonResponse
    {
        $count = LearnedTransactionPattern::count();
        LearnedTransactionPattern::truncate();

        Log::warning("All learned patterns cleared", [
            'patterns_deleted' => $count,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'All learned patterns have been cleared.',
            'patterns_deleted' => $count,
        ]);
    }
}
