<?php

namespace App\Mail;

use App\Models\Suscripcion;
use App\Services\Bienvenidas;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Para el cliente: bienvenida a Clinea + carta en PDF. */
class Bienvenida extends Mailable
{
    use Queueable;

    public function __construct(public Suscripcion $suscripcion) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // El remitente siempre dice «Clinea», sin importar el MAIL_FROM_NAME.
            from: new Address(config('mail.from.address'), config('clinea.marca')),
            // Si responde, le llega a una persona del equipo y no a no-reply.
            replyTo: [new Address(config('clinea.correo_soporte'), 'Equipo de '.config('clinea.marca'))],
            subject: 'Te damos la bienvenida a '.config('clinea.marca').': ya estamos preparando '.$this->suscripcion->clinica,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.bienvenida',
            text: 'mail.bienvenida-texto',
            with: app(Bienvenidas::class)->datos($this->suscripcion),
        );
    }

    public function attachments(): array
    {
        $bienvenidas = app(Bienvenidas::class);

        return [
            Attachment::fromData(fn () => $bienvenidas->pdf($this->suscripcion), $bienvenidas->nombreArchivo($this->suscripcion))
                ->withMime('application/pdf'),
        ];
    }
}
