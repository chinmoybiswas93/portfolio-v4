<?php

declare(strict_types=1);

use App\Http\Controllers\Mcp\ApproveMcpAuthorizationController;
use App\Http\Middleware\Mcp\EnsureOwnerForConsent;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
 * Passport routes, re-registered so the consent screen sits behind the admin login + 2FA (ADR-0009 §2).
 * Names match Passport's so laravel/mcp's discovery metadata points here. Only the authorization-code flow.
 */
Route::prefix('oauth')->name('passport.')->group(function (): void {
    Route::post('/token', [AccessTokenController::class, 'issueToken'])->middleware('throttle:30,1')->name('token');

    Route::middleware(['web', EnsureOwnerForConsent::class])->group(function (): void {
        Route::get('/authorize', [AuthorizationController::class, 'authorize'])->name('authorizations.authorize');
        Route::post('/authorize', [ApproveMcpAuthorizationController::class, 'approve'])->name('authorizations.approve');
        Route::delete('/authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
    });
});
