<?php

declare(strict_types=1);

namespace App\Support\Cache;

/**
 * Model observer: any change to public content clears the page cache.
 */
final class FlushPageCache
{
    public function __construct(private readonly PageCache $cache) {}

    public function saved(): void
    {
        $this->cache->flush();
    }

    public function deleted(): void
    {
        $this->cache->flush();
    }

    public function restored(): void
    {
        $this->cache->flush();
    }
}
