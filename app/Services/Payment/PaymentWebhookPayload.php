<?php

namespace App\Services\Payment;

/** Format normalisé d'un événement de paiement, quel que soit le fournisseur d'origine. */
class PaymentWebhookPayload
{
    public function __construct(
        public readonly string $reference,
        public readonly string $status, // 'success' | 'failed'
        public readonly float $amount,
    ) {
    }
}
