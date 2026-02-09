<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankStatementApiController;
use App\Http\Controllers\Api\HubspotCrmCardController;
use App\Http\Controllers\Api\PricingApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// API v1
Route::prefix('v1')->group(function () {
    // Authentication routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/user', [AuthController::class, 'user']);
            Route::get('/tokens', [AuthController::class, 'tokens']);
            Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        });
    });

    // Bank Statement Analyzer API (protected)
    Route::middleware('auth:sanctum')->prefix('bank-statement')->group(function () {
        // Analysis endpoints
        Route::post('/analyze', [BankStatementApiController::class, 'analyze']);
        Route::get('/sessions', [BankStatementApiController::class, 'getSessions']);
        Route::get('/sessions/{sessionId}', [BankStatementApiController::class, 'getSession']);
        Route::get('/sessions/{sessionId}/transactions', [BankStatementApiController::class, 'getTransactions']);
        Route::get('/sessions/{sessionId}/summary', [BankStatementApiController::class, 'getSummary']);
        Route::get('/sessions/{sessionId}/monthly', [BankStatementApiController::class, 'getMonthlyData']);
        Route::get('/sessions/{sessionId}/mca-analysis', [BankStatementApiController::class, 'getMcaAnalysis']);
        Route::get('/sessions/{sessionId}/download', [BankStatementApiController::class, 'downloadCsv']);
        Route::delete('/sessions/{sessionId}', [BankStatementApiController::class, 'deleteSession']);

        // Transaction correction endpoints
        Route::post('/transactions/{transactionId}/toggle-type', [BankStatementApiController::class, 'toggleType']);
        Route::post('/transactions/{transactionId}/toggle-revenue', [BankStatementApiController::class, 'toggleRevenue']);
        Route::post('/transactions/{transactionId}/toggle-mca', [BankStatementApiController::class, 'toggleMca']);

        // Reference data
        Route::get('/mca-lenders', [BankStatementApiController::class, 'getMcaLenders']);
        Route::get('/stats', [BankStatementApiController::class, 'getStats']);

        // Learned patterns management
        Route::get('/learned-patterns', [BankStatementApiController::class, 'getLearnedPatterns']);
        Route::delete('/learned-patterns', [BankStatementApiController::class, 'clearAllLearnedPatterns']);
        Route::delete('/learned-patterns/{patternId}', [BankStatementApiController::class, 'resetLearnedPattern']);
    });

    // MCA Pricing API (protected)
    Route::middleware('auth:sanctum')->prefix('pricing')->group(function () {
        // Calculate MCA offer with full breakdown
        Route::post('/calculate', [PricingApiController::class, 'calculate']);

        // Check merchant capacity for additional positions
        Route::post('/capacity', [PricingApiController::class, 'checkCapacity']);

        // Validate offer terms server-side
        Route::post('/validate', [PricingApiController::class, 'validate']);

        // Generate multiple offer scenarios
        Route::post('/scenarios', [PricingApiController::class, 'scenarios']);

        // Reference data
        Route::get('/industries', [PricingApiController::class, 'industries']);
        Route::get('/config', [PricingApiController::class, 'config']);
    });
});

// HubSpot CRM Card API (public - called by HubSpot)
Route::prefix('hubspot')->group(function () {
    Route::get('/crm-card', [HubspotCrmCardController::class, 'fetch']);
    Route::post('/crm-card', [HubspotCrmCardController::class, 'fetch']);
    Route::post('/webhook', [HubspotCrmCardController::class, 'webhook']);
});
