<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

it('drains the database queue every minute without overlapping', function (): void {
    $events = collect(app(Schedule::class)->events());
    $drain = $events->first(fn ($event): bool => str_contains((string) $event->command, 'queue:work database --stop-when-empty'));

    expect($drain)->not->toBeNull()
        ->and($drain->expression)->toBe('* * * * *')
        ->and($drain->withoutOverlapping)->toBeTrue();
});
