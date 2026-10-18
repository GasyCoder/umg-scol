<?php

namespace App\Mail;

use App\Support\StudentMails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Asks a student to complete or check their record from the portal. Lists field names, never values. */
class StudentInformationRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param list<array{label:string,missing:bool}> $fields */
    public function __construct(
        public readonly string $studentName,
        public readonly array $fields,
        public readonly ?string $note,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre fiche étudiante est à compléter');
    }

    public function content(): Content
    {
        $list = '';
        foreach ($this->fields as $field) {
            $list .= '<tr><td style="padding:9px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a">'.e($field['label']).'</td>'
                .'<td style="padding:9px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:'.($field['missing'] ? '#b45309' : '#1d4ed8').';text-align:right">'
                .($field['missing'] ? 'Non renseigné' : 'À vérifier').'</td></tr>';
        }

        return new Content(htmlString: StudentMails::html(
            '<p style="margin:0 0 12px">Bonjour '.e($this->studentName).',</p>'
            .'<p style="margin:0 0 16px">'.($this->fields === []
                ? 'La scolarité vous demande de vérifier votre fiche étudiante.'
                : 'La scolarité a relevé des informations à compléter ou à vérifier sur votre fiche étudiante :').'</p>'
            .($this->fields === [] ? '' : '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border-top:1px solid #e2e8f0">'.$list.'</table>')
            .($this->note === null ? '' : '<p style="margin:0 0 6px;font-size:13px;color:#64748b">Message de la scolarité</p>'
                .'<p style="margin:0 0 20px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;white-space:pre-line">'.e($this->note).'</p>')
            .'<p style="margin:0">Connectez-vous au portail étudiant et signalez la bonne valeur avec le crayon à côté de chaque information. Vous pouvez aussi vous présenter à la scolarité de votre établissement.</p>',
            'Des informations de votre fiche étudiante sont à compléter ou à vérifier.',
        ));
    }
}
