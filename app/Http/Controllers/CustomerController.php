<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

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

    public function export()
    {
        $customers = Customer::orderBy('name')->get();

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['name', 'type', 'phone', 'email', 'address', 'niu', 'credit_limit', 'payment_terms_days'], ';');

        foreach ($customers as $customer) {
            fputcsv($stream, [
                $customer->name,
                $customer->type,
                $customer->phone,
                $customer->email,
                $customer->address,
                $customer->niu,
                number_format((float) $customer->credit_limit, 2, '.', ''),
                $customer->payment_terms_days,
            ], ';');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="clients-'.now()->format('Y-m-d').'.csv"',
        ]);
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
        $customer->update($this->validated($request, $customer));

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

    /**
     * Réinitialisation "par admin" (le client ne peut pas/plus accéder à son e-mail) :
     * réutilise le même broker que le flux self-service de la boutique — le staff
     * déclenche l'envoi, il ne choisit jamais le mot de passe à la place du client.
     */
    public function sendPasswordReset(Customer $customer)
    {
        if (blank($customer->email)) {
            return back()->with('error', "Ce client n'a pas d'adresse e-mail enregistrée — renseignez-en une avant d'envoyer un lien.");
        }

        Password::broker('customers')->sendResetLink(['email' => $customer->email]);

        return back()->with('success', 'Lien de réinitialisation envoyé à '.$customer->email.'.');
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

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'address' => ['nullable', 'string'],
            'type' => ['required', 'in:particulier,professionnel'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
        ], [
            'email.unique' => 'Un client existe déjà avec cette adresse e-mail.',
        ]);
    }
}
