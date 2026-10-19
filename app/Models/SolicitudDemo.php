<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudDemo extends Model
{
    protected $table = 'solicitudes_demo';

    protected $guarded = ['id'];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'enviada' => 'Acceso enviado',
        'cuenta_existente' => 'Correo ya registrado',
        'error' => 'Falló',
    ];

    protected function casts(): array
    {
        return [
            'demo_expira_at' => 'datetime',
            'primer_ingreso_at' => 'datetime',
            'ultimo_ingreso_at' => 'datetime',
            'ingresos' => 'integer',
        ];
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** Enlace wa.me para escribirle; null si no dejó número. */
    public function enlaceWhatsapp(): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $this->whatsapp);

        return $digitos !== '' ? 'https://wa.me/'.$digitos : null;
    }

    /** ¿Entró alguna vez a su demo? (lo sincroniza ActividadDemos). */
    public function entro(): bool
    {
        return $this->primer_ingreso_at !== null;
    }

    public function vigente(): bool
    {
        return $this->estado === 'enviada' && $this->demo_expira_at?->isFuture();
    }
}
