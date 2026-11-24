<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells IndexNow search engines (Bing, Yandex, Seznam…) that pages changed (ADR-0010). Runs from the database
 * queue that the cron drains every minute; one short request, retried twice, never blocks an admin save.
 */
final class SubmitToIndexNow implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /** @param  list<string>  $urls  absolute URLs on this site */
    public function __construct(public readonly array $urls) {}

    public function handle(): void
    {
        $key = (string) config('seo.indexnow_key');
        if ($key === '' || $this->urls === []) {
            return;
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $response = Http::timeout(10)->acceptJson()->post((string) config('seo.indexnow_endpoint'), [
            'host' => $host,
            'key' => $key,
            'keyLocation' => rtrim((string) config('app.url'), '/').'/indexnow.txt',
            'urlList' => array_values(array_unique($this->urls)),
        ]);

        // 200 = received, 202 = accepted pending key check. 4xx means our request or key is wrong: log, don't retry.
        if ($response->clientError()) {
            Log::warning('IndexNow rejected the submission', ['status' => $response->status(), 'urls' => $this->urls]);

            return;
        }
        $response->throw();
    }
}
