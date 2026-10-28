<?php

namespace App\Services;

use App\Mail\DemoLista;
use App\Models\SolicitudDemo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * A los odontólogos que pidieron la demo cuando solo existía la médica: les
 * crea un acceso en la demo dental de su país y les manda el correo de
 * «nueva versión con odontograma», con la guía en PDF.
 *
 * Cada envío queda como una solicitud nueva (instancia odonto/odontohn), así
 * el panel sigue sabiendo en qué demo está cada uno y si entró.
 */
class ReenvioDemoDental
{
    public function __construct(private InstanciaDemo $instancia) {}

    /**
     * Las solicitudes de odontólogos a las que les toca, una por correo (la
     * más reciente), sin las que ya están en una demo dental.
     *
     * @param  list<int>  $ids  vacío = todas las de Odontología
     * @return Collection<int, SolicitudDemo>
     */
    public function candidatas(array $ids = []): Collection
    {
        $yaDentales = SolicitudDemo::query()->where('instancia', 'like', 'odonto%')->pluck('email')->all();

        return SolicitudDemo::query()
            ->where('especialidad', InstanciaDemo::ODONTOLOGIA)
            ->where('estado', 'enviada')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->whereNotIn('email', $yaDentales)
            ->orderByDesc('id')
            ->get()
            ->unique('email')
            ->values();
    }

    /** El país con el que se arma su demo: el que se le dio en la primera, si no el de su WhatsApp. */
    public function paisDe(SolicitudDemo $s): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $s->whatsapp);

        return match (true) {
            $s->instancia === 'hn', $s->pais === 'HN', str_starts_with($digitos, '504') => 'HN',
            $s->pais === 'GT', str_starts_with($digitos, '502') => 'GT',
            default => 'SV',
        };
    }

    public function enviar(SolicitudDemo $original): SolicitudDemo
    {
        $pais = $this->paisDe($original);

        $nueva = SolicitudDemo::create([
            'nombre' => $original->nombre,
            'especialidad' => $original->especialidad,
            'email' => $original->email,
            'whatsapp' => $original->whatsapp,
            'pais' => $original->pais,
            'instancia' => $this->instancia->para($pais, InstanciaDemo::ODONTOLOGIA),
            'detalle' => "Reenvío de la demo dental (antes en la solicitud #{$original->id}).",
        ]);

        $acceso = $this->instancia->crearAcceso($nueva->nombre, $nueva->email, $nueva->especialidad, $pais, $nueva->instancia);
        Mail::to($nueva->email, $nueva->nombre)->send(new DemoLista($nueva, $acceso, reenvio: true));
        $nueva->update(['estado' => 'enviada', 'demo_expira_at' => Carbon::parse($acceso['expira_en'])]);

        return $nueva;
    }
}
