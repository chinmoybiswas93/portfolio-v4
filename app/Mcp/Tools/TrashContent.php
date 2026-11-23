<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ContentCatalog;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('trash-content')]
#[Description('Move a post, project, service, category or tag to the trash, or restore it from the trash. Trashed items disappear from the public site. Nothing is ever deleted permanently through this server.')]
final class TrashContent extends ScopedTool
{
    /** Types with a trash (experience has none). */
    public const TYPES = ['post', 'project', 'service', 'category', 'tag'];

    public static function scope(): string
    {
        return 'content:trash';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(self::TYPES)->required(),
            'id' => $schema->integer()->required(),
            'action' => $schema->string()->enum(['trash', 'restore'])->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'id' => ['required', 'integer'],
            'action' => ['required', 'in:trash,restore'],
        ]);
        $class = ContentCatalog::TYPES[$data['type']]['model'];
        if (! in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            return Response::error('This type has no trash.');
        }
        $model = $class::withTrashed()->find($data['id']);
        $user = $request->user();
        if ($model === null) {
            return Response::error('Not found.');
        }
        $ability = $data['action'] === 'trash' ? 'delete' : 'restore';
        if (! $user instanceof User || Gate::forUser($user)->denies($ability, $model)) {
            return Response::error('Not allowed.');
        }

        $data['action'] === 'trash' ? $model->delete() : $model->restore();
        $label = (string) $model->getAttribute(ContentCatalog::TYPES[$data['type']]['title']);
        $this->audit($data['type'], $model->getKey(), $label, ['action' => $data['action'] === 'trash' ? 'trashed' : 'restored']);

        return Response::text(($data['action'] === 'trash' ? 'Moved to the trash: ' : 'Restored: ').$label);
    }
}
