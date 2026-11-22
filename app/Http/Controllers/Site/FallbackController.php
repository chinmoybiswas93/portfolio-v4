<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Unknown paths: an admin-managed redirect (counted) if one exists, otherwise the 404 page.
 */
final class FallbackController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $path = '/'.ltrim($request->path(), '/');
        $redirect = Redirect::query()->where('from_path', $path)->orWhere('from_path', rtrim($path, '/'))->first();
        abort_if($redirect === null, 404);

        Redirect::query()->whereKey($redirect->getKey())->increment('hits');

        return redirect()->to($redirect->to_path, in_array($redirect->status_code, [301, 302], true) ? $redirect->status_code : 301);
    }
}
