<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Sale;

class InvoiceController extends Controller
{
    /** Génère (ou retrouve) la facture d'une vente, puis l'affiche. */
    public function store(Sale $sale)
    {
        $invoice = Invoice::generateFor($sale);

        return redirect()->route('invoices.show', $invoice);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('invoiceable.lines.product', 'customer');

        return view('invoices.show', compact('invoice'));
    }
}
