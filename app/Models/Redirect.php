<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    protected $fillable = ['from_path', 'to_path', 'status_code'];

    protected function casts(): array
    {
        return ['status_code' => 'integer', 'hits' => 'integer'];
    }
}
