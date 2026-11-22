<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\McpAuditLog;
use App\Models\Post;
use App\Models\User;
use App\Settings\McpSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['mcp-server.enabled' => true]);
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
});

/**
 * @param  array<string, mixed>  $params
 */
function mcpCall(string $method, array $params = [], int $id = 1): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params];
}

function asMcpOwner(array $scopes): User
{
    $owner = adminWithMfa();
    Passport::actingAs($owner, ['mcp:use', ...$scopes], 'api');

    return $owner;
}

it('publishes OAuth discovery metadata with PKCE S256', function (): void {
    $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
        ->assertJsonPath('registration_endpoint', url('/oauth/register'));
    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->assertJsonPath('resource', url('/mcp'));
});

it('registers apps only with allowed redirect domains, and only while registration is open', function (): void {
    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->assertCreated();
    $this->postJson('/oauth/register', ['client_name' => 'CLI', 'redirect_uris' => ['http://localhost:53682/callback']])->assertCreated();
    $this->postJson('/oauth/register', ['client_name' => 'Evil', 'redirect_uris' => ['https://evil.example/callback']])->assertStatus(400);

    $settings = app(McpSettings::class);
    $settings->registration_open = false;
    $settings->save();
    $this->postJson('/oauth/register', ['client_name' => 'Late', 'redirect_uris' => ['https://claude.ai/cb']])->assertForbidden();
});

it('asks a guest for a token with the resource metadata pointer', function (): void {
    $this->postJson('/mcp', mcpCall('tools/list'))->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
});

it('answers 503 when MCP is switched off, by env or in Settings', function (): void {
    asMcpOwner(['content:read']);
    config(['mcp-server.enabled' => false]);
    $this->postJson('/mcp', mcpCall('tools/list'))->assertStatus(503);

    $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => 'x'])->assertStatus(503);

    config(['mcp-server.enabled' => true]);
    $settings = app(McpSettings::class);
    $settings->enabled = false;
    $settings->save();
    $this->postJson('/mcp', mcpCall('tools/list'))->assertStatus(503);
});

it('lists only the tools the token may use and refuses others with 403 insufficient_scope', function (): void {
    asMcpOwner(['content:read']);

    $names = collect($this->postJson('/mcp', mcpCall('tools/list'))->assertOk()->json('result.tools'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['list-content', 'get-content']);

    $this->postJson('/mcp', mcpCall('tools/call', ['name' => 'list-invoices', 'arguments' => []]))
        ->assertForbidden()->assertHeader('WWW-Authenticate', 'Bearer error="insufficient_scope", scope="invoices:read"');

    expect(McpAuditLog::query()->where('tool', 'list-invoices')->value('result'))->toBe('denied');
});

it('refuses tokens without mcp:use', function (): void {
    Passport::actingAs(adminWithMfa(), ['content:read'], 'api');

    $this->postJson('/mcp', mcpCall('tools/list'))->assertForbidden();
});

it('reads content as Markdown and records the call in the audit log', function (): void {
    asMcpOwner(['content:read']);
    $post = Post::factory()->create(['title' => 'Speed tips', 'body_md' => "## Hello\n\n**bold**"]);

    $list = $this->postJson('/mcp', mcpCall('tools/call', ['name' => 'list-content', 'arguments' => ['type' => 'post']]))->assertOk();
    expect($list->json('result.content.0.text'))->toContain('Speed tips');

    $detail = json_decode((string) $this->postJson('/mcp', mcpCall('tools/call', ['name' => 'get-content', 'arguments' => ['type' => 'post', 'id' => $post->id]]))
        ->assertOk()->json('result.content.0.text'), true);
    expect($detail['body_md'])->toBe("## Hello\n\n**bold**")->and($detail)->not->toHaveKey('body_html');

    $log = McpAuditLog::query()->where('tool', 'get-content')->firstOrFail();
    expect($log->result)->toBe('ok')->and($log->target_type)->toBe('post')->and($log->target_id)->toBe((string) $post->id)
        ->and($log->scope)->toBe('content:read')->and($log->getAttributes())->not->toHaveKey('token');
});

it('reads invoices only with invoices:read and never exposes payment settings', function (): void {
    asMcpOwner(['invoices:read', 'settings:read']);
    Invoice::factory()->create();

    $this->postJson('/mcp', mcpCall('tools/call', ['name' => 'list-invoices', 'arguments' => []]))->assertOk();
    $settings = $this->postJson('/mcp', mcpCall('tools/call', ['name' => 'get-settings', 'arguments' => ['group' => 'payment']]))->json();
    expect(json_encode($settings))->toContain('group');
    $this->postJson('/mcp', mcpCall('tools/call', ['name' => 'get-settings', 'arguments' => ['group' => 'profile']]))->assertOk();
});

it('rate limits per token and audits throttled calls', function (): void {
    config(['mcp-server.rate.per_minute' => 2]);
    asMcpOwner(['content:read']);
    $call = mcpCall('tools/call', ['name' => 'list-content', 'arguments' => ['type' => 'tag']]);

    $this->postJson('/mcp', $call)->assertOk();
    $this->postJson('/mcp', $call)->assertOk();
    $this->postJson('/mcp', $call)->assertStatus(429);

    expect(McpAuditLog::query()->where('result', 'throttled')->count())->toBe(1);
});

it('keeps the audit log append-only', function (): void {
    $log = McpAuditLog::query()->create(['method' => 'tools/call', 'tool' => 'x', 'result' => 'ok', 'http_status' => 200]);

    expect(fn () => $log->update(['result' => 'denied']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('sends guests, non-admins and admins without 2FA away from the consent screen', function (): void {
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.ai/cb'], false);
    $url = '/oauth/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $client->getKey(), 'redirect_uri' => 'https://claude.ai/cb', 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256', 'state' => 'x']);

    $this->get($url)->assertRedirect(adminPath('/login'));
    $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();

    $admin = User::factory()->create();
    $admin->is_admin = true;
    $admin->save();
    $this->actingAs($admin)->get($url)->assertRedirectContains(adminPath('/multi-factor-authentication/set-up'));
});

it('runs the full flow: register, owner approves some scopes, PKCE exchange, scoped calls', function (): void {
    $register = $this->postJson('/oauth/register', ['client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost:53682/callback']])->assertCreated();
    $clientId = (string) $register->json('client_id');

    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $query = ['response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => 'http://localhost:53682/callback', 'scope' => 'mcp:use', 'state' => 'abc', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'];

    $this->actingAs(adminWithMfa());
    $consent = $this->get('/oauth/authorize?'.http_build_query($query))->assertOk()
        ->assertSee('Allow Claude Code to access your site?')->assertSee('New app')->assertSee('http://localhost:53682/callback')
        ->assertHeader('X-Frame-Options', 'DENY');
    preg_match('/name="auth_token" value="([^"]+)"/', (string) $consent->getContent(), $m);

    $approve = $this->post('/oauth/authorize', ['state' => 'abc', 'client_id' => $clientId, 'auth_token' => $m[1], 'scopes' => ['content:read']]);
    $approve->assertRedirect();
    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $redirect);
    expect($redirect['state'])->toBe('abc');

    auth()->forgetGuards();
    $tokens = $this->postJson('/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'redirect_uri' => 'http://localhost:53682/callback', 'code_verifier' => $verifier, 'code' => $redirect['code']])->assertOk();
    $access = (string) $tokens->json('access_token');
    expect($tokens->json('expires_in'))->toBeLessThanOrEqual(3600)->and($tokens->json('refresh_token'))->not->toBeEmpty();

    expect(Client::query()->findOrFail($clientId)->getAttribute('scopes'))->toBe(['mcp:use', 'content:read']);

    $this->withToken($access)->postJson('/mcp', mcpCall('tools/call', ['name' => 'list-content', 'arguments' => ['type' => 'post']]))->assertOk();
    $this->withToken($access)->postJson('/mcp', mcpCall('tools/call', ['name' => 'list-invoices', 'arguments' => []]))->assertForbidden();

    expect(McpAuditLog::query()->where('client_id', $clientId)->pluck('result')->all())->toBe(['ok', 'denied']);
});

it('answers unauthenticated MCP calls in JSON even without a JSON Accept header', function (): void {
    $this->call('POST', '/mcp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'text/html'], json_encode(mcpCall('tools/list')))
        ->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
    $this->get('/login')->assertNotFound();
});
