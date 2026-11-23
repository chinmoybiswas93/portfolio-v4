<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\Post;
use App\Models\Project;
use App\Settings\ProfileSettings;
use App\Settings\SeoSettings;

/**
 * schema.org structured data (docs/ARCHITECTURE.md §6): Person + WebSite on home, BlogPosting on posts,
 * CreativeWork on case studies, BreadcrumbList on detail pages. Built as plain arrays.
 */
final class JsonLd
{
    /**
     * @return array<string, mixed>
     */
    public static function person(ProfileSettings $profile): array
    {
        return array_filter([
            '@type' => 'Person',
            '@id' => Seo::absolute('/').'#person',
            'name' => $profile->name,
            'jobTitle' => $profile->title,
            'description' => $profile->tagline,
            'url' => Seo::absolute('/'),
            'email' => 'mailto:'.($profile->email ?: 'hello@chinmoybiswas.com'),
            'address' => $profile->location_public ? ['@type' => 'PostalAddress', 'addressLocality' => $profile->location_public] : null,
            'sameAs' => array_map(fn (array $s): string => $s['url'], $profile->socials) ?: null,
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
            'publisher' => ['@id' => Seo::absolute('/').'#person'],
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
            'dateModified' => $post->updated_at?->toAtomString(),
            'author' => ['@id' => Seo::absolute('/').'#person'],
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
            'creator' => ['@id' => Seo::absolute('/').'#person'],
            'dateCreated' => $project->year ? (string) $project->year : null,
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
