<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Relibère le stock des commandes web réservées mais jamais payées dans le délai
 * imparti (Order::RESERVATION_MINUTES) — le "disponible" doit redevenir vendable
 * pour les autres clients, comptoir ou web.
 */
#[Signature('app:release-expired-reservations')]
#[Description("Annule les commandes web dont la réservation de stock a expiré sans paiement, et relibère le stock correspondant")]
class ReleaseExpiredReservations extends Command
{
    public function handle(): int
    {
        $expiredOrderIds = Reservation::where('status', Reservation::STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->where('reservable_type', Order::class)
            ->pluck('reservable_id')
            ->unique();

        $cancelled = 0;

        foreach ($expiredOrderIds as $orderId) {
            $order = Order::find($orderId);

            if ($order && $order->status === Order::STATUS_RESERVEE) {
                $order->cancel(reason: 'Réservation expirée — paiement non reçu dans le délai imparti.');
                $cancelled++;
            }
        }

        $this->info("{$cancelled} commande(s) annulée(s) pour réservation expirée.");

        return self::SUCCESS;
    }
}
