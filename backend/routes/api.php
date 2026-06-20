<?php

use App\Http\Controllers\Console\AppController;
use App\Http\Controllers\Console\AuthController;
use App\Http\Controllers\Console\BillingController;
use App\Http\Controllers\Console\ServiceController;
use App\Http\Controllers\Console\SessionController as ConsoleSessionController;
use App\Http\Controllers\Console\WebhookController;
use App\Http\Controllers\Console\WorkflowController as ConsoleWorkflowController;
use App\Http\Controllers\CredentialDiscoveryController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\HostedController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StandaloneController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public demo (no API key; rate-limited). Powers demos.pollus.tech.
|--------------------------------------------------------------------------
*/
Route::prefix('demo')->middleware('throttle:30,1')->group(function () {
    Route::post('start', [DemoController::class, 'start']);
    Route::get('status/{id}', [DemoController::class, 'status']);
});

/*
|--------------------------------------------------------------------------
| Developer console — Valyd SSO auth + session-token authed management API.
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::get('config', [AuthController::class, 'config']);
    // Auto-login: exchange the IdP authorization code for a console session.
    // `callback` kept as a backward-compatible alias of `autologin`.
    Route::post('autologin', [AuthController::class, 'callback']);
    Route::post('callback', [AuthController::class, 'callback']);
    Route::middleware('console.auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('email', [AuthController::class, 'setEmail']);
        Route::put('account', [AuthController::class, 'updateAccount']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('console')->middleware('console.auth')->group(function () {
    Route::get('services', [ServiceController::class, 'index']);

    Route::get('apps', [AppController::class, 'index']);
    Route::post('apps', [AppController::class, 'store']);
    Route::match(['put', 'patch'], 'apps/{app}', [AppController::class, 'update']);
    Route::delete('apps/{app}', [AppController::class, 'destroy']);
    Route::post('apps/{app}/rotate-key', [AppController::class, 'rotateKey']);

    Route::get('apps/{app}/workflows', [ConsoleWorkflowController::class, 'index']);
    Route::post('apps/{app}/workflows', [ConsoleWorkflowController::class, 'store']);
    Route::patch('apps/{app}/workflows/{id}', [ConsoleWorkflowController::class, 'update']);
    Route::delete('apps/{app}/workflows/{id}', [ConsoleWorkflowController::class, 'destroy']);

    Route::get('apps/{app}/webhook', [WebhookController::class, 'show']);
    Route::put('apps/{app}/webhook', [WebhookController::class, 'update']);
    Route::post('apps/{app}/webhook/rotate-secret', [WebhookController::class, 'rotateSecret']);

    Route::get('apps/{app}/sessions', [ConsoleSessionController::class, 'index']);

    // Prepaid account balance (per console user, across all their apps).
    Route::get('billing/balance', [BillingController::class, 'balance']);
    Route::post('billing/top-up', [BillingController::class, 'topUp']);
    Route::get('billing/transactions', [BillingController::class, 'transactions']);
});

/*
|--------------------------------------------------------------------------
| v2 API — authenticated by the client's project API key (X-API-Key / Bearer)
|--------------------------------------------------------------------------
*/
Route::prefix('v2')->middleware('project.key')->group(function () {

    // --- Workflows (reusable feature bundles, Didit-style) ---
    Route::get('workflows', [WorkflowController::class, 'index']);
    Route::post('workflows', [WorkflowController::class, 'store']);
    Route::get('workflows/{id}', [WorkflowController::class, 'show']);
    Route::patch('workflows/{id}', [WorkflowController::class, 'update']);
    Route::delete('workflows/{id}', [WorkflowController::class, 'destroy']);

    // --- Hosted verification sessions ---
    Route::post('session', [SessionController::class, 'store']);
    Route::get('session', [SessionController::class, 'index']);
    Route::get('session/{id}', [SessionController::class, 'show']);
    Route::get('session/{id}/decision', [SessionController::class, 'decision']);
    Route::patch('session/{id}/status', [SessionController::class, 'updateStatus']);

    // --- Credential discovery (states + license types/providers) ---
    Route::get('credential/states', [CredentialDiscoveryController::class, 'states']);
    Route::get('credential/states/{state}/providers', [CredentialDiscoveryController::class, 'providers']);

    // --- Standalone (direct, synchronous) per-capability checks ---
    Route::post('id-verification', [StandaloneController::class, 'idVerification']);
    Route::post('liveness', [StandaloneController::class, 'liveness']);
    Route::post('face-match', [StandaloneController::class, 'faceMatch']);
    Route::post('age-verification', [StandaloneController::class, 'ageVerification']);
    Route::post('credential-verification', [StandaloneController::class, 'credentialVerification']);
    Route::post('location', [StandaloneController::class, 'location']);

    // KYC + License in one synchronous call (ID + liveness + face match, then the
    // license checked against the OCR'd name).
    Route::post('kyc-credential', [StandaloneController::class, 'kycCredential']);
});

/*
|--------------------------------------------------------------------------
| Hosted page (end-user) endpoints — authenticated by the session_token only
|--------------------------------------------------------------------------
*/
Route::prefix('hosted/{token}')->middleware('session.token')->group(function () {
    Route::get('state', [HostedController::class, 'state']);
    Route::get('credential/states', [CredentialDiscoveryController::class, 'states']);
    Route::get('credential/states/{state}/providers', [CredentialDiscoveryController::class, 'providers']);
    Route::post('documents', [HostedController::class, 'uploadDocument']);
    Route::post('run/{check}', [HostedController::class, 'runCheck']);
    Route::post('decline', [HostedController::class, 'decline']);
});

// Result is resolved directly by session_token (works on terminal sessions),
// so it must live OUTSIDE the session.token group.
Route::get('hosted/{token}/result', [HostedController::class, 'result']);
