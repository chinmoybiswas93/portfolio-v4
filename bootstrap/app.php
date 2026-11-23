<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::middleware('public')->group(base_path('routes/public.php'));
            Route::group([], base_path('routes/oauth.php'));
            // Health check: no session or cookies (it is hit by monitors), throttled per IP.
            Route::middleware([SecurityHeaders::class, 'throttle:30,1'])
                ->get('/up', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No public "login" route (the admin path is private): MCP/OAuth answer 401 JSON, anything else goes home.
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('mcp', 'oauth/*', '.well-known/*') ? null : '/');
        // Public site: no session, no cookies, no CSRF (it has no forms), so pages can be cached for everyone.
        $middleware->group('public', [
            SecurityHeaders::class.':public',
            CachePublicPage::class,
            SubstituteBindings::class,
        ]);
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // MCP and OAuth endpoints always answer in JSON (no redirect to a login page).
            fn (Request $request) => $request->is('api/*', 'mcp', 'oauth/*', '.well-known/*') || $request->expectsJson(),
        );
        // A tampered Livewire snapshot is rejected; answer like an expired page (419), not a server error.
        $exceptions->render(fn (CorruptComponentPayloadException $e) => response()->json(['message' => 'Page expired. Reload and try again.'], 419));
    })->create();
