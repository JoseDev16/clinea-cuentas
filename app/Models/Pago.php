<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'detectado_at' => 'datetime',
        ];
    }

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(Suscripcion::class);
    }
}
