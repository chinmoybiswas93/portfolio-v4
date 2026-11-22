<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Settings\ProfileSettings;
use Illuminate\Contracts\View\View;

final class HomeController
{
    public function __invoke(ProfileSettings $profile): View
    {
        return view('site.home', [
            'profile' => $profile,
            'services' => Service::query()->published()->get(),
            'experiences' => Experience::query()->published()->with('tags')->get(),
            'projects' => Project::query()->published()->where('is_featured', true)->with(['tags', 'media'])->limit(4)->get(),
            'posts' => Post::query()->published()->limit(3)->get(),
        ]);
    }
}
