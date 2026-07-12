<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()
            ->withSum(['sales as due_total' => fn ($q) => $q->where('payment_status', 'due')], 'total')
            ->withSum(['sales as due_paid' => fn ($q) => $q->where('payment_status', 'due')], 'paid_amount');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        Customer::create($this->validated($request));

        return redirect()->route('customers.index')->with('success', 'Client créé.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.index')->with('success', 'Client mis à jour.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->outstandingBalance() > 0) {
            return back()->with('error', 'Ce client a un encours à crédit non soldé : réglez-le avant de le supprimer.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Client envoyé à la corbeille.');
    }

    public function statement(Customer $customer)
    {
        $dueSales = $customer->sales()->where('payment_status', 'due')->latest()->get();

        return view('customers.statement', compact('customer', 'dueSales'));
    }

    public function recordPayment(Request $request, Customer $customer, \App\Models\Sale $sale)
    {
        abort_if($sale->customer_id !== $customer->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $sale->recordPayment((float) $data['amount']);

        return redirect()->route('customers.statement', $customer)->with('success', 'Paiement enregistré.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'type' => ['required', 'in:particulier,professionnel'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);
    }
}
