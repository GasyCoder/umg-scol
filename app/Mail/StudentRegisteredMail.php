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
 * Sent to the official email of a new student file, created with the form (never by an import).
 * Only the name and the declared situation: no CIN, contact or other personal data.
 */
class StudentRegisteredMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $studentName,
        public readonly string $establishment,
        public readonly string $academicYear,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre fiche étudiante a été enregistrée');
    }

    public function content(): Content
    {
        return new Content(htmlString: StudentMails::html(
            '<p style="margin:0 0 12px">Bonjour '.e($this->studentName).',</p>'
            .'<p style="margin:0 0 20px">Votre fiche étudiante a été enregistrée à la Scolarité Centrale.</p>'
            .StudentMails::details(['Année universitaire' => $this->academicYear, 'Établissement' => $this->establishment])
            .'<p style="margin:0">Sur le portail étudiant, vérifiez vos informations et signalez une erreur si besoin.</p>',
            'Votre fiche étudiante a été enregistrée pour '.$this->academicYear.'.',
        ));
    }
}
