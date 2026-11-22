<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;

/**
 * Deletes dynamically registered MCP apps the owner never approved (ADR-0009 §2). An approved app has scopes
 * beyond mcp:use stored on it by the consent screen.
 */
final class PruneUnapprovedMcpClients extends Command
{
    protected $signature = 'mcp:prune-clients';

    protected $description = 'Delete MCP apps that registered but were never approved';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('mcp-server.unapproved_client_days'));
        $deleted = 0;

        Client::query()->where('created_at', '<', $cutoff)->each(function (Client $client) use (&$deleted): void {
            $scopes = $client->getAttribute('scopes');
            $approved = is_array($scopes) && array_diff($scopes, [Registrar::OAUTH_SCOPE]) !== [];
            if (! $approved && ! $client->tokens()->exists()) {
                $client->delete();
                $deleted++;
            }
        });

        $this->info("{$deleted} unapproved app(s) deleted.");

        return self::SUCCESS;
    }
}
