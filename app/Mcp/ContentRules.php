<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Enums\PostStatus;
use App\Enums\PublishStatus;

/**
 * Validation for MCP content writes, mirroring the admin form limits (ADR-0009 §4). Only the fields listed here
 * can be written; *_html, ids, media and timestamps other than published_at are never accepted.
 */
final class ContentRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function for(string $type, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $slug = ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];

        return match ($type) {
            'post' => [
                'title' => [$required, 'string', 'max:255'],
                'slug' => $slug,
                'excerpt' => ['nullable', 'string', 'max:500'],
                'body_md' => [$required, 'string', 'max:200000'],
                'status' => ['sometimes', 'in:'.implode(',', array_column(PostStatus::cases(), 'value'))],
                'published_at' => ['nullable', 'date'],
                'is_featured' => ['sometimes', 'boolean'],
                'category' => ['nullable', 'string', 'max:100'],
                'tags' => ['sometimes', 'array', 'max:20'],
                'tags.*' => ['string', 'max:64'],
                'seo_title' => ['nullable', 'string', 'max:60'],
                'seo_description' => ['nullable', 'string', 'max:160'],
                // Answer-ready fields (ADR-0010, slice 8c), with the admin's limits.
                'takeaways' => ['nullable', 'array', 'max:5'],
                'takeaways.*' => ['string', 'max:160'],
                'faq' => ['nullable', 'array', 'max:6'],
                'faq.*.question' => ['required', 'string', 'max:160'],
                'faq.*.answer' => ['required', 'string', 'max:800'],
                'reviewed_at' => ['nullable', 'date', 'before_or_equal:today'],
            ],
            'project' => [
                'title' => [$required, 'string', 'max:255'],
                'slug' => $slug,
                'summary' => ['nullable', 'string', 'max:500'],
                'client_name' => ['nullable', 'string', 'max:255'],
                'sector' => ['nullable', 'string', 'max:255'],
                'role' => ['nullable', 'string', 'max:255'],
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
                'started_on' => ['nullable', 'date'],
                'ended_on' => ['nullable', 'date'],
                'has_case_study' => ['sometimes', 'boolean'],
                'problem_md' => ['nullable', 'string', 'max:100000'],
                'approach_md' => ['nullable', 'string', 'max:100000'],
                'results_md' => ['nullable', 'string', 'max:100000'],
                'metrics' => ['sometimes', 'array', 'max:6'],
                'metrics.*.label' => ['required', 'string', 'max:60'],
                'metrics.*.value' => ['required', 'string', 'max:30'],
                'live_url' => ['nullable', 'url:https,http', 'max:512'],
                'repo_url' => ['nullable', 'url:https,http', 'max:512'],
                'status' => ['sometimes', 'in:'.implode(',', array_column(PublishStatus::cases(), 'value'))],
                'is_featured' => ['sometimes', 'boolean'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'tags' => ['sometimes', 'array', 'max:30'],
                'tags.*' => ['string', 'max:64'],
                'seo_title' => ['nullable', 'string', 'max:60'],
                'seo_description' => ['nullable', 'string', 'max:160'],
            ],
            'service' => [
                'title' => [$required, 'string', 'max:255'],
                'slug' => $slug,
                'summary' => ['nullable', 'string', 'max:500'],
                'body_md' => ['nullable', 'string', 'max:100000'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'is_published' => ['sometimes', 'boolean'],
            ],
            'experience' => [
                'position' => [$required, 'string', 'max:255'],
                'company' => [$required, 'string', 'max:255'],
                'company_url' => ['nullable', 'url:https,http', 'max:512'],
                'location' => ['nullable', 'string', 'max:255'],
                'started_on' => [$required, 'date'],
                'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
                'description_md' => ['nullable', 'string', 'max:100000'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'is_published' => ['sometimes', 'boolean'],
                'tags' => ['sometimes', 'array', 'max:30'],
                'tags.*' => ['string', 'max:64'],
            ],
            'category' => [
                'name' => [$required, 'string', 'max:100'],
                'slug' => $slug,
                'description' => ['nullable', 'string', 'max:500'],
            ],
            'tag' => [
                'name' => [$required, 'string', 'max:64'],
                'slug' => $slug,
            ],
            default => [],
        };
    }
}
