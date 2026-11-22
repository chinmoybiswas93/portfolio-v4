<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ChangeSummary;
use App\Mcp\ContentCatalog;
use App\Mcp\ContentRules;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save-content')]
#[Description('Create (no id) or update (with id) a post, project, service, experience entry, category or tag. Text fields are Markdown (body_md, problem_md, approach_md, results_md, description_md); HTML is not accepted. Use status "draft" to save without publishing. Tags are names; the post category is a name. Returns the saved item.')]
final class SaveContent extends ScopedTool
{
    public static function scope(): string
    {
        return 'content:write';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(ContentCatalog::types())->required(),
            'id' => $schema->integer()->description('Omit to create; give to update'),
            'fields' => $schema->object()->required()->description('Fields to set, e.g. {"title": "…", "body_md": "…", "status": "draft", "tags": ["WordPress"]}'),
        ];
    }

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'type' => ['required', 'in:'.implode(',', ContentCatalog::types())],
            'id' => ['nullable', 'integer'],
            'fields' => ['required', 'array'],
        ]);
        $type = (string) $input['type'];
        $creating = ! isset($input['id']);
        $user = $request->user();

        $class = ContentCatalog::TYPES[$type]['model'];
        $model = $creating ? new $class : $class::query()->find($input['id']);
        if ($model === null) {
            return Response::error("No {$type} with id {$input['id']}.");
        }
        if (! $user instanceof User || Gate::forUser($user)->denies($creating ? 'create' : 'update', $creating ? $class : $model)) {
            return Response::error('Not allowed.');
        }

        $rules = ContentRules::for($type, $creating);
        $unknown = array_diff(array_keys((array) $input['fields']), array_keys($rules));
        if ($unknown !== []) {
            return Response::error('These fields cannot be set: '.implode(', ', $unknown).'.');
        }
        $validator = Validator::make((array) $input['fields'], $rules);
        if ($validator->fails()) {
            return Response::error('Not saved: '.implode(' ', $validator->errors()->all()));
        }
        $data = $validator->validated();
        $tags = $data['tags'] ?? null;
        $categoryName = array_key_exists('category', $data) ? $data['category'] : false;
        unset($data['tags'], $data['category']);

        $before = $model->exists ? $model->only(array_keys($data)) : [];
        DB::transaction(function () use ($model, $data, $tags, $categoryName): void {
            $model->fill($data);
            if ($model instanceof Post && $categoryName !== false) {
                $model->category_id = $categoryName === null ? null : Category::query()->firstOrCreate(
                    ['slug' => Str::slug((string) $categoryName)],
                    ['name' => trim((string) $categoryName)],
                )->getKey();
            }
            $model->save();
            if (is_array($tags) && method_exists($model, 'syncTagNames')) {
                $model->syncTagNames(array_values(array_map('strval', $tags)));
            }
        });

        $model->refresh();
        $changes = ChangeSummary::diff($before, $model->only(array_keys($data)));
        if (is_array($tags)) {
            $changes['tags'] = ['before' => null, 'after' => implode(', ', $tags)];
        }
        $this->audit($type, $model->getKey(), (string) $model->getAttribute(ContentCatalog::TYPES[$type]['title']), ['action' => $creating ? 'created' : 'updated', 'fields' => $changes]);

        return Response::json(['saved' => $creating ? 'created' : 'updated'] + ContentCatalog::detail($type, $model->load(ContentCatalog::TYPES[$type]['relations'])));
    }
}
