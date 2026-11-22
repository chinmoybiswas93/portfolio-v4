<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Amounts are integer minor units in $currency. Totals are written by the invoice calculator (slice 3.5).
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /** Same defaults as the database, so a new model knows them before it is reloaded. */
    protected $attributes = [
        'status' => 'draft',
        'discount_type' => 'none',
        'discount_value' => 0,
        'tax_rate_bp' => 0,
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'tax_minor' => 0,
        'total_minor' => 0,
        'reverse_charge' => false,
    ];

    protected $fillable = [
        'client_id', 'currency', 'reference', 'issue_date', 'due_date', 'discount_type', 'discount_value',
        'tax_rate_bp', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'currency' => Currency::class,
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'tax_rate_bp' => 'integer',
            'reverse_charge' => 'boolean',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'sequence_year' => 'integer',
            'sequence_no' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'client_snapshot' => 'array',
            'seller_snapshot' => 'array',
            'pdf_generated_at' => 'datetime',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<InvoiceEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(InvoiceEmail::class)->latest('sent_at');
    }
}
