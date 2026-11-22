<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\Client;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * Tokens carry exactly the scopes the owner approved for that app on the consent screen (stored on the
 * client), whatever the app asks for. Auto-approved re-authorisations therefore keep the approved set, and a
 * revoked app gets nothing (ADR-0009 §2).
 */
final class ApprovedScopeRepository extends ScopeRepository
{
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, ?string $userIdentifier = null, ?string $authCodeId = null): array
    {
        if ($grantType === 'authorization_code') {
            $client = Client::query()->find($clientEntity->getIdentifier());
            $scopesAttr = $client instanceof Client ? $client->getAttribute('scopes') : null;
            $approved = $client instanceof Client && ! $client->revoked && is_array($scopesAttr) ? $scopesAttr : [];
            $scopes = array_map(fn (string $id) => new Scope($id), array_values($approved));
        }

        return parent::finalizeScopes($scopes, $grantType, $clientEntity, $userIdentifier, $authCodeId);
    }
}
