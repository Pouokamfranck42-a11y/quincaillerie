<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Vue côté personnel sur les commandes web : confirmation de paiement à la livraison
 * (Phase 6) et workflow complet de préparation — préparation/prête/livrée-retirée,
 * annulation (Phase 8).
 */
class OnlineOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('customer')->latest()->paginate(20);

        return view('online-orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load('lines.product', 'customer', 'payments');

        return view('online-orders.show', compact('order'));
    }

    /** Le personnel confirme avoir encaissé le paiement à la remise — déclenche la déduction physique. */
    public function confirmCashOnDelivery(Order $order)
    {
        if ($order->payment_method !== 'a_la_livraison') {
            throw ValidationException::withMessages(['status' => "Cette commande n'est pas en paiement à la livraison."]);
        }

        $order->confirmPayment(auth()->id());

        return redirect()->route('online-orders.show', $order)
            ->with('success', 'Paiement à la livraison confirmé — stock déduit, vente enregistrée.');
    }

    public function startPreparation(Order $order)
    {
        return $this->transition($order, fn () => $order->startPreparation(), 'Commande passée en préparation.');
    }

    public function markReady(Order $order)
    {
        return $this->transition($order, fn () => $order->markReady(), 'Commande marquée prête — le client a été notifié.');
    }

    public function deliver(Order $order)
    {
        return $this->transition($order, fn () => $order->deliver(), 'Commande marquée livrée.');
    }

    public function pickUp(Order $order)
    {
        return $this->transition($order, fn () => $order->pickUp(), 'Retrait client confirmé.');
    }

    public function cancel(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->transition(
            $order,
            fn () => $order->cancel(auth()->id(), $data['reason'] ?? null),
            'Commande annulée — le stock a été régularisé.',
        );
    }

    private function transition(Order $order, callable $action, string $successMessage)
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('online-orders.show', $order)->with('success', $successMessage);
    }
}
