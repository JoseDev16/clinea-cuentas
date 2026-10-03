<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso interno para fstudios: nueva solicitud, pagos, atrasos. */
class Aviso extends Mailable
{
    use Queueable;

    /** @param array<int, array{titulo: string, lineas: array<int, string>, url?: string}> $bloques */
    public function __construct(public string $asunto, public array $bloques) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.aviso');
    }
}
