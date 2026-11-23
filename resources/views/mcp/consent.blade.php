@php
    /** @var \Laravel\Passport\Client $client */
    $descriptions = (array) config('mcp-server.scopes');
    $isNew = ! is_array($client->scopes) || array_diff($client->scopes, [\Laravel\Mcp\Server\Registrar::OAUTH_SCOPE]) === [];
    $redirects = (array) ($client->redirect_uris ?? []);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Allow {{ $client->name }}? · {{ config('brand.admin_name') }}</title>
    <style>
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0F172A; color: #94A3B8; font: 14px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        main { width: 100%; max-width: 480px; padding: 24px 16px; box-sizing: border-box; }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; color: #E2E8F0; font-weight: 600; }
        .card { background: #111C33; border: 1px solid #1E293B; border-radius: 10px; padding: 18px 20px; }
        h1 { margin: 0; color: #E2E8F0; font-size: 18px; font-weight: 600; }
        .muted { font-size: 12.5px; }
        dl { display: grid; grid-template-columns: 110px 1fr; gap: 4px 12px; margin: 14px 0; font-size: 12.5px; }
        dt { color: #8291A8; } dd { margin: 0; color: #CBD5E1; overflow-wrap: anywhere; }
        code { color: #E2E8F0; }
        .badge { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 999px; background: rgba(251, 191, 36, .14); color: #FCD34D; font-size: 11.5px; font-weight: 500; }
        ul { list-style: none; margin: 0; padding: 0; }
        li label { display: flex; gap: 10px; padding: 9px 0; border-top: 1px solid #1E293B; cursor: pointer; }
        input[type=checkbox] { accent-color: #2DD4BF; margin-top: 3px; }
        .never { margin: 14px 0; padding: 10px 12px; border-radius: 8px; background: rgba(148, 163, 184, .08); font-size: 12px; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; }
        button { height: 34px; padding: 0 14px; border-radius: 8px; font: inherit; font-size: 13px; cursor: pointer; }
        .deny { border: 1px solid #334155; background: transparent; color: #E2E8F0; }
        .allow { border: 0; background: #2DD4BF; color: #0B1220; font-weight: 600; }
        button:focus-visible, input:focus-visible { outline: 2px solid #2DD4BF; outline-offset: 2px; }
        .error { color: #FCA5A5; font-size: 12.5px; margin: 8px 0 0; }
        .hint { margin-top: 12px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
<main>
    <div class="brand">
        <img src="{{ \App\Support\BrandAssets::logoUrl('brand/logo-mark-light.svg') }}" width="24" height="27" alt="">
        <span>{{ config('brand.admin_name') }} · Admin</span>
    </div>
    <section class="card" aria-labelledby="consent-title">
        <h1 id="consent-title">Allow {{ $client->name }} to access your site?</h1>
        <p class="muted">Signed in as {{ $user->email }} with two-factor authentication.</p>

        <dl>
            <dt>App</dt><dd>{{ $client->name }}@if ($isNew)<span class="badge">New app</span>@endif</dd>
            <dt>Registered</dt><dd>{{ $client->created_at?->format('j M Y, H:i') }}</dd>
            <dt>Returns to</dt><dd>@foreach ($redirects as $uri)<code>{{ $uri }}</code>@if (! $loop->last)<br>@endif @endforeach</dd>
        </dl>

        <form method="post" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <p style="margin: 0 0 2px; color: #E2E8F0; font-weight: 500;">Permissions</p>
            <p class="muted" style="margin: 0 0 6px;">Untick anything you don't want to give. You can revoke access later in Connected apps.</p>
            <ul>
                @foreach ($descriptions as $id => $description)
                    <li><label><input type="checkbox" name="scopes[]" value="{{ $id }}" checked><span><code>{{ $id }}</code><br><span class="muted">{{ $description }}</span></span></label></li>
                @endforeach
            </ul>
            @error('scopes')<p class="error" role="alert">{{ $message }}</p>@enderror

            <p class="never">Never available to apps: payment details, invoicing settings, issuing or emailing invoices, security and two-factor settings, permanent deletes.</p>

            <div class="actions">
                <button type="submit" class="deny" form="deny-form">Deny</button>
                <button type="submit" class="allow">Allow selected</button>
            </div>
        </form>
        <form id="deny-form" method="post" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
        </form>
    </section>
    <p class="hint">Only approve apps you started connecting yourself just now.</p>
</main>
</body>
</html>
