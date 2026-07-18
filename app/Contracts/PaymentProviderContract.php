<?php

namespace App\Contracts;

use App\Models\Order;
use App\Services\Payment\PaymentInitiationResult;
use App\Services\Payment\PaymentWebhookPayload;
use Illuminate\Http\Request;

/**
 * Un seul contrat pour toutes les méthodes de paiement en ligne (MTN MoMo, Orange
 * Money — via agrégateur — et le mode simulation). Le webhook est le SEUL
 * déclencheur de la confirmation ; initiate() ne fait jamais passer une commande
 * à "payée" par lui-même.
 */
interface PaymentProviderContract
{
    /** Démarre une tentative de paiement — ne confirme jamais rien, retourne juste une référence et des instructions. */
    public function initiate(Order $order, string $method): PaymentInitiationResult;

    /** Vérifie l'authenticité de la requête webhook (signature) avant tout traitement. */
    public function verifyWebhookSignature(Request $request): bool;

    /** Extrait un format normalisé (référence, statut, montant) depuis le payload brut du fournisseur. */
    public function parseWebhookPayload(Request $request): PaymentWebhookPayload;

    /** Nom court utilisé en base (payments.provider) et dans l'URL du webhook. */
    public function name(): string;
}
