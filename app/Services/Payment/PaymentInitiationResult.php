<?php

namespace App\Services\Payment;

/** Résultat du démarrage d'un paiement — pas encore une confirmation. */
class PaymentInitiationResult
{
    public function __construct(
        public readonly string $reference,
        public readonly string $customerMessage,
        public readonly ?string $redirectUrl = null,
    ) {
    }
}
