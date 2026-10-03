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
        return ['demo_expira_at' => 'datetime'];
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function vigente(): bool
    {
        return $this->estado === 'enviada' && $this->demo_expira_at?->isFuture();
    }
}
