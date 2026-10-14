<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StudentOtpMail extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code d’accès au portail étudiant UMG');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Votre code d’accès : <strong>'.$this->code.'</strong></p><p>Il expire dans 10 minutes et ne peut être utilisé qu’une fois. Si vous n’avez pas demandé ce code, ignorez cet email.</p>');
    }
}
