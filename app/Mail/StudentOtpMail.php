<?php

namespace App\Mail;

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
        return new Envelope(subject: 'Votre code d’accès au portail étudiant UMG');
    }

    public function content(): Content
    {
        $minutes = (int) config('portals.otp_minutes');

        return new Content(htmlString: '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;color:#0f172a">'
            .'<p style="font-size:15px">Voici votre code d’accès au portail étudiant de l’Université de Mahajanga :</p>'
            .'<p style="font-size:32px;font-weight:700;letter-spacing:10px;text-align:center;background:#eef2ff;border-radius:12px;padding:16px 0;margin:20px 0">'.e($this->code).'</p>'
            .'<p style="font-size:13px;color:#475569">Il expire dans '.$minutes.' minutes et ne sert qu’une fois. Si vous n’avez pas demandé ce code, ignorez cet email.</p>'
            .'</div>');
    }
}
