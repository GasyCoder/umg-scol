<?php

namespace App\Mail;

use App\Support\StudentMails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One-time code of the student portal. Queued so that the page answers at once instead of
 * waiting for the SMTP server; encrypted in the queue (APP_KEY) so that the code is never
 * stored in clear, only its hash in email_otp_challenges.
 */
class StudentOtpMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    /** A code is useless after its expiry: no retry storm, no late delivery. */
    public int $tries = 2;

    public function __construct(public readonly string $code)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        // The code first: readable in the phone notification without opening the email.
        return new Envelope(subject: $this->code.' est votre code d’accès au portail étudiant');
    }

    public function content(): Content
    {
        $minutes = (int) config('portals.otp_minutes');

        return new Content(htmlString: StudentMails::html(
            '<p style="margin:0 0 6px;font-size:18px;font-weight:700">Votre code de connexion</p>'
            .'<p style="margin:0 0 20px;color:#475569">Saisissez ce code sur le portail étudiant pour continuer.</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px"><tr>'
            .'<td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:22px 12px;text-align:center">'
            .'<p style="margin:0;font-size:34px;line-height:1;font-weight:700;letter-spacing:12px;padding-left:12px;font-family:Consolas,Menlo,Monaco,monospace;color:#0f172a">'.e($this->code).'</p>'
            .'</td></tr></table>'
            .'<p style="margin:0 0 24px;font-size:13px;color:#64748b;text-align:center">Valable '.$minutes.' minutes · une seule utilisation</p>'
            .'<p style="margin:0;padding-top:16px;border-top:1px solid #e2e8f0;font-size:13px;line-height:1.55;color:#64748b">'
            .'Vous n’avez pas demandé ce code ? Ignorez cet email : sans lui, personne ne peut ouvrir votre fiche. '
            .'Ne le communiquez à personne, même à la scolarité.</p>',
            'Code '.$this->code.', valable '.$minutes.' minutes.',
            portalButton: false,
            subtitle: 'Portail étudiant',
        ));
    }
}
