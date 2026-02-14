<?php

use App\Http\Controllers\ApiUsageController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BankStatementController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HubspotController;
use App\Http\Controllers\MerchantBankController;
use App\Http\Controllers\PlaidController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmartMcaController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UnderwritingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Bank Statement Analyzer (OpenAI)
    Route::prefix('bankstatement')->name('bankstatement.')->group(function () {
        Route::get('/', [BankStatementController::class, 'index'])->name('index');
        Route::get('/analyze', [BankStatementController::class, 'analyzeGet'])->name('analyze.get');
        Route::post('/analyze', [BankStatementController::class, 'analyze'])->name('analyze');
        Route::get('/results', [BankStatementController::class, 'viewResults'])->name('view-results');
        Route::get('/history', [BankStatementController::class, 'history'])->name('history');
        Route::get('/session/{sessionId}', [BankStatementController::class, 'session'])->name('session');
        Route::get('/download/{sessionId}', [BankStatementController::class, 'downloadCsv'])->name('download');
        Route::post('/toggle-type', [BankStatementController::class, 'toggleType'])->name('toggle-type');
        Route::post('/toggle-revenue', [BankStatementController::class, 'toggleRevenue'])->name('toggle-revenue');
        Route::post('/toggle-mca', [BankStatementController::class, 'toggleMca'])->name('toggle-mca');
        Route::post('/find-similar-transactions', [BankStatementController::class, 'findSimilarTransactions'])->name('find-similar-transactions');
        Route::post('/toggle-category', [BankStatementController::class, 'toggleCategory'])->name('toggle-category');
        Route::get('/categories', [BankStatementController::class, 'getCategories'])->name('categories');
        Route::post('/batch-classify', [BankStatementController::class, 'batchClassify'])->name('batch-classify');
        Route::post('/mark-notified', [BankStatementController::class, 'markNotified'])->name('mark-notified');
        Route::get('/mca-lenders', [BankStatementController::class, 'getMcaLenders'])->name('mca-lenders');
        Route::get('/view-analysis', [BankStatementController::class, 'viewAnalysis'])->name('view-analysis');
        Route::get('/lenders', [BankStatementController::class, 'lenders'])->name('lenders');
        Route::get('/lenders/create', [BankStatementController::class, 'createLender'])->name('lenders.create');
        Route::post('/lenders', [BankStatementController::class, 'storeLender'])->name('lenders.store');
        Route::get('/lenders/{lenderId}', [BankStatementController::class, 'lenderDetail'])->name('lender-detail');
        Route::get('/lenders/{lenderId}/pattern/create', [BankStatementController::class, 'createPattern'])->name('lenders.pattern.create');
        Route::post('/lenders/{lenderId}/pattern', [BankStatementController::class, 'storePattern'])->name('lenders.pattern.store');
        Route::get('/lenders/{lenderId}/pattern/{patternId}/edit', [BankStatementController::class, 'editPattern'])->name('lenders.pattern.edit');
        Route::put('/lenders/{lenderId}/pattern/{patternId}', [BankStatementController::class, 'updatePattern'])->name('lenders.pattern.update');
        Route::delete('/lenders/{lenderId}/pattern/{patternId}', [BankStatementController::class, 'deletePattern'])->name('lenders.pattern.delete');

        // MCA Offer Calculator
        Route::post('/save-offer', [BankStatementController::class, 'saveOffer'])->name('save-offer');
        Route::post('/load-offers', [BankStatementController::class, 'loadOffers'])->name('load-offers');
        Route::post('/delete-offer', [BankStatementController::class, 'deleteOffer'])->name('delete-offer');
        Route::post('/toggle-offer-favorite', [BankStatementController::class, 'toggleOfferFavorite'])->name('toggle-offer-favorite');
    });

    // Underwriting
    Route::prefix('underwriting')->name('underwriting.')->group(function () {
        Route::get('/', [UnderwritingController::class, 'index'])->name('index');
        Route::post('/analyze', [UnderwritingController::class, 'analyze'])->name('analyze');
        Route::post('/analyze-text', [UnderwritingController::class, 'analyzeText'])->name('analyze-text');
        Route::post('/quick-check', [UnderwritingController::class, 'quickCheck'])->name('quick-check');
        Route::get('/categories', [UnderwritingController::class, 'getCategories'])->name('categories');
        Route::post('/auto-decision', [UnderwritingController::class, 'autoDecision'])->name('auto-decision');
        Route::post('/batch-process', [UnderwritingController::class, 'batchProcess'])->name('batch-process');
        Route::get('/automation-stats', [UnderwritingController::class, 'automationStats'])->name('automation-stats');
    });

    // Plaid Integration
    Route::prefix('plaid')->name('plaid.')->group(function () {
        Route::get('/', [PlaidController::class, 'index'])->name('index');
        Route::post('/create-link-token', [PlaidController::class, 'createLinkToken'])->name('create-link-token');
        Route::post('/exchange-token', [PlaidController::class, 'exchangeToken'])->name('exchange-token');
        Route::post('/sync', [PlaidController::class, 'syncTransactions'])->name('sync');
        Route::post('/accounts', [PlaidController::class, 'getAccounts'])->name('accounts');
        Route::post('/transactions', [PlaidController::class, 'getTransactions'])->name('transactions');
        Route::post('/analyze', [PlaidController::class, 'analyzeTransactions'])->name('analyze');
        Route::post('/disconnect', [PlaidController::class, 'disconnect'])->name('disconnect');
    });

    // MCA Applications
    Route::prefix('applications')->name('applications.')->group(function () {
        Route::get('/', [ApplicationController::class, 'index'])->name('index');
        Route::get('/create', [ApplicationController::class, 'create'])->name('create');
        Route::post('/', [ApplicationController::class, 'store'])->name('store');
        Route::get('/{application}', [ApplicationController::class, 'show'])->name('show');
        Route::get('/{application}/edit', [ApplicationController::class, 'edit'])->name('edit');
        Route::put('/{application}', [ApplicationController::class, 'update'])->name('update');
        Route::delete('/{application}', [ApplicationController::class, 'destroy'])->name('destroy');
        Route::patch('/{application}/status', [ApplicationController::class, 'updateStatus'])->name('update-status');
        Route::post('/{application}/verification', [ApplicationController::class, 'runVerification'])->name('run-verification');
        Route::post('/{application}/verification/all', [ApplicationController::class, 'runAllVerifications'])->name('run-all-verifications');
        Route::get('/{application}/flow-status', [ApplicationController::class, 'getFlowStatus'])->name('flow-status');
        Route::post('/{application}/risk-score', [ApplicationController::class, 'calculateRiskScore'])->name('calculate-risk');
        Route::post('/{application}/note', [ApplicationController::class, 'addApplicationNote'])->name('add-note');
        Route::post('/{application}/assign', [ApplicationController::class, 'assignTo'])->name('assign');
        // Bank Link Requests
        Route::post('/{application}/bank-link', [ApplicationController::class, 'sendBankLinkRequest'])->name('send-bank-link');
        Route::post('/{application}/bank-link/{linkRequest}/resend', [ApplicationController::class, 'resendBankLinkRequest'])->name('resend-bank-link');
        Route::get('/{application}/bank-status', [ApplicationController::class, 'getBankLinkStatus'])->name('bank-status');
        // Documents
        Route::get('/{application}/documents', [ApplicationController::class, 'getDocuments'])->name('documents');
        Route::post('/{application}/documents', [ApplicationController::class, 'uploadDocument'])->name('upload-document');
        Route::get('/{application}/documents/{document}/view', [ApplicationController::class, 'viewDocument'])->name('view-document');
        Route::get('/{application}/documents/{document}/download', [ApplicationController::class, 'downloadDocument'])->name('download-document');
        Route::delete('/{application}/documents/{document}', [ApplicationController::class, 'deleteDocument'])->name('delete-document');
        // Bank Statement Analysis
        Route::post('/{application}/analyze-bank-statements', [ApplicationController::class, 'runBankAnalysis'])->name('analyze-bank');
        Route::get('/{application}/documents/{document}/transactions', [ApplicationController::class, 'getDocumentTransactions'])->name('document-transactions');
        Route::patch('/{application}/documents/{document}/transactions/{transaction}', [ApplicationController::class, 'updateTransaction'])->name('update-transaction');
        Route::post('/{application}/documents/{document}/recalculate-revenue', [ApplicationController::class, 'recalculateTrueRevenue'])->name('recalculate-revenue');
        Route::get('/{application}/fcs/{filename}', [ApplicationController::class, 'downloadFCS'])->name('download-fcs');
        // Underwriting Score
        Route::post('/{application}/underwriting-score', [ApplicationController::class, 'calculateUnderwritingScore'])->name('underwriting-score');
        // Email FCS Report
        Route::post('/{application}/send-fcs', [ApplicationController::class, 'sendFCSReport'])->name('send-fcs');
    });

    // Configuration - Integration Settings
    Route::prefix('configuration')->name('configuration.')->group(function () {
        Route::get('/', [ConfigurationController::class, 'index'])->name('index');
        Route::get('/{integration}', [ConfigurationController::class, 'edit'])->name('edit');
        Route::put('/{integration}', [ConfigurationController::class, 'update'])->name('update');
        Route::post('/{integration}/test', [ConfigurationController::class, 'test'])->name('test');
        Route::post('/{integration}/toggle', [ConfigurationController::class, 'toggle'])->name('toggle');
    });

    // API Usage Tracking
    Route::prefix('api-usage')->name('api-usage.')->group(function () {
        Route::get('/', [ApiUsageController::class, 'index'])->name('index');
        Route::get('/stats', [ApiUsageController::class, 'getStats'])->name('stats');
        Route::get('/export', [ApiUsageController::class, 'exportCsv'])->name('export');
    });

    // Training (ML Model Training)
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/', [TrainingController::class, 'index'])->name('index');
        Route::post('/upload', [TrainingController::class, 'upload'])->name('upload');
    });

    // SmartMCA (AI-powered Bank Statement Analysis)
    Route::prefix('smartmca')->name('smartmca.')->group(function () {
        Route::get('/', [SmartMcaController::class, 'index'])->name('index');
        Route::post('/analyze', [SmartMcaController::class, 'analyze'])->name('analyze');
        Route::get('/history', [SmartMcaController::class, 'history'])->name('history');
        Route::get('/patterns', [SmartMcaController::class, 'getLearnedPatterns'])->name('patterns');
        Route::get('/session/{sessionId}', [SmartMcaController::class, 'viewSession'])->name('session');
        Route::post('/correction', [SmartMcaController::class, 'saveCorrection'])->name('correction');
        Route::post('/calculate-revenue', [SmartMcaController::class, 'calculateTrueRevenue'])->name('calculateRevenue');
        Route::get('/pricing', [SmartMcaController::class, 'pricing'])->name('pricing');
        Route::get('/pricing/{sessionId}', [SmartMcaController::class, 'pricingWithSession'])->name('pricing.session');
        Route::post('/pricing/calculate', [SmartMcaController::class, 'pricingCalculate'])->name('pricing.calculate');
        Route::post('/pricing/scenarios', [SmartMcaController::class, 'pricingScenarios'])->name('pricing.scenarios');
        Route::get('/accuracy-dashboard', [SmartMcaController::class, 'accuracyDashboard'])->name('accuracy-dashboard');
    });

    // HubSpot Integration
    Route::prefix('hubspot')->name('hubspot.')->group(function () {
        Route::get('/', [HubspotController::class, 'index'])->name('index');
        Route::get('/connect', [HubspotController::class, 'connect'])->name('connect');
        Route::get('/callback', [HubspotController::class, 'callback'])->name('callback');
        Route::post('/disconnect', [HubspotController::class, 'disconnect'])->name('disconnect');
        Route::post('/refresh', [HubspotController::class, 'refresh'])->name('refresh');
        Route::post('/sync-offer', [HubspotController::class, 'syncOffer'])->name('sync-offer');
        Route::get('/contacts', [HubspotController::class, 'getContacts'])->name('contacts');
        Route::get('/companies', [HubspotController::class, 'getCompanies'])->name('companies');
        Route::get('/calculator', [HubspotController::class, 'calculator'])->name('calculator');
    });
});

// Plaid Webhook (public endpoint)
Route::post('/api/plaid/webhook', [PlaidController::class, 'webhook'])->name('plaid.webhook');

// Merchant Bank Connection (public routes - no auth required)
Route::prefix('merchant/bank')->name('merchant.bank.')->group(function () {
    Route::get('/{token}', [MerchantBankController::class, 'show'])->name('connect');
    Route::post('/{token}/create-link-token', [MerchantBankController::class, 'createLinkToken'])->name('create-link-token');
    Route::post('/{token}/exchange-token', [MerchantBankController::class, 'exchangeToken'])->name('exchange-token');
});

require __DIR__.'/auth.php';
