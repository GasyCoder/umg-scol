<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the official email of a record when a portal account with another email is linked to it. */
class StudentPortalLinkedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $accountEmail)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre fiche étudiante a été ouverte sur le portail UMG');
    }

    public function content(): Content
    {
        $masked = (string) preg_replace('/(?<=^.).*(?=@)/u', '•••', $this->accountEmail);

        return new Content(htmlString: '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;color:#0f172a">'
            .'<p style="font-size:15px">Votre fiche étudiante vient d’être rattachée au compte <strong>'.e($masked).'</strong> sur le portail étudiant de l’Université de Mahajanga.</p>'
            .'<p style="font-size:15px">Si c’est bien vous, il n’y a rien à faire.</p>'
            .'<p style="font-size:15px">Si ce n’est pas vous, contactez rapidement votre établissement : il pourra détacher ce compte de votre fiche.</p>'
            .'</div>');
    }
}
