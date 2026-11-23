<?php

declare(strict_types=1);

/*
 * The repository is public and generic: the owner's identity lives in the database, storage and the server
 * .env. This fails if a personal term appears in a tracked text file outside the allowlist.
 */
it('keeps personal terms out of tracked files', function (): void {
    $terms = ['chinmoy', 'biswas', 'cb709'];

    // Files that may name the author (credits). Keep this list short.
    $allowlist = [
        'LICENSE',
        'README.md',
        'tests/Feature/NoPersonalDataTest.php',
    ];
    $skipExtensions = ['png', 'jpg', 'jpeg', 'webp', 'ico', 'ttf', 'woff', 'woff2', 'pdf', 'lock'];

    $files = array_filter(explode("\n", (string) shell_exec('git -C '.escapeshellarg(base_path()).' ls-files')));
    expect($files)->not->toBeEmpty();

    $offenders = [];
    foreach ($files as $file) {
        if (in_array($file, $allowlist, true)
            || in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $skipExtensions, true)
            || ! is_file(base_path($file))) {
            continue;
        }
        $content = strtolower((string) file_get_contents(base_path($file)));
        foreach ($terms as $term) {
            if (str_contains($content, $term)) {
                $offenders[] = "{$file} ({$term})";
            }
        }
    }

    expect($offenders)->toBe([]);
});
