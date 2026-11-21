# ADR-0006: Queues and scheduled jobs on shared hosting

**Status:** Proposed (cron interval to be confirmed in the deploy proof)
**Date:** 2026-11-22
**Deciders:** Owner

## Context
- There are no long-running processes (no Supervisor, no `queue:work` daemon). `crontab` is **not available** in the SSH shell. Cron jobs are managed through hPanel or the Hostinger API (`hosting_cron-jobs_create` takes a standard 5-field expression).
- Async work is light: responsive image conversions, invoice overdue checks and pruning.
- Invoice emails need **immediate, visible success or failure** in admin.

## Decision
- One cron job per environment, **every minute** (`* * * * *`), created in hPanel or via the API after owner approval:
  `/usr/bin/php ~/domains/<d>/app/current/artisan schedule:run` (absolute paths, with the PHP path confirmed in the deploy proof).
- `QUEUE_CONNECTION=database`, `CACHE_STORE=database` (needed for locks: `withoutOverlapping`, `migrate --isolated`), `SESSION_DRIVER=database`.
- Scheduler (`routes/console.php`):
  | Task | Schedule |
  |---|---|
  | `queue:work database --stop-when-empty --max-time=50 --tries=3 --backoff=30` | everyMinute, `withoutOverlapping(5)` |
  | `invoices:mark-overdue` (Sent and past due → Overdue) | dailyAt 06:00 (Europe/London) |
  | `queue:prune-failed --hours=168`, `model:prune` | daily |
  | `sitemap:generate` (writes a static `sitemap.xml` to the public disk) | hourly, plus on content save |
- **Invoice email is sent synchronously** inside the Filament action (Hostinger SMTP, timeout 15s). The result is logged to `invoice_emails` either way, and the admin sees success or the error at once. Nothing financial depends on the queue.
- Queued work is limited to work where a delay of up to about 1 minute is fine (image conversions).

## Options considered
| Option | Verdict |
|---|---|
| **Database queue drained by `schedule:run` every minute** | **Chosen.** Standard Laravel, nothing extra. |
| Everything synchronous (`sync` driver) | Simple, but image conversions on upload could hit web timeouts. Kept as the fallback if cron is unreliable. |
| External queue/cron service | Unnecessary for this volume. |

## Consequences
- Fallback if Hostinger only allows a **5- or 15-minute** minimum: still fine. Images appear later, and the original is shown meanwhile. Update this ADR if so.
- If cron stops, nothing financial breaks. A `/up` health extension reports the last scheduler run (`cache:scheduler:last-run`) so this can be monitored.

## Action items
1. [ ] Deploy proof: create the staging cron (owner-approved) and confirm the allowed interval and PHP path from the job output (`hosting_cron-jobs_output`).
