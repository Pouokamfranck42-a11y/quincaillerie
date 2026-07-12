<?php

namespace App\Notifications;

use App\Models\ProductLot;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LotExpiringAlert extends Notification
{
    use Queueable;

    public function __construct(public ProductLot $lot)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lot_expiring',
            'lot_id' => $this->lot->id,
            'product_id' => $this->lot->product_id,
            'product_name' => $this->lot->product->name,
            'lot_number' => $this->lot->lot_number,
            'expiry_date' => $this->lot->expiry_date?->format('Y-m-d'),
            'message' => 'Péremption proche : '.$this->lot->product->name.' — lot '.$this->lot->lot_number.' ('.$this->lot->expiry_date?->format('d/m/Y').')',
        ];
    }
}
