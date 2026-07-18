<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Panier en session — pas de compte requis pour composer un panier, seulement pour
 * passer commande (Phase 5 : le tunnel exige une connexion à ce moment-là).
 */
class CartController extends Controller
{
    private const SESSION_KEY = 'shop_cart';

    public function index(Request $request)
    {
        [$lines, $total] = self::buildLines($request);

        return view('shop.cart.index', ['lines' => $lines, 'total' => $total]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $product = Product::where('id', $data['product_id'])->published()->firstOrFail();

        if ((float) $data['quantity'] > $product->availableStock()) {
            throw ValidationException::withMessages([
                'quantity' => "Seulement {$product->availableStock()} {$product->unit} disponible(s) pour {$product->name}.",
            ]);
        }

        $cart = $request->session()->get(self::SESSION_KEY, []);
        $cart[$product->id] = ($cart[$product->id] ?? 0) + (float) $data['quantity'];
        $request->session()->put(self::SESSION_KEY, $cart);

        return redirect()->route('shop.cart.index')->with('success', $product->name.' ajouté au panier.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cart = $request->session()->get(self::SESSION_KEY, []);

        foreach ($data['quantities'] as $productId => $quantity) {
            if ((float) $quantity <= 0) {
                unset($cart[$productId]);

                continue;
            }
            $cart[$productId] = (float) $quantity;
        }

        $request->session()->put(self::SESSION_KEY, $cart);

        return redirect()->route('shop.cart.index')->with('success', 'Panier mis à jour.');
    }

    public function destroy(Request $request, int $productId)
    {
        $cart = $request->session()->get(self::SESSION_KEY, []);
        unset($cart[$productId]);
        $request->session()->put(self::SESSION_KEY, $cart);

        return redirect()->route('shop.cart.index')->with('success', 'Article retiré du panier.');
    }

    /** @return array{0: array<int, array>, 1: float} */
    public static function buildLines(Request $request): array
    {
        $cart = $request->session()->get(self::SESSION_KEY, []);
        $customer = auth('customer')->user();

        $lines = [];
        $total = 0.0;

        foreach ($cart as $productId => $quantity) {
            $product = Product::find($productId);
            if (! $product || ! $product->published_online || ! $product->active) {
                continue;
            }

            $price = $product->priceFor($customer);
            $lineTotal = $price * (float) $quantity;
            $total += $lineTotal;

            $lines[] = [
                'product' => $product,
                'quantity' => (float) $quantity,
                'price' => $price,
                'line_total' => $lineTotal,
                'available' => $product->availableStock(),
            ];
        }

        return [$lines, $total];
    }
}
