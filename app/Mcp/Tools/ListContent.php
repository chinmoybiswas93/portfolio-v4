<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ContentCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list-content')]
#[Description('List posts, projects, services, experience, categories or tags, newest first. Optional text search. Returns ids, titles, slugs and status; use get-content for the full Markdown.')]
final class ListContent extends ScopedTool
{
    public static function scope(): string
    {
        return 'content:read';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(ContentCatalog::types())->required()->description('Content type'),
            'search' => $schema->string()->description('Text to look for in the title (or name)'),
            'page' => $schema->integer()->min(1)->default(1),
            'per_page' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', ContentCatalog::types())],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $type = (string) $data['type'];
        $titleField = ContentCatalog::TYPES[$type]['title'];

        $page = ContentCatalog::query($type)
            ->when(filled($data['search'] ?? null), fn (Builder $q) => $q->where($titleField, 'like', '%'.addcslashes((string) $data['search'], '%_\\').'%'))
            ->reorder()->latest('updated_at')
            ->paginate((int) ($data['per_page'] ?? 20), ['*'], 'page', (int) ($data['page'] ?? 1));

        $this->audit($type, null, 'list');

        return Response::json([
            'items' => collect($page->items())->map(fn ($m) => ContentCatalog::summary($type, $m))->values()->all(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }
}
