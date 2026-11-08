<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecretariaMensagemMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $assunto,
        public string $corpo,
        public string $destinatarioNome,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.secretaria-mensagem',
        );
    }
}
