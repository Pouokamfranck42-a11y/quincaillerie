<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function index()
    {
        $quotes = Quote::with('customer')->withCount('lines')->latest()->paginate(20);

        return view('quotes.index', compact('quotes'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'pro_price', 'unit']);

        return view('quotes.create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $taxRate = 18;

        $quote = DB::transaction(function () use ($data, $request, $taxRate) {
            $subtotal = collect($data['lines'])->sum(fn ($line) => $line['quantity'] * $line['unit_price']);
            $taxAmount = round($subtotal * ($taxRate / 100), 2);

            $quote = Quote::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount,
                'status' => Quote::STATUS_BROUILLON,
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $quote->lines()->create($line);
            }

            return $quote;
        });

        return redirect()->route('quotes.show', $quote)->with('success', 'Devis créé.');
    }

    public function show(Quote $quote)
    {
        $quote->load(['customer', 'user', 'lines.product']);

        return view('quotes.show', compact('quote'));
    }

    public function convert(Request $request, Quote $quote)
    {
        if ($quote->status === Quote::STATUS_CONVERTI) {
            return back()->with('error', 'Ce devis a déjà été converti en vente.');
        }

        $session = CashRegisterSession::openFor($request->user()->id);

        if (! $session) {
            return back()->with('error', 'Ouvrez la caisse avant de convertir ce devis en vente.');
        }

        $data = $request->validate([
            'payment_method' => ['required', 'in:especes,carte,mobile,credit'],
        ]);

        $quote->load('lines.product');
        $sale = $quote->convertToSale($session, $request->user()->id, $data['payment_method']);

        return redirect()->route('quotes.show', $quote)->with('success', 'Devis converti en vente #'.$sale->id.'.');
    }
}
