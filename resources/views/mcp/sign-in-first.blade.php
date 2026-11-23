{{-- Shown to a signed-out visitor at /oauth/authorize. It never links to the admin: its path is private. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in first · {{ config('brand.admin_name') }}</title>
    <style>
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0F172A; color: #B4C0D1; font: 14px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        main { width: 100%; max-width: 480px; padding: 24px 16px; box-sizing: border-box; }
        .card { background: #111C33; border: 1px solid #1E293B; border-radius: 10px; padding: 18px 20px; }
        h1 { margin: 0 0 8px; color: #E2E8F0; font-size: 18px; font-weight: 600; }
        p { margin: 0 0 8px; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>Sign in to the admin first</h1>
        <p>An app is asking to connect to this site. Only the site owner can approve it.</p>
        <p>Sign in to the admin in this browser, then go back to the app and connect again.</p>
    </div>
</main>
</body>
</html>
