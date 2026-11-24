<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\Tag;
use App\Settings\ProfileSettings;
use App\Settings\SeoSettings;
use Illuminate\Support\Facades\Storage;

/**
 * schema.org structured data (docs/ARCHITECTURE.md §6, ADR-0010): Person + WebSite + ProfessionalService on home,
 * BlogPosting on posts, CreativeWork on case studies (each with the Person embedded), CollectionPage on listings and
 * BreadcrumbList on detail pages. Built as plain arrays.
 */
final class JsonLd
{
    /**
     * The site owner. Embedded on every page that refers to them, so author and publisher always resolve (ADR-0010).
     *
     * @return array<string, mixed>
     */
    public static function person(ProfileSettings $profile): array
    {
        return array_filter([
            '@type' => 'Person',
            '@id' => self::personId(),
            'name' => $profile->name,
            'jobTitle' => $profile->title,
            'description' => $profile->tagline,
            'url' => Seo::absolute('/'),
            'image' => $profile->avatar_path ? Seo::absolute(Storage::disk('public')->url($profile->avatar_path)) : null,
            'email' => 'mailto:'.($profile->email ?: config('brand.contact_email')),
            'address' => self::address($profile->location_public),
            'knowsAbout' => self::knowsAbout() ?: null,
            'areaServed' => config('brand.area_served') ?: null,
            'sameAs' => array_map(fn (array $s): string => $s['url'], $profile->socials) ?: null,
        ]);
    }

    /**
     * The freelance business behind the site, with its services as an offer catalogue (home page).
     *
     * @param  iterable<Service>  $services
     * @return array<string, mixed>
     */
    public static function professionalService(SeoSettings $seo, ProfileSettings $profile, iterable $services): array
    {
        $offers = [];
        foreach ($services as $service) {
            $offers[] = ['@type' => 'Offer', 'itemOffered' => array_filter([
                '@type' => 'Service', 'name' => $service->title, 'description' => $service->summary,
            ])];
        }

        return array_filter([
            '@type' => 'ProfessionalService',
            '@id' => Seo::absolute('/').'#business',
            'name' => $seo->site_title,
            'description' => $seo->default_description,
            'url' => Seo::absolute('/'),
            'founder' => ['@id' => self::personId()],
            'address' => self::address($profile->location_public),
            'areaServed' => config('brand.area_served') ?: null,
            'hasOfferCatalog' => $offers === [] ? null : ['@type' => 'OfferCatalog', 'name' => 'Services', 'itemListElement' => $offers],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(SeoSettings $seo): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => Seo::absolute('/').'#website',
            'name' => $seo->site_title,
            'url' => Seo::absolute('/'),
            'inLanguage' => config('brand.language'),
            'publisher' => ['@id' => self::personId()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blogPosting(Post $post, string $image): array
    {
        return array_filter([
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->seo_description ?: $post->excerpt,
            'url' => Seo::absolute(route('posts.show', $post, false)),
            'mainEntityOfPage' => Seo::absolute(route('posts.show', $post, false)),
            'image' => $image,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => ($post->updated_at && $post->published_at && $post->updated_at->greaterThan($post->published_at) ? $post->updated_at : $post->published_at)?->toAtomString(),
            'inLanguage' => config('brand.language'),
            'author' => self::personRef(),
            'publisher' => self::personRef(),
            'isPartOf' => ['@id' => Seo::absolute('/').'#website'],
            'articleSection' => $post->category?->name,
            'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
            'timeRequired' => $post->reading_minutes ? 'PT'.$post->reading_minutes.'M' : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function creativeWork(Project $project, string $image): array
    {
        return array_filter([
            '@type' => 'CreativeWork',
            'name' => $project->title,
            'description' => $project->seo_description ?: $project->summary,
            'url' => Seo::absolute(route('projects.show', $project, false)),
            'image' => $image,
            'creator' => self::personRef(),
            'author' => self::personRef(),
            'dateCreated' => $project->year ? (string) $project->year : null,
            'datePublished' => $project->published_at?->toAtomString(),
            'inLanguage' => config('brand.language'),
            'keywords' => $project->tags->pluck('name')->implode(', ') ?: null,
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items  [name, path]
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => Seo::absolute($item[1]),
            ], $items, array_keys($items)),
        ];
    }

    /**
     * A listing page (/blog, /projects) as a CollectionPage holding an ItemList of its entries.
     *
     * @param  list<array{0: string, 1: string}>  $items  [name, path]
     * @return array<string, mixed>
     */
    public static function collection(string $name, string $path, array $items): array
    {
        return [
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => Seo::absolute($path),
            'inLanguage' => config('brand.language'),
            'isPartOf' => ['@id' => Seo::absolute('/').'#website'],
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => array_map(fn (array $item, int $i): array => [
                    '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'url' => Seo::absolute($item[1]),
                ], $items, array_keys($items)),
            ],
        ];
    }

    private static function personId(): string
    {
        return Seo::absolute('/').'#person';
    }

    /** @return array<string, string> a reference that still names the person when read on its own */
    private static function personRef(): array
    {
        $profile = app(ProfileSettings::class);

        return ['@type' => 'Person', '@id' => self::personId(), 'name' => $profile->name, 'url' => Seo::absolute('/')];
    }

    /**
     * "Sylhet, Bangladesh" → locality "Sylhet", country "Bangladesh"; a single part stays the locality.
     *
     * @return array<string, string>|null
     */
    private static function address(?string $location): ?array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', (string) $location))));
        if ($parts === []) {
            return null;
        }
        $country = count($parts) > 1 ? array_pop($parts) : null;

        return array_filter(['@type' => 'PostalAddress', 'addressLocality' => implode(', ', $parts), 'addressCountry' => $country]);
    }

    /** @return list<string> the most used tags across published projects, experience and posts */
    private static function knowsAbout(): array
    {
        return Tag::query()
            ->withCount(['projects', 'experiences', 'posts'])
            ->get()
            ->sortByDesc(fn (Tag $t): int => (int) $t->projects_count + (int) $t->experiences_count + (int) $t->posts_count)
            ->take(12)->pluck('name')->values()->all();
    }

    /**
     * One script body for a page: a @graph, safe to place inside <script> (no "</" can close it).
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function encode(array $nodes): string
    {
        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }
}
