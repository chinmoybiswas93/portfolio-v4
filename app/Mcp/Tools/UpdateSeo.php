<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ChangeSummary;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Settings\SeoSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update-seo')]
#[Description('Set the SEO title and description of a post or project, or the site-wide SEO defaults (target "site": site_title, default_description).')]
final class UpdateSeo extends ScopedTool
{
    public static function scope(): string
    {
        return 'seo:write';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'target' => $schema->string()->enum(['post', 'project', 'site'])->required(),
            'id' => $schema->integer()->description('Post or project id'),
            'seo_title' => $schema->string()->description('Up to 70 characters'),
            'seo_description' => $schema->string()->description('Up to 160 characters'),
            'site_title' => $schema->string(),
            'default_description' => $schema->string(),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'target' => ['required', 'in:post,project,site'],
            'id' => ['required_unless:target,site', 'nullable', 'integer'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'site_title' => ['required_if:target,site', 'nullable', 'string', 'max:70'],
            'default_description' => ['nullable', 'string', 'max:160'],
        ]);

        if ($data['target'] === 'site') {
            $seo = app(SeoSettings::class);
            $before = ['site_title' => $seo->site_title, 'default_description' => $seo->default_description];
            $seo->site_title = (string) $data['site_title'];
            if (array_key_exists('default_description', $data)) {
                $seo->default_description = (string) $data['default_description'];
            }
            $seo->save();
            $this->audit('settings', 'seo', 'SEO defaults', ChangeSummary::diff($before, ['site_title' => $seo->site_title, 'default_description' => $seo->default_description]));

            return Response::text('SEO defaults saved.');
        }

        $class = $data['target'] === 'post' ? Post::class : Project::class;
        $model = $class::query()->find($data['id']);
        $user = $request->user();
        if ($model === null) {
            return Response::error('Not found.');
        }
        if (! $user instanceof User || Gate::forUser($user)->denies('update', $model)) {
            return Response::error('Not allowed.');
        }
        $fields = array_intersect_key($data, array_flip(['seo_title', 'seo_description']));
        $before = $model->only(array_keys($fields));
        $model->fill($fields)->save();
        $this->audit($data['target'], $model->getKey(), (string) $model->getAttribute('title'), ChangeSummary::diff($before, $model->only(array_keys($fields))));

        return Response::text('SEO saved for "'.$model->getAttribute('title').'".');
    }
}
