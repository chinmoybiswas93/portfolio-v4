<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Cache\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves cached HTML for anonymous GET requests on public routes and stores successful responses.
 * Public routes never start a session, so every visitor gets the same page.
 */
final class CachePublicPage
{
    public function __construct(private readonly PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodCacheable() || ! config('cache.pages_enabled', true)) {
            return $next($request);
        }

        $key = $this->cache->key($request);

        /** @var array{content: string, type: string}|null $hit */
        $hit = $this->cache->store()->get($key);
        if (is_array($hit)) {
            return new Response($hit['content'], 200, ['Content-Type' => $hit['type'], 'X-Page-Cache' => 'HIT']);
        }

        $response = $next($request);

        $type = (string) $response->headers->get('Content-Type', '');
        if ($response->getStatusCode() === 200 && str_starts_with($type, 'text/html') && ! $response->headers->has('Set-Cookie')) {
            $this->cache->store()->put($key, ['content' => (string) $response->getContent(), 'type' => $type], PageCache::TTL_SECONDS);
            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }
}
