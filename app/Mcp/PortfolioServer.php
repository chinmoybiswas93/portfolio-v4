<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Mcp\Tools\GetContent;
use App\Mcp\Tools\GetInvoice;
use App\Mcp\Tools\GetSettings;
use App\Mcp\Tools\ListClients;
use App\Mcp\Tools\ListContent;
use App\Mcp\Tools\ListInvoices;
use App\Mcp\Tools\ScopedTool;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use ReflectionClass;

#[Name('Chinmoy Biswas portfolio')]
#[Version('1.0.0')]
#[Instructions('Read and (with permission) edit the portfolio content in Markdown. Payment, invoicing and security settings are never available; deletes go to the trash; only draft invoices can be edited.')]
final class PortfolioServer extends Server
{
    /** @var list<class-string<ScopedTool>> */
    public const TOOLS = [
        ListContent::class,
        GetContent::class,
        ListInvoices::class,
        GetInvoice::class,
        ListClients::class,
        GetSettings::class,
    ];

    protected array $tools = self::TOOLS;

    /** The scope a tool needs, by its MCP name; null for unknown tools (the server answers "not found"). */
    public static function scopeFor(string $toolName): ?string
    {
        foreach (self::TOOLS as $class) {
            $attribute = (new ReflectionClass($class))->getAttributes(Name::class)[0] ?? null;
            $name = $attribute !== null ? $attribute->newInstance()->value : Str::kebab(class_basename($class));
            if ($name === $toolName) {
                return $class::scope();
            }
        }

        return null;
    }
}
