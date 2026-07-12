<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use Illuminate\Http\Request;

class CashRegisterSessionController extends Controller
{
    public function terminal(Request $request)
    {
        $session = CashRegisterSession::openFor($request->user()->id);

        if (! $session) {
            return view('pos.open');
        }

        return view('pos.terminal', ['session' => $session]);
    }

    public function open(Request $request)
    {
        if (CashRegisterSession::openFor($request->user()->id)) {
            return redirect()->route('pos.index');
        }

        $data = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
        ]);

        CashRegisterSession::create([
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'opening_amount' => $data['opening_amount'],
            'status' => CashRegisterSession::STATUS_OPEN,
        ]);

        return redirect()->route('pos.index')->with('success', 'Caisse ouverte.');
    }

    public function close(Request $request)
    {
        $session = CashRegisterSession::openFor($request->user()->id);

        if (! $session) {
            return redirect()->route('pos.index');
        }

        $data = $request->validate([
            'closing_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $expected = (float) $session->opening_amount + $session->salesTotal();
        $gap = round((float) $data['closing_amount'] - $expected, 2);

        $session->update([
            'closing_amount' => $data['closing_amount'],
            'closed_at' => now(),
            'status' => CashRegisterSession::STATUS_CLOSED,
        ]);

        if (abs($gap) < 0.01) {
            return redirect()->route('pos.index')->with('success', 'Caisse fermée, montant conforme ('.number_format($expected, 0, ',', ' ').' FCFA).');
        }

        $sign = $gap > 0 ? 'excédent' : 'manquant';

        return redirect()->route('pos.index')->with('error', 'Caisse fermée avec un écart : '.$sign.' de '.number_format(abs($gap), 0, ',', ' ').' FCFA par rapport au montant attendu.');
    }
}
