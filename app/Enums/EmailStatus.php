<?php

declare(strict_types=1);

namespace App\Enums;

enum EmailStatus: string
{
    /** Queued; sent by the scheduler within a minute. */
    case Pending = 'pending';

    /** Accepted by the mail server (not proof the email was opened). */
    case Sent = 'sent';
    case Failed = 'failed';
}
