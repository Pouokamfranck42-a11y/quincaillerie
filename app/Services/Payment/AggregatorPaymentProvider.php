<?php

namespace App\Services\Payment;

use App\Contracts\PaymentProviderContract;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * SQUELETTE générique pour un agrégateur Mobile Money (CinetPay, Notchpay, PawaPay,
 * Semoa… — à choisir). Aucun agrégateur n'a été précisé dans le brief : la forme
 * exacte des requêtes/réponses ci-dessous est une HYPOTHÈSE RAISONNABLE (proche de
 * ce que la plupart de ces services proposent), PAS une intégration vérifiée contre
 * une vraie API — à ajuster précisément une fois l'agrégateur choisi et des
 * identifiants de test obtenus (même situation que GeminiService avant l'arrivée
 * d'une vraie clé : structure prête, jamais éprouvée en conditions réelles).
 */
class AggregatorPaymentProvider implements PaymentProviderContract
{
    public function initiate(Order $order, string $method): PaymentInitiationResult
    {
        $network = match ($method) {
            'mobile_money_mtn' => 'MTN',
            'mobile_money_orange' => 'ORANGE',
            default => throw new \InvalidArgumentException("Méthode de paiement non gérée par l'agrégateur : {$method}"),
        };

        $response = Http::withToken(config('services.payment.aggregator.api_key'))
            ->post(rtrim((string) config('services.payment.aggregator.base_url'), '/').'/payments', [
                'amount' => (float) $order->total,
                'currency' => 'XAF',
                'network' => $network,
                'phone' => $order->delivery_phone,
                'external_reference' => (string) $order->id,
                'callback_url' => route('webhooks.payment', ['provider' => $this->name()]),
            ])
            ->throw()
            ->json();

        return new PaymentInitiationResult(
            reference: (string) ($response['transaction_id'] ?? ''),
            customerMessage: 'Un message de confirmation Mobile Money vous a été envoyé — validez sur votre téléphone.',
            redirectUrl: $response['payment_url'] ?? null,
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('services.payment.aggregator.webhook_secret');
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header('X-Signature'));
    }

    public function parseWebhookPayload(Request $request): PaymentWebhookPayload
    {
        return new PaymentWebhookPayload(
            reference: (string) $request->input('transaction_id'),
            status: ((string) $request->input('status')) === 'SUCCESSFUL' ? 'success' : 'failed',
            amount: (float) $request->input('amount'),
        );
    }

    public function name(): string
    {
        return 'aggregator';
    }
}
