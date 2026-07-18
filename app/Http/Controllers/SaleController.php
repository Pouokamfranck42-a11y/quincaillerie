<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleLine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['user', 'customer'])->withCount('lines')->where('status', 'completed');

        if (! $request->user()->can('ventes.historique_tous')) {
            $query->where('user_id', $request->user()->id);
        }

        $sales = $query->latest()->paginate(30);

        return view('sales.index', compact('sales'));
    }

    public function show(Sale $sale)
    {
        $sale->load(['user', 'customer', 'session', 'lines.product', 'invoices']);

        return view('sales.show', compact('sale'));
    }

    public function print(Sale $sale)
    {
        $sale->load(['user', 'customer', 'lines.product']);

        return view('sales.print', compact('sale'));
    }

    public function returnLine(Request $request, Sale $sale, SaleLine $saleLine)
    {
        abort_if($saleLine->sale_id !== $sale->id, 404);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:'.$saleLine->returnableQuantity()],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $saleLine->returnQuantity((float) $data['quantity'], $request->user()->id, $data['reason'] ?? null);

        return redirect()->route('sales.show', $sale)->with('success', 'Retour enregistré : le stock a été réintégré.');
    }

    public function cancel(Request $request, Sale $sale)
    {
        $sale->load('session');

        try {
            $sale->cancel($request->user()->id, $request->input('reason'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('sales.show', $sale)->with('success', 'Vente annulée — le stock a été réintégré.');
    }
}
