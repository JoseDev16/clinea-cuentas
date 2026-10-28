<?php

namespace App\Mail;

use App\Models\SolicitudDemo;
use App\Services\Bienvenidas;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * Para el doctor que pidió «Prueba gratis»: su usuario y contraseña
 * de la demo. La contraseña viaja solo en este correo (no se guarda aquí), así
 * el acceso le llega únicamente a quien es dueño del correo.
 */
class DemoLista extends Mailable
{
    use Queueable;

    /** La guía en PDF que se adjunta a los odontólogos. */
    public const GUIA_DENTAL = 'marca/Clinea-para-odontologos.pdf';

    /**
     * @param  bool  $reenvio  A quien ya tenía la demo médica y ahora recibe la
     *                         dental: el correo le explica que es una versión nueva.
     */
    public function __construct(public SolicitudDemo $solicitud, public array $acceso, public bool $reenvio = false) {}

    /** ¿Es la demo de odontología (odonto u odontohn)? */
    public function esDental(): bool
    {
        return str_starts_with((string) $this->solicitud->instancia, 'odonto');
    }

    public function envelope(): Envelope
    {
        $marca = config('clinea.marca');

        return new Envelope(
            from: new Address(config('mail.from.address'), $marca),
            replyTo: [new Address(config('clinea.correo_soporte'), 'Equipo de '.$marca)],
            subject: match (true) {
                $this->reenvio => "{$marca} para odontólogos: tu nueva demo con odontograma",
                $this->esDental() => "Tu demo de {$marca} para odontólogos está lista: pruébala ".config('clinea.demo.duracion'),
                default => "Tu demo de {$marca} está lista: entra y pruébala ".config('clinea.demo.duracion'),
            },
        );
    }

    public function attachments(): array
    {
        return $this->esDental()
            ? [Attachment::fromPath(resource_path(self::GUIA_DENTAL))->as('Clinea-para-odontologos.pdf')->withMime('application/pdf')]
            : [];
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
                'duracion' => config('clinea.demo.duracion'),
                'vence' => $vence->isoFormat('dddd D [de] MMMM, h:mm a'),
                'whatsappUrl' => 'https://wa.me/'.$whatsapp.'?text='.rawurlencode("Hola, soy {$this->solicitud->nombre}. Estoy probando la demo de Clinea y tengo una pregunta."),
                'whatsappVisible' => Bienvenidas::telefonoVisible($whatsapp),
                'correoSoporte' => config('clinea.correo_soporte'),
                'direccion' => config('clinea.direccion_fstudios'),
                'dental' => $this->esDental(),
                'reenvio' => $this->reenvio,
            ],
        );
    }
}
