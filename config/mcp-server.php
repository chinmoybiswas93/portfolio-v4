<?php

declare(strict_types=1);

/*
 * Portfolio MCP server (ADR-0009). MCP_ENABLED is the hard switch (off unless set); the Settings → MCP tab is
 * the everyday switch. Both must be on for /mcp to answer.
 */
return [
    'enabled' => (bool) env('MCP_ENABLED', false),

    'rate' => [
        'per_minute' => (int) env('MCP_RATE_PER_MINUTE', 60),
        'per_day' => (int) env('MCP_RATE_PER_DAY', 1000),
    ],

    'audit_retention_days' => 90,

    // Dynamically registered clients that were never approved are deleted after this many days.
    'unapproved_client_days' => 7,

    /*
     * Scopes (owner, 2026-11-22). mcp:use is added by laravel/mcp and required on every token.
     */
    'scopes' => [
        'content:read' => 'Read posts, projects, services, experience, categories and tags',
        'content:write' => 'Create and edit them in Markdown, including publishing',
        'content:trash' => 'Move items to the trash and restore them (never deletes permanently)',
        'seo:write' => 'Edit SEO titles, descriptions and SEO settings',
        'media:upload' => 'Upload images (JPEG, PNG, WebP; re-encoded, metadata removed)',
        'invoices:read' => 'Read invoices and clients',
        'invoices:draft' => 'Create and edit draft invoices',
        'settings:read' => 'Read profile and SEO settings',
        'settings:write' => 'Edit profile and SEO settings',
    ],
];
