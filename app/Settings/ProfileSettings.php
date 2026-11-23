<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class ProfileSettings extends Settings
{
    public string $name;

    public string $title;

    public string $tagline;

    public string $about_md;

    public string $about_html;

    public ?string $location_public;

    public ?string $email;

    /** @phpstan-var list<array{network: string, url: string}> */
    public array $socials;

    public ?string $cv_path;

    public ?string $avatar_path;

    public static function group(): string
    {
        return 'profile';
    }
}
