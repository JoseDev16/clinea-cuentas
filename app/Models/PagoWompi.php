<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un pago en un enlace de pago único de Wompi (Honduras / Guatemala). */
class PagoWompi extends Model
{
    protected $table = 'pagos_wompi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'pagado_at' => 'datetime',
            'aviso_enviado_at' => 'datetime',
            'coincidio_por_correo' => 'boolean',
            'datos' => 'array',
        ];
    }

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(Suscripcion::class);
    }
}
