<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Payment\WebhookController;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Bouton "mode test" visible uniquement quand PAYMENT_MODE=simulation. Construit une
 * requête de webhook correctement signée (avec le secret de simulation) et l'envoie
 * au VRAI WebhookController — donc c'est le code de production qui est exercé
 * (vérification de signature, idempotence, confirmation de commande), pas un raccourci.
 */
class PaymentSimulationController extends Controller
{
    public function confirm(Order $order, WebhookController $webhookController)
    {
        abort_unless(config('services.payment.mode') === 'simulation', 404);
        abort_unless($order->customer_id === auth('customer')->id(), 404);

        $payment = $order->payments()->where('status', Payment::STATUS_PENDING)->latest()->firstOrFail();

        $body = json_encode([
            'reference' => $payment->provider_reference,
            'status' => 'success',
            'amount' => (float) $payment->amount,
        ]);
        $signature = hash_hmac('sha256', $body, config('services.payment.simulation_secret'));

        $webhookRequest = Request::create(
            uri: route('webhooks.payment', ['provider' => 'simulation']),
            method: 'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
        $webhookRequest->headers->set('X-Signature', $signature);

        $webhookController->__invoke($webhookRequest, 'simulation');

        return redirect()->route('shop.account.orders.show', $order)
            ->with('success', 'Paiement simulé confirmé — la commande est maintenant payée.');
    }
}
