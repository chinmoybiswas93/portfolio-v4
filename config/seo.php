<?php

declare(strict_types=1);

return [
    // Search engines may index the site only in production with this on (staging is always noindex).
    'indexable' => (bool) env('SEO_INDEXABLE', true),
];
