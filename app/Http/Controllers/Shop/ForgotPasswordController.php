<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function create()
    {
        return view('shop.auth.forgot-password');
    }

    public function store(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Message identique que le compte existe ou non — évite de révéler quels e-mails sont enregistrés.
        Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        return back()->with('status', "Si un compte existe avec cette adresse, un lien de réinitialisation vient d'être envoyé.");
    }
}
