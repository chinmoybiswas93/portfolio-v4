<?php

declare(strict_types=1);

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
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // A tampered Livewire snapshot is rejected; answer like an expired page (419), not a server error.
        $exceptions->render(fn (CorruptComponentPayloadException $e) => response()->json(['message' => 'Page expired. Reload and try again.'], 419));
    })->create();
