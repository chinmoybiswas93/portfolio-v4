<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 404 for routes of features this app does not use (Filament export/import downloads).
 */
final class FeatureDisabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort(404);
    }
}
