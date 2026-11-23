<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CheckHealth;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(CheckHealth $check): JsonResponse
    {
        $result = $check();

        return response()
            ->json($result, $result['database'] ? 200 : 503)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
