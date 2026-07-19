<?php

namespace App\Http\Controllers\Payment;

use App\Contracts\PaymentProviderContract;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\AggregatorPaymentProvider;
use App\Services\Payment\PaymentWebhookPayload;
use App\Services\Payment\SimulatedPaymentProvider;
use App\Support\DatabaseLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Point d'entrée public (pas d'auth, pas de CSRF — appelé par le serveur de
 * l'agrégateur) et SEUL déclencheur de la confirmation de paiement en ligne.
 * Signature vérifiée avant tout traitement, idempotent via un verrou sur la ligne
 * `payments` : un événement reçu deux fois ne déduit jamais deux fois le stock.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider)
    {
        $paymentProvider = $this->resolve($provider);

        if (! $paymentProvider->verifyWebhookSignature($request)) {
            Log::warning('Webhook paiement rejeté : signature invalide', ['provider' => $provider]);
            abort(401, 'Signature invalide.');
        }

        $payload = $paymentProvider->parseWebhookPayload($request);

        try {
            return $this->process($payload, $provider, $request);
        } catch (ValidationException $e) {
            // Verrou contesté (voir DatabaseLock) : 503 pour que l'agrégateur rejoue
            // l'événement plus tard plutôt que de le considérer définitivement échoué.
            Log::warning('Webhook paiement : verrou contesté, réessai demandé à l\'agrégateur', [
                'provider' => $provider, 'reference' => $payload->reference,
            ]);

            return response()->json(['status' => 'retry_later'], 503);
        }
    }

    private function process(PaymentWebhookPayload $payload, string $provider, Request $request)
    {
        return DB::transaction(function () use ($payload, $provider, $request) {
            $payment = DatabaseLock::guard(
                fn () => Payment::where('provider_reference', $payload->reference)->lockForUpdate()->first(),
                'payment',
                'Paiement en cours de traitement par une autre opération.',
            );

            if (! $payment) {
                Log::warning('Webhook paiement : référence inconnue, ignoré', [
                    'provider' => $provider, 'reference' => $payload->reference,
                ]);

                return response()->json(['status' => 'ignored'], 200);
            }

            // Rejeu (le même événement reçu une deuxième fois) : ne rien refaire, répondre 200 quand même.
            if ($payment->status === Payment::STATUS_SUCCESS) {
                return response()->json(['status' => 'already_processed'], 200);
            }

            $payment->update([
                'status' => $payload->status === 'success' ? Payment::STATUS_SUCCESS : Payment::STATUS_FAILED,
                'raw_payload' => $request->all(),
                'paid_at' => $payload->status === 'success' ? now() : null,
            ]);

            if ($payload->status === 'success') {
                $order = $payment->payable;
                if ($order instanceof Order && $order->status === Order::STATUS_RESERVEE) {
                    $order->confirmPayment();
                }
            }

            return response()->json(['status' => 'ok'], 200);
        });
    }

    private function resolve(string $provider): PaymentProviderContract
    {
        if ($provider === 'simulation') {
            abort_unless(config('services.payment.mode') === 'simulation', 404);

            return new SimulatedPaymentProvider();
        }

        if ($provider === 'aggregator') {
            return new AggregatorPaymentProvider();
        }

        abort(404);
    }
}
