<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Client;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Approves an app with the scopes the owner ticked (not necessarily the ones it asked for). The ticked set is
 * written into the pending authorization request and stored on the client; ApprovedScopeRepository then
 * issues exactly that set on every token (ADR-0009 §2). Passport's own approve() does the rest.
 */
final class ApproveMcpAuthorizationController extends ApproveAuthorizationController
{
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $allowed = array_keys((array) config('mcp-server.scopes'));
        $ticked = array_values(array_intersect($allowed, array_filter((array) $request->input('scopes', []), 'is_string')));
        if ($ticked === []) {
            throw ValidationException::withMessages(['scopes' => 'Tick at least one permission, or deny the request.']);
        }
        $granted = [Registrar::OAUTH_SCOPE, ...$ticked];

        // Read the pending request with Passport's own (token-checked) reader, set the ticked scopes, put it back
        // for Passport's approve() to complete. A wrong auth_token throws here, before anything is stored.
        $authToken = (string) $request->input('auth_token');
        $authRequest = $this->getAuthRequestFromSession($request);
        $authRequest->setScopes(array_map(fn (string $id): Scope => new Scope($id), $granted));
        $request->session()->put('authToken', $authToken);
        $request->session()->put('authRequest', serialize($authRequest));

        $client = Client::query()->find($authRequest->getClient()->getIdentifier());
        if ($client instanceof Client) {
            $client->forceFill(['scopes' => $granted])->save();
        }

        return parent::approve($request, $psrResponse);
    }
}
