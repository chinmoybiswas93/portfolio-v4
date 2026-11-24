<?php

declare(strict_types=1);

namespace App\Support\Seo;

use Illuminate\Http\Request;
use Spatie\MediaLibrary\HasMedia;

final class Seo
{
    /** Longest title shown in full in search results (characters; Google cuts at about 600 px). */
    public const int TITLE_MAX = 60;

    /**
     * Document title (ADR-0010): the page title plus " · {name}" while that fits in TITLE_MAX characters, else
     * the page title alone. Pages without a title use the site title.
     */
    public static function title(?string $title, string $siteTitle, string $name): string
    {
        $title = trim((string) $title);
        if ($title === '') {
            return $siteTitle;
        }
        $withName = $title.' · '.$name;

        return mb_strlen($withName) <= self::TITLE_MAX ? $withName : $title;
    }

    /** Absolute URL on the site's configured origin (APP_URL), never the request host. */
    public static function absolute(string $path): string
    {
        if (preg_match('#^https?://#', $path) === 1) {
            $parts = parse_url($path);
            $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /** Canonical: configured origin + path, keeping only ?page when it is above 1. */
    public static function canonical(Request $request): string
    {
        $path = $request->getPathInfo();
        $page = $request->query('page');
        $query = is_numeric($page) && (int) $page > 1 ? '?page='.(int) $page : '';

        return self::absolute($path === '/' ? '/' : rtrim($path, '/')).$query;
    }

    public static function indexable(): bool
    {
        return app()->isProduction() && (bool) config('seo.indexable', true);
    }

    /** Uploaded share image if the model has one, else the generated card. */
    public static function image(?HasMedia $model, string $label, string $title): string
    {
        $uploaded = $model?->getFirstMediaUrl('og');

        return self::absolute($uploaded !== null && $uploaded !== '' ? $uploaded : OgImage::url($label, $title));
    }
}
