<?php

declare(strict_types=1);

/*
 * Branding defaults. The repository is generic: the real identity lives in the Settings screens (database)
 * and in the server .env. Settings override these values at runtime where the app reads them through
 * App\Support\Brand; the admin and MCP names are read from here because they are needed before the
 * database is available.
 */
return [
    // Shown as the site/owner name when Settings → Profile has no name yet.
    'site_name' => env('BRAND_SITE_NAME', 'Your Name'),

    // Domain printed on generated share images, without the scheme.
    'domain' => env('BRAND_DOMAIN', 'example.com'),

    // Contact address used when Settings → Profile has no email.
    'contact_email' => env('BRAND_CONTACT_EMAIL', 'hello@example.com'),

    // Name in the admin panel header and on the MCP consent screens. Defaults to the site name.
    'admin_name' => env('BRAND_ADMIN_NAME', env('BRAND_SITE_NAME', 'Your Name')),

    // Name the MCP server reports to assistants. Defaults to "<site name> portfolio".
    'mcp_name' => env('BRAND_MCP_NAME', env('BRAND_SITE_NAME', 'Your Name').' portfolio'),
];
