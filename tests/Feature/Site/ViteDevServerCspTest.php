<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use App\Support\Vite\DevServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;

uses(RefreshDatabase::class);

function useHotFile(string $contents): void
{
    $path = sys_get_temp_dir().'/hot-'.bin2hex(random_bytes(4));
    file_put_contents($path, $contents);
    Vite::useHotFile($path);
}

it('keeps the strict CSP when no dev server is running', function (): void {
    Vite::useHotFile(sys_get_temp_dir().'/hot-missing');
    $this->get('/')->assertOk()->assertHeader('Content-Security-Policy', SecurityHeaders::PUBLIC_CSP);
});

it('allows the Vite origin in the CSP locally while the dev server runs', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    useHotFile("https://app.test:5173\n");

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect(DevServer::origin())->toBe('https://app.test:5173')
        ->and($csp)->toContain("script-src 'self' https://app.test:5173")
        ->toContain("style-src 'self' https://app.test:5173")
        ->toContain('wss://app.test:5173')
        ->and($csp)->toContain("frame-ancestors 'none'");
});

it('never loosens the CSP outside local, even with a hot file', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    useHotFile('https://app.test:5173');

    expect(DevServer::origin())->toBeNull();
    $this->get('/')->assertHeader('Content-Security-Policy', SecurityHeaders::PUBLIC_CSP);
});

it('ignores a hot file that is not a plain origin', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    useHotFile("https://app.test:5173; script-src *\n");

    expect(DevServer::origin())->toBeNull();
});

it('does not cache pages while the dev server runs locally', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    useHotFile('https://app.test:5173');

    $this->get('/')->assertOk()->assertHeaderMissing('X-Page-Cache');
});
