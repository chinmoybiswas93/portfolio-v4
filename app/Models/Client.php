<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'company_name', 'contact_name', 'email', 'phone', 'address_line1', 'address_line2',
        'city', 'postcode', 'country_code', 'vat_number', 'default_currency', 'notes',
    ];

    protected function casts(): array
    {
        return ['default_currency' => Currency::class];
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
