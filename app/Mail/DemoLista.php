<?php

namespace App\Mail;

use App\Models\SolicitudDemo;
use App\Services\Bienvenidas;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * Para el doctor que pidió «Prueba Clinea 24 horas»: su usuario y contraseña
 * de la demo. La contraseña viaja solo en este correo (no se guarda aquí), así
 * el acceso le llega únicamente a quien es dueño del correo.
 */
class DemoLista extends Mailable
{
    use Queueable;

    public function __construct(public SolicitudDemo $solicitud, public array $acceso) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('clinea.marca')),
            replyTo: [new Address(config('clinea.correo_soporte'), 'Equipo de '.config('clinea.marca'))],
            subject: 'Tu demo de '.config('clinea.marca').' está lista: entra y pruébala 24 horas',
        );
    }

    public function content(): Content
    {
        $vence = Carbon::parse($this->acceso['expira_en'])->timezone(config('app.timezone'))->locale('es');
        $whatsapp = (string) config('clinea.whatsapp_soporte');

        return new Content(
            view: 'mail.demo-lista',
            text: 'mail.demo-lista-texto',
            with: [
                'marca' => config('clinea.marca'),
                'nombre' => $this->solicitud->nombre,
                'url' => $this->acceso['url'],
                'correo' => $this->acceso['correo'],
                'password' => $this->acceso['password'],
                'horas' => config('clinea.demo.horas'),
                'vence' => $vence->isoFormat('dddd D [de] MMMM, h:mm a'),
                'whatsappUrl' => 'https://wa.me/'.$whatsapp.'?text='.rawurlencode("Hola, soy {$this->solicitud->nombre}. Estoy probando la demo de Clinea y tengo una pregunta."),
                'whatsappVisible' => Bienvenidas::telefonoVisible($whatsapp),
                'correoSoporte' => config('clinea.correo_soporte'),
                'direccion' => config('clinea.direccion_fstudios'),
            ],
        );
    }
}
