<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Category;
use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\Tag;
use App\Models\User;
use App\Support\Cache\FlushPageCache;
use App\Support\Cache\PageCache;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

        // Any change to public content or settings clears the full-page cache (ADR-0008).
        foreach ([Service::class, Experience::class, Project::class, Post::class, Category::class, Tag::class, Redirect::class, Media::class] as $model) {
            $model::observe(FlushPageCache::class);
        }
        Event::listen(SettingsSaved::class, fn () => app(PageCache::class)->flush());

        // Catch N+1 queries and silently discarded attributes outside production. (Not "missing attributes":
        // freshly created models legitimately lack database-defaulted columns until refreshed.)
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
