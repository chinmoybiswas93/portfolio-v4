<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Settings\ProfileSettings;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the CV from the private disk at a stable URL (ADR-0005).
 */
final class CvController
{
    public function __invoke(ProfileSettings $profile): StreamedResponse
    {
        $path = $profile->cv_path;
        abort_if($path === null || $path === '' || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, str($profile->name)->slug()->append('-CV.pdf')->toString(), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'public, max-age=3600',
        ], 'inline');
    }
}
