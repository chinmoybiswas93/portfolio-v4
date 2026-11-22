<?php

declare(strict_types=1);

/*
 * laravel/mcp settings (ADR-0009). The package default allows redirects to any domain; this restricts them to
 * the assistants the owner uses, plus loopback for CLI tools (Claude Code, Codex).
 */
return [
    'redirect_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('MCP_REDIRECT_DOMAINS',
        'https://claude.ai,https://claude.com,https://chatgpt.com,https://chat.openai.com,http://localhost,http://127.0.0.1'
    ))))),

    // No private-use URI schemes (desktop apps) unless added deliberately.
    'custom_schemes' => [],

    'authorization_server' => null,

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

    // This app is an MCP server only; it connects to no external MCP servers.
    'clients' => [],
];
