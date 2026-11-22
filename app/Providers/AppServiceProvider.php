<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MarkdownRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stable, short morph names for taggables and media (never class names in the database).
        Relation::enforceMorphMap([
            'experience' => Experience::class,
            'post' => Post::class,
            'project' => Project::class,
            'user' => User::class,
        ]);

        // Catch N+1 queries and silently discarded attributes outside production. (Not "missing attributes":
        // freshly created models legitimately lack database-defaulted columns until refreshed.)
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
