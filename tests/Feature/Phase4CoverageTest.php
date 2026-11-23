<?php

declare(strict_types=1);

use App\Filament\Auth\Login;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Passport;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * Phase 4: tests for security-sensitive code the coverage report showed as thin.
 */

/**
 * @param  array<int, string>|null  $scopes
 */
function mcpClient(string $name, ?array $scopes, int $ageDays): Laravel\Passport\Client
{
    $client = Passport::client()->newQuery()->forceCreate(['name' => $name, 'secret' => null, 'provider' => null, 'redirect_uris' => ['http://localhost/cb'], 'grant_types' => ['authorization_code'], 'revoked' => false, 'scopes' => $scopes]);
    $client->forceFill(['created_at' => now()->subDays($ageDays)])->save();

    return $client;
}

it('prunes only old apps that were never approved and never got a token', function (): void {
    $oldUnapproved = mcpClient('Spam', null, 10);
    $oldOnlyMcpUse = mcpClient('Spam 2', ['mcp:use'], 10);
    $oldApproved = mcpClient('Claude Code', ['mcp:use', 'content:read'], 10);
    $oldRevoked = mcpClient('Revoked', null, 10);
    $oldRevoked->tokens()->forceCreate(['id' => str_repeat('a', 80), 'user_id' => null, 'name' => null, 'scopes' => [], 'revoked' => true, 'expires_at' => now()]);
    $newUnapproved = mcpClient('Just registered', null, 1);

    $this->artisan('mcp:prune-clients')->expectsOutput('2 unapproved app(s) deleted.')->assertSuccessful();

    $left = Passport::client()->newQuery()->pluck('id')->all();
    expect($left)->toContain($oldApproved->id, $oldRevoked->id, $newUnapproved->id)
        ->not->toContain($oldUnapproved->id, $oldOnlyMcpUse->id);
});

it('returns an invoice and finds clients through the MCP read tools, without payment settings', function (): void {
    config(['mcp-server.enabled' => true]);
    Passport::actingAs(adminWithMfa(), ['mcp:use', 'invoices:read'], 'api');
    $client = Client::factory()->create(['company_name' => 'Acme Ltd']);
    Client::factory()->create(['company_name' => 'Other Co']);
    $invoice = Invoice::factory()->for($client)->create();
    $invoice->items()->create(['position' => 0, 'description' => 'Care plan', 'quantity_milli' => 1000, 'unit_price_minor' => 35000]);

    $call = fn (string $name, array $args) => $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $args]])->assertOk();

    $byId = json_decode((string) $call('get-invoice', ['id' => $invoice->id])->json('result.content.0.text'), true);
    expect($byId['client']['company_name'])->toBe('Acme Ltd')->and($byId['lines'][0]['description'])->toBe('Care plan')
        ->and(json_encode($byId))->not->toContain('iban')->not->toContain('sort_code')->not->toContain('account_number');

    expect($call('get-invoice', ['id' => 99999])->json('result.isError'))->toBeTrue();

    $clients = (string) $call('list-clients', ['search' => 'acme'])->json('result.content.0.text');
    expect($clients)->toContain('Acme Ltd')->not->toContain('Other Co');
    expect((string) $call('list-clients', ['search' => '%'])->json('result.content.0.text'))->not->toContain('Acme Ltd'); // wildcards are literal
});

it('blocks admin sign-in after the hourly limit, even with the right password', function (): void {
    $admin = adminWithMfa();
    $admin->forceFill(['password' => Hash::make('correct-horse-battery-1')])->save();
    for ($i = 0; $i < Login::MAX_ATTEMPTS_PER_HOUR; $i++) {
        RateLimiter::hit('admin-login:127.0.0.1', 3600);
    }

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'correct-horse-battery-1'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('resets the admin password and two-factor from the command line', function (): void {
    $admin = adminWithMfa();
    $admin->setRememberToken('remember-me');
    $admin->save();

    $this->artisan('app:admin-reset', ['email' => $admin->email, '--mfa' => true])
        ->expectsConfirmation('Set a new password?', 'yes')
        ->expectsQuestion('New password (min. 12 characters)', 'NewPassword2026x')
        ->expectsQuestion('Confirm password', 'NewPassword2026x')
        ->assertSuccessful();

    $admin->refresh();
    expect(Hash::check('NewPassword2026x', $admin->password))->toBeTrue()
        ->and($admin->getRememberToken())->toBeEmpty()
        ->and($admin->getAppAuthenticationSecret())->toBeEmpty();
});

it('refuses weak passwords and unknown emails in the reset command', function (): void {
    $admin = adminWithMfa();

    $this->artisan('app:admin-reset', ['email' => $admin->email])
        ->expectsConfirmation('Set a new password?', 'yes')
        ->expectsQuestion('New password (min. 12 characters)', 'short')
        ->expectsQuestion('Confirm password', 'short')
        ->assertFailed();

    $this->artisan('app:admin-reset', ['email' => 'nobody@example.com'])->expectsOutput('No admin with that email.')->assertFailed();
    expect(User::query()->where('email', 'nobody@example.com')->exists())->toBeFalse();
});
