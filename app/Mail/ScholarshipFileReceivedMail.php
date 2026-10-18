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

/** Sent to the official email of the student when the paper scholarship file is received at the counter. */
class ScholarshipFileReceivedMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $studentName,
        public readonly string $ddbNumber,
        public readonly string $academicYear,
        public readonly string $receivedAt,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Demande de bourse reçue : N° '.$this->ddbNumber);
    }

    public function content(): Content
    {
        return new Content(htmlString: StudentMails::html(
            '<p style="margin:0 0 12px">Bonjour '.e($this->studentName).',</p>'
            .'<p style="margin:0">Votre demande de bourse a été reçue le <strong>'.e($this->receivedAt).'</strong> par la Scolarité Centrale.</p>'
            .StudentMails::reference('Votre numéro de référence', $this->ddbNumber)
            .StudentMails::details(['Année universitaire' => $this->academicYear])
            .'<p style="margin:0">Conservez ce numéro de référence : il est demandé pour toute démarche. Suivez le traitement de votre dossier sur le portail étudiant.</p>',
            'Demande de bourse reçue : votre numéro de référence est '.$this->ddbNumber.'.',
        ));
    }
}
