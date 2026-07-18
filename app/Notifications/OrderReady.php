<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Notifie le client que sa commande est prête (retrait) ou prête à partir (livraison). */
class OrderReady extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $message = $this->order->fulfillment_type === 'retrait'
            ? 'Votre commande #'.$this->order->id.' est prête — vous pouvez venir la retirer en magasin.'
            : 'Votre commande #'.$this->order->id.' est prête et va être livrée.';

        return [
            'type' => 'order_ready',
            'order_id' => $this->order->id,
            'message' => $message,
        ];
    }
}
