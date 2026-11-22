<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Everyday MCP switches (Settings → MCP tab, slice 3.9c). MCP_ENABLED in .env is the hard switch above these.
 */
class McpSettings extends Settings
{
    public bool $enabled;

    public bool $registration_open;

    public static function group(): string
    {
        return 'mcp';
    }
}
