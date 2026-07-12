<?php

namespace App\Console\Commands;

use App\Models\ProductLot;
use App\Models\User;
use App\Notifications\LotExpiringAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('app:check-expiring-lots {--days=30 : Fenêtre de péremption à surveiller, en jours}')]
#[Description('Notifie les admins/magasiniers des lots dont la péremption approche (à exécuter régulièrement)')]
class CheckExpiringLots extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $days = (int) $this->option('days');
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'magasinier']))->get();
        $notified = 0;

        ProductLot::with('product')
            ->whereNotNull('expiry_date')
            ->get()
            ->filter(fn (ProductLot $lot) => $lot->currentQuantity() > 0 && $lot->expiresWithin($days))
            ->each(function (ProductLot $lot) use ($recipients, &$notified) {
                $alreadyNotified = $recipients->first()
                    ?->notifications()
                    ->where('type', LotExpiringAlert::class)
                    ->where('data->lot_id', $lot->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();

                if ($alreadyNotified) {
                    return;
                }

                Notification::send($recipients, new LotExpiringAlert($lot));
                $notified++;
            });

        $this->info($notified.' notification(s) de péremption envoyée(s).');
    }
}
