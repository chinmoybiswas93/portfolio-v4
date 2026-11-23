<?php

declare(strict_types=1);

it('reports healthy status and the release id', function (): void {
    $this->getJson('/up')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'database' => true])
        ->assertJsonStructure(['status', 'release', 'database'])
        ->assertHeader('X-Robots-Tag', 'noindex');
});

it('is never cached', function (): void {
    expect($this->get('/up')->headers->get('Cache-Control'))->toContain('no-store');
});
