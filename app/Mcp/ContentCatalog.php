<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\Category;
use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Content types exposed to MCP and the fields each returns. Markdown fields (*_md) only; rendered *_html is
 * never exposed or accepted (ADR-0009 §4).
 */
final class ContentCatalog
{
    /** @var array<string, array{model: class-string<Model>, title: string, fields: list<string>, relations: list<string>}> */
    public const TYPES = [
        'post' => ['model' => Post::class, 'title' => 'title', 'fields' => ['title', 'slug', 'excerpt', 'body_md', 'status', 'published_at', 'is_featured', 'category_id', 'seo_title', 'seo_description', 'reading_minutes', 'updated_at'], 'relations' => ['category', 'tags']],
        'project' => ['model' => Project::class, 'title' => 'title', 'fields' => ['title', 'slug', 'summary', 'client_name', 'sector', 'role', 'made_at', 'year', 'started_on', 'ended_on', 'problem_md', 'approach_md', 'results_md', 'metrics', 'live_url', 'repo_url', 'is_featured', 'has_case_study', 'status', 'published_at', 'sort_order', 'seo_title', 'seo_description', 'updated_at'], 'relations' => ['tags']],
        'service' => ['model' => Service::class, 'title' => 'title', 'fields' => ['title', 'slug', 'summary', 'body_md', 'sort_order', 'is_published', 'updated_at'], 'relations' => []],
        'experience' => ['model' => Experience::class, 'title' => 'position', 'fields' => ['company', 'company_url', 'position', 'location', 'started_on', 'ended_on', 'description_md', 'sort_order', 'is_published', 'updated_at'], 'relations' => ['tags']],
        'category' => ['model' => Category::class, 'title' => 'name', 'fields' => ['name', 'slug', 'description', 'updated_at'], 'relations' => []],
        'tag' => ['model' => Tag::class, 'title' => 'name', 'fields' => ['name', 'slug', 'updated_at'], 'relations' => []],
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * @return Builder<covariant Model>
     */
    public static function query(string $type): Builder
    {
        $class = self::TYPES[$type]['model'];

        return $class::query()->with(self::TYPES[$type]['relations']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(string $type, Model $model): array
    {
        $title = self::TYPES[$type]['title'];

        return array_filter([
            'type' => $type,
            'id' => $model->getKey(),
            'title' => $model->getAttribute($title),
            'slug' => $model->getAttribute('slug'),
            'status' => self::status($model),
            'updated_at' => $model->getAttribute('updated_at')?->toAtomString(),
        ], fn (mixed $v): bool => $v !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(string $type, Model $model): array
    {
        $data = ['type' => $type, 'id' => $model->getKey()];
        foreach (self::TYPES[$type]['fields'] as $field) {
            $value = $model->getAttribute($field);
            $data[$field] = $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value);
        }
        if (in_array('tags', self::TYPES[$type]['relations'], true)) {
            $data['tags'] = $model->getRelationValue('tags')?->pluck('name')->values()->all() ?? [];
        }
        if (in_array('category', self::TYPES[$type]['relations'], true)) {
            $data['category'] = $model->getRelationValue('category')?->getAttribute('name');
        }

        return $data;
    }

    private static function status(Model $model): ?string
    {
        $status = $model->getAttribute('status');
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }
        if ($model->getAttribute('is_published') !== null) {
            return $model->getAttribute('is_published') ? 'published' : 'hidden';
        }

        return null;
    }
}
