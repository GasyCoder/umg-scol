<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** Account created by an administrator: the person chooses their own password through a reset link. */
class InternalAccountInvitation extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Votre accès au portail '.config('app.name'))
            ->greeting('Bonjour,')
            ->line('Un compte vous a été ouvert sur le portail interne '.config('app.name').' de l’Université de Mahajanga.')
            ->line('Choisissez votre mot de passe pour activer votre accès.')
            ->action('Définir mon mot de passe', $this->resetUrl($notifiable))
            ->line("Ce lien est valable {$minutes} minutes. Passé ce délai, demandez à votre administrateur de vous renvoyer l’invitation.")
            ->line('Si vous n’attendiez pas ce message, ignorez-le.');
    }
}
