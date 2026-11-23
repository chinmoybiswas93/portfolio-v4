<?php

declare(strict_types=1);

/*
 * Releases are packed from an include list (scripts/package-release.sh). This fails when a new tracked
 * top-level or resources/ entry is in neither the shipped nor the dev-only list, so nothing new reaches
 * the server, or goes missing from it, without a decision.
 */
it('classifies every tracked entry for the release package', function (): void {
    $output = [];
    $status = 0;
    exec('bash '.escapeshellarg(base_path('scripts/package-release.sh')).' --check 2>&1', $output, $status);

    expect($status)->toBe(0, implode("\n", $output));
});
