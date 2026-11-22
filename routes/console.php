<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 * Shared hosting has no long-running workers (ADR-0006): the hPanel cron runs `schedule:run`
 * every minute, and the queue is drained in short, non-overlapping bursts.
 */
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3 --backoff=30')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->name('queue-drain');

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('model:prune')->daily();

Schedule::command('invoices:mark-overdue')->dailyAt('06:00')->timezone('Europe/London');
