<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AnomalyDetected extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(
        public string $anomalyType,
        public string $message,
        public array $data = [],
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return array_merge(['type' => 'anomaly_'.$this->anomalyType, 'message' => $this->message], $this->data);
    }
}
