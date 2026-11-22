<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ContentCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-content')]
#[Description('Get one post, project, service, experience entry, category or tag by id or slug, with its Markdown fields, tags and SEO fields.')]
final class GetContent extends ScopedTool
{
    public static function scope(): string
    {
        return 'content:read';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(ContentCatalog::types())->required(),
            'id' => $schema->integer()->description('Numeric id'),
            'slug' => $schema->string()->description('Slug, when the id is not known'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', ContentCatalog::types())],
            'id' => ['nullable', 'integer', 'required_without:slug'],
            'slug' => ['nullable', 'string', 'max:255', 'required_without:id'],
        ]);
        $type = (string) $data['type'];
        $query = ContentCatalog::query($type);
        $model = isset($data['id']) ? $query->find($data['id']) : $query->where('slug', $data['slug'])->first();

        if ($model === null) {
            return Response::error("No {$type} found with that ".(isset($data['id']) ? 'id' : 'slug').'.');
        }
        $this->audit($type, $model->getKey(), (string) $model->getAttribute(ContentCatalog::TYPES[$type]['title']));

        return Response::json(ContentCatalog::detail($type, $model));
    }
}
