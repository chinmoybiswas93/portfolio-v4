<?php

declare(strict_types=1);

arch('no debugging calls are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('actions are final and invokable')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toHaveMethod('__invoke');

arch()->preset()->security();
