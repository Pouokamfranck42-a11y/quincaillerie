<?php

namespace App\Http\Controllers\Shop;

use App\Contracts\PaymentProviderContract;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function create(Request $request)
    {
        [$lines, $total] = CartController::buildLines($request);

        if (empty($lines)) {
            return redirect()->route('shop.cart.index')->with('error', 'Votre panier est vide.');
        }

        return view('shop.checkout.create', ['lines' => $lines, 'total' => $total]);
    }

    /**
     * Passe la commande (Order::place — Phase 4) : le stock est réservé, pas déduit.
     * Pour un paiement Mobile Money, démarre la tentative auprès du fournisseur et
     * crée un Payment "pending" — c'est le WEBHOOK, plus tard, qui confirmera
     * réellement le paiement et déclenchera la déduction (Order::confirmPayment).
     * Pour un paiement à la livraison, aucune tentative en ligne : la confirmation
     * se fait manuellement par le personnel à la remise (voir StaffOrderController).
     */
    public function store(Request $request, PaymentProviderContract $paymentProvider)
    {
        $data = $request->validate([
            'fulfillment_type' => ['required', 'in:livraison,retrait'],
            'delivery_address' => ['required_if:fulfillment_type,livraison', 'nullable', 'string', 'max:1000'],
            'delivery_phone' => ['required', 'string', 'max:50'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:mobile_money_mtn,mobile_money_orange,a_la_livraison'],
        ]);

        [$lines] = CartController::buildLines($request);

        if (empty($lines)) {
            return redirect()->route('shop.cart.index')->with('error', 'Votre panier est vide.');
        }

        $cartItems = array_map(fn ($line) => [
            'product' => $line['product'],
            'quantity' => $line['quantity'],
        ], $lines);

        try {
            $order = Order::place(
                cartItems: $cartItems,
                customerId: auth('customer')->id(),
                paymentMethod: $data['payment_method'],
                fulfillmentType: $data['fulfillment_type'],
                deliveryAddress: $data['delivery_address'] ?? null,
                deliveryPhone: $data['delivery_phone'],
                deliveryNotes: $data['delivery_notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $request->session()->forget('shop_cart');

        if (in_array($data['payment_method'], ['mobile_money_mtn', 'mobile_money_orange'], true)) {
            $initiation = $paymentProvider->initiate($order, $data['payment_method']);

            Payment::create([
                'payable_type' => Order::class,
                'payable_id' => $order->id,
                'amount' => $order->total,
                'method' => $data['payment_method'],
                'status' => Payment::STATUS_PENDING,
                'provider' => $paymentProvider->name(),
                'provider_reference' => $initiation->reference,
            ]);

            return redirect()->route('shop.account.orders.show', $order)
                ->with('success', 'Commande #'.$order->id.' passée. '.$initiation->customerMessage);
        }

        return redirect()->route('shop.account.orders.show', $order)
            ->with('success', 'Commande #'.$order->id.' passée — le paiement se fera à la livraison.');
    }
}
