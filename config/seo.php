<?php

declare(strict_types=1);

return [
    // Search engines may index the site only in production with this on (staging is always noindex).
    'indexable' => (bool) env('SEO_INDEXABLE', true),

    /*
     * Crawlers named in robots.txt (ADR-0010, owner decision 2026-11-24): search and answer engines and AI
     * training crawlers are all allowed. Move a name to 'disallowed_crawlers' to opt it out.
     */
    'allowed_crawlers' => [
        'Googlebot', 'Bingbot', 'Applebot', 'DuckDuckBot',
        'OAI-SearchBot', 'ChatGPT-User', 'GPTBot',
        'ClaudeBot', 'Claude-User', 'Claude-SearchBot',
        'PerplexityBot', 'Perplexity-User',
        'Google-Extended', 'Applebot-Extended',
    ],
    'disallowed_crawlers' => [],

    // IndexNow (Bing, Yandex, Seznam…): ping changed URLs. Off without a key; the key file is served at /indexnow.txt.
    'indexnow_key' => env('INDEXNOW_KEY'),
    'indexnow_endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
];
