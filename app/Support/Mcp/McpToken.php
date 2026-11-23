<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;

/**
 * The current request's Passport access token (id and client), without ever exposing the token itself.
 */
final class McpToken
{
    public static function id(mixed $user): ?string
    {
        $token = self::token($user);

        return $token?->oauth_access_token_id;
    }

    public static function clientId(mixed $user): ?string
    {
        return self::token($user)?->oauth_client_id;
    }

    public static function clientName(mixed $user): ?string
    {
        $clientId = self::clientId($user);
        $client = $clientId !== null ? Client::query()->find($clientId) : null;

        return $client instanceof Client ? (string) $client->getAttribute('name') : null;
    }

    /**
     * @return AccessToken<mixed>|null
     */
    private static function token(mixed $user): ?AccessToken
    {
        if (! is_object($user) || ! method_exists($user, 'token')) {
            return null;
        }
        $token = $user->token();

        return $token instanceof AccessToken ? $token : null;
    }
}
