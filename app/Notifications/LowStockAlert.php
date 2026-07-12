<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    use Queueable;

    public function __construct(public Product $product)
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
            'type' => 'low_stock',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'stock' => $this->product->currentStock(),
            'threshold' => (float) $this->product->low_stock_threshold,
            'message' => 'Stock bas : '.$this->product->name.' ('.$this->product->currentStock().' '.$this->product->unit.')',
        ];
    }
}
