<?php

namespace App\Services;

use App\Mail\Bienvenida;
use App\Models\Suscripcion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * La bienvenida al cliente en cuanto se suscribe en Wompi: un correo con la
 * marca de Clinea y una carta en PDF firmada por el equipo. Llega minutos
 * después de pagar, para que nadie se quede con la duda de a quién le pagó.
 */
class Bienvenidas
{
    public function enviar(Suscripcion $s, bool $otraVez = false): bool
    {
        if ($s->bienvenida_enviada_at && ! $otraVez) {
            return true;
        }

        try {
            Mail::to($s->email, $s->nombre_contacto)->send(new Bienvenida($s));
        } catch (Throwable $e) {
            Log::error("No se pudo enviar la bienvenida de la suscripción {$s->id}", ['error' => $e->getMessage()]);

            return false;
        }

        $s->forceFill(['bienvenida_enviada_at' => now()])->save();

        return true;
    }

    /** La carta de bienvenida (PDF, tamaño carta). */
    public function pdf(Suscripcion $s): string
    {
        return Pdf::loadView('pdf.bienvenida', $this->datos($s))
            ->setPaper('letter')
            ->output();
    }

    public function nombreArchivo(Suscripcion $s): string
    {
        $clinica = trim(preg_replace('/[^\pL\pN ]+/u', '', $s->clinica)) ?: 'tu clinica';

        return 'Bienvenida a Clinea - '.mb_substr($clinica, 0, 60).'.pdf';
    }

    /** Lo que comparten el correo y la carta. */
    public function datos(Suscripcion $s): array
    {
        $promesa = $s->instanciaPrometidaPara() ?? now()->addHours(config('clinea.horas_instancia'));
        $usd = '$'.number_format((float) $s->monto, 2);
        // Honduras y Guatemala: precio anunciado en su moneda, cobro en dólares.
        $anunciado = config("clinea.planes.{$s->pais}.{$s->plan}.anunciado");
        $whatsapp = (string) config('clinea.whatsapp_soporte');

        return [
            's' => $s,
            'marca' => config('clinea.marca'),
            'nombre' => $s->nombre_contacto,
            'plan' => $s->nombrePlan(),
            'monto' => $anunciado ? "{$anunciado} (se cobra US{$usd})" : $usd,
            'notaMoneda' => $anunciado
                ? "Nunca pagarás más de {$anunciado} al mes. Se cobra en dólares (US{$usd}) y tu banco lo convierte a ".config("clinea.monedas_locales.{$s->pais}", 'tu moneda').": por la conversión podrías pagar un poco menos, pero nunca más de lo anunciado."
                : null,
            'horas' => config('clinea.horas_instancia'),
            'promesaHora' => $promesa->locale('es')->isoFormat('h:mm a'),
            'promesaDia' => $promesa->isSameDay(now()) ? 'hoy' : $promesa->locale('es')->isoFormat('dddd D [de] MMMM'),
            'fecha' => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'whatsappUrl' => 'https://wa.me/'.$whatsapp.'?text='.rawurlencode("Hola, soy {$s->nombre_contacto} de {$s->clinica}. Acabo de suscribirme a Clinea."),
            'whatsappVisible' => self::telefonoVisible($whatsapp),
            'correo' => config('clinea.correo_soporte'),
            'direccion' => config('clinea.direccion_fstudios'),
        ];
    }

    /** 50366781544 → +503 6678-1544 */
    public static function telefonoVisible(string $digitos): string
    {
        $d = preg_replace('/\D/', '', $digitos);
        if (strlen($d) === 11) {
            return '+'.substr($d, 0, 3).' '.substr($d, 3, 4).'-'.substr($d, 7);
        }

        return '+'.$d;
    }
}
