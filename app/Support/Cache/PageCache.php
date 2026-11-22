<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Full-page cache for public HTML (ADR-0008). A small in-house cache: spatie/laravel-responsecache 8.x needs
 * PHP 8.4 and 7.x stops at Laravel 12. Entries live in the dedicated "pages" file store and are flushed
 * whenever content or settings change.
 */
final class PageCache
{
    public const TTL_SECONDS = 86400;

    /** Query parameters that change a public page; anything else is ignored for the cache key. */
    private const ALLOWED_QUERY = ['page'];

    public function store(): Repository
    {
        return Cache::store('pages');
    }

    public function key(Request $request): string
    {
        $query = collect($request->query())->only(self::ALLOWED_QUERY)->sortKeys()->all();

        return 'page:'.hash('sha256', $request->getPathInfo().'?'.http_build_query($query));
    }

    public function flush(): void
    {
        $this->store()->flush();
    }
}
