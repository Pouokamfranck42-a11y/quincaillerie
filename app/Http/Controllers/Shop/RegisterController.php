<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function create()
    {
        return view('shop.auth.register');
    }

    /**
     * Un client déjà connu au comptoir (créé par le personnel, sans mot de passe) peut
     * activer un compte web sur la même fiche — une seule base, un seul historique.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $existing = Customer::where('email', $data['email'])->first();

        if ($existing && $existing->hasWebAccount()) {
            throw ValidationException::withMessages([
                'email' => 'Un compte existe déjà avec cette adresse — connectez-vous.',
            ]);
        }

        $customer = $existing ?? new Customer(['type' => 'particulier']);
        $customer->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? $customer->phone,
            'password' => $data['password'],
        ]);
        $customer->save();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->intended(route('shop.account.index'));
    }
}
