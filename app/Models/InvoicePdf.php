<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generated version of an invoice PDF. Versions are never overwritten or edited.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $version
 * @property string $path
 * @property string $sha256
 * @property InvoiceStatus $status_at_render
 */
class InvoicePdf extends Model
{
    protected $fillable = ['invoice_id', 'version', 'path', 'sha256', 'status_at_render'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'status_at_render' => InvoiceStatus::class];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function label(): string
    {
        return 'v'.$this->version;
    }
}
