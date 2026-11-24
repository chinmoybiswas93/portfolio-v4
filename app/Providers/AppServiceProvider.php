<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\FeatureDisabled;
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
use App\Support\Seo\NotifyIndexNow;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;
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

        // Leaked-password check (ADR-0010): a k-anonymity range request with a short timeout; if the service is
        // down the check passes (NotPwnedVerifier fails open), so a sign-in setting never blocks on it.
        $this->app->singleton(UncompromisedVerifier::class, fn ($app): NotPwnedVerifier => new NotPwnedVerifier($app->make(HttpFactory::class), 5));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Links and asset URLs always use APP_URL, never the request's Host: cached pages are shared by everyone.
        if (! $this->app->environment('local', 'testing')) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme('https');
        }

        // Admin passwords (ADR-0010): at least 12 characters and not in known breach lists.
        Password::defaults(fn (): Password => Password::min(12)->uncompromised());

        // Filament export/import downloads are not used (no exports tables): answer 404 instead of a 500.
        $this->app->make(Router::class)->middlewareGroup('filament.actions', [FeatureDisabled::class]);

        // Stable, short morph names for taggables and media (never class names in the database).
        Relation::enforceMorphMap([
            'experience' => Experience::class,
            'post' => Post::class,
            'project' => Project::class,
            'service' => Service::class,
            'user' => User::class,
        ]);

        // Any change to public content or settings clears the full-page cache (ADR-0008).
        foreach ([Service::class, Experience::class, Project::class, Post::class, Category::class, Tag::class, Redirect::class, Media::class] as $model) {
            $model::observe(FlushPageCache::class);
        }
        Event::listen(SettingsSaved::class, fn () => app(PageCache::class)->flush());

        // Live posts, case studies and service pages ping IndexNow when saved (ADR-0010).
        Post::observe(NotifyIndexNow::class);
        Project::observe(NotifyIndexNow::class);
        Service::observe(NotifyIndexNow::class);

        // Catch N+1 queries and silently discarded attributes outside production. (Not "missing attributes":
        // freshly created models legitimately lack database-defaulted columns until refreshed.)
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
