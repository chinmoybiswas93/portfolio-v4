<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\DB;
use Throwable;

final class CheckHealth
{
    /**
     * @return array{status: string, release: string, database: bool}
     */
    public function __invoke(): array
    {
        $release = is_file(base_path('RELEASE'))
            ? trim((string) file_get_contents(base_path('RELEASE')))
            : 'local';

        try {
            DB::connection()->getPdo();
            $database = true;
        } catch (Throwable) {
            $database = false;
        }

        return [
            'status' => $database ? 'ok' : 'degraded',
            'release' => $release,
            'database' => $database,
        ];
    }
}
