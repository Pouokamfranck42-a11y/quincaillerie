<?php

namespace App\Services\Payment;

use App\Contracts\PaymentProviderContract;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mode simulation (PAYMENT_MODE=simulation, valeur par défaut) — permet de tester
 * tout le circuit (initiation -> webhook -> confirmation de commande) sans agrégateur
 * réel. Le webhook simulé passe par la MÊME route et le MÊME contrôleur que les
 * fournisseurs réels — seule la signature diffère (secret local au lieu du secret
 * de l'agrégateur), donc c'est le vrai code de webhook qui est exercé, pas un raccourci.
 */
class SimulatedPaymentProvider implements PaymentProviderContract
{
    public function initiate(Order $order, string $method): PaymentInitiationResult
    {
        return new PaymentInitiationResult(
            reference: 'SIM-'.Str::upper(Str::random(10)),
            customerMessage: "Mode simulation : aucun paiement réel n'est envoyé. Utilisez le bouton « Simuler le paiement » sur la page de suivi de la commande.",
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $expected = hash_hmac('sha256', $request->getContent(), $this->secret());

        return hash_equals($expected, (string) $request->header('X-Signature'));
    }

    public function parseWebhookPayload(Request $request): PaymentWebhookPayload
    {
        return new PaymentWebhookPayload(
            reference: (string) $request->input('reference'),
            status: (string) $request->input('status'),
            amount: (float) $request->input('amount'),
        );
    }

    public function name(): string
    {
        return 'simulation';
    }

    public function secret(): string
    {
        return config('services.payment.simulation_secret') ?: 'simulation-secret-non-configure';
    }
}
