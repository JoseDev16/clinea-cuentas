<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $guarded = ['id'];

    public const ESTADOS = [
        'pendiente' => 'Pendiente de pago',
        'suscrita' => 'Suscrita: preparar instancia',
        'activa' => 'Al día',
        'atrasada' => 'Atrasada',
        'cancelada' => 'Cancelada',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'primer_pago_at' => 'datetime',
            'ultimo_pago_at' => 'datetime',
            'revisada_at' => 'datetime',
            'instancia_lista_at' => 'datetime',
            'suscrita_at' => 'datetime',
            'bienvenida_enviada_at' => 'datetime',
        ];
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class)->orderByDesc('numero');
    }

    public function nombrePlan(): string
    {
        return config("clinea.planes.{$this->pais}.{$this->plan}.nombre", $this->plan);
    }

    public function nombrePais(): string
    {
        return config("clinea.paises.{$this->pais}", $this->pais);
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * El último día de cobro que ya pasó (contando los días de gracia), o null
     * si todavía no le toca ninguno desde su primer pago.
     */
    public function ultimoCobroVencido(?Carbon $hoy = null): ?Carbon
    {
        $hoy ??= now();
        $limite = $hoy->copy()->subDays(config('clinea.dias_gracia'))->startOfDay();
        $cobro = $limite->copy()->day(min($this->dia_cobro, $limite->daysInMonth));
        if ($cobro->gt($limite)) {
            $cobro->subMonthNoOverflow();
            $cobro->day(min($this->dia_cobro, $cobro->daysInMonth));
        }

        return $this->primer_pago_at && $cobro->gt($this->primer_pago_at->copy()->startOfDay()) ? $cobro : null;
    }

    /** Hasta cuándo le prometimos tener lista su instancia (null si no se ha suscrito). */
    public function instanciaPrometidaPara(): ?Carbon
    {
        return $this->suscrita_at?->copy()->addHours(config('clinea.horas_instancia'));
    }

    public function enlaceWhatsApp(): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', $this->whatsapp);
    }
}
