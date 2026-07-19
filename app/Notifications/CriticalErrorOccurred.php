<?php

namespace App\Notifications;

use App\Models\ErrorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte les admins d'une exception inattendue (voir bootstrap/app.php pour ce qui
 * déclenche ceci — les rejets métier attendus n'arrivent jamais ici). Canal 'database'
 * pour une visibilité immédiate dans la cloche de notifications déjà utilisée partout
 * ailleurs dans l'app, 'mail' pour être vu même sans être connecté à l'interface.
 */
class CriticalErrorOccurred extends Notification
{
    use Queueable;

    public function __construct(public ErrorLog $errorLog)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'critical_error',
            'error_log_id' => $this->errorLog->id,
            'message' => 'Erreur technique : '.$this->errorLog->exception_class.' — '.mb_substr($this->errorLog->message, 0, 120),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Quincaillerie] Erreur technique inattendue')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Une erreur inattendue est survenue sur l\'application :')
            ->line('**'.$this->errorLog->exception_class.'**')
            ->line($this->errorLog->message)
            ->line('Fichier : '.$this->errorLog->file.':'.$this->errorLog->line)
            ->when($this->errorLog->url, fn ($mail) => $mail->line('URL : '.$this->errorLog->url))
            ->action('Voir le journal des erreurs', route('error-logs.index'));
    }
}
