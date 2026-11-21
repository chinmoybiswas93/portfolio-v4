<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEmail extends Model
{
    protected $fillable = [
        'invoice_id', 'sent_by', 'to_email', 'cc', 'subject', 'body', 'pdf_path', 'pdf_sha256',
        'status', 'error', 'message_id', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailStatus::class,
            'cc' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
