<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\ServiceTicket;
use Illuminate\Http\Request;

/**
 * SAV : dossiers de retour/réparation/échange rattachés à une ligne de vente précise.
 * La réintégration physique de stock (si la résolution l'implique) réutilise
 * SaleLine::returnQuantity() — noyau de stock unifié, jamais dupliqué ici.
 */
class ServiceTicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = ServiceTicket::with(['saleLine.product', 'saleLine.sale.customer', 'openedBy'])
            ->when($request->string('status')->value(), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('service-tickets.index', compact('tickets'));
    }

    public function create(Sale $sale, SaleLine $saleLine)
    {
        abort_if($saleLine->sale_id !== $sale->id, 404);
        $saleLine->load('product');

        return view('service-tickets.create', compact('sale', 'saleLine'));
    }

    public function store(Request $request, Sale $sale, SaleLine $saleLine)
    {
        abort_if($saleLine->sale_id !== $sale->id, 404);

        $data = $request->validate([
            'issue_description' => ['required', 'string', 'max:2000'],
        ]);

        $ticket = ServiceTicket::create([
            'sale_line_id' => $saleLine->id,
            'opened_by' => $request->user()->id,
            'status' => ServiceTicket::STATUS_OUVERT,
            'issue_description' => $data['issue_description'],
        ]);

        return redirect()->route('service-tickets.show', $ticket)->with('success', 'Dossier SAV ouvert.');
    }

    public function show(ServiceTicket $serviceTicket)
    {
        $serviceTicket->load(['saleLine.product', 'saleLine.sale.customer', 'openedBy', 'resolvedBy']);

        return view('service-tickets.show', ['ticket' => $serviceTicket]);
    }

    public function resolve(Request $request, ServiceTicket $serviceTicket)
    {
        $data = $request->validate([
            'resolution_type' => ['required', 'in:reparation,echange,remboursement,refuse'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            // Pour échange/remboursement : quantité à réintégrer physiquement (noyau de stock unifié).
            'return_quantity' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $serviceTicket->load('saleLine');

        if (in_array($data['resolution_type'], [ServiceTicket::RESOLUTION_ECHANGE, ServiceTicket::RESOLUTION_REMBOURSEMENT], true)
            && (float) ($data['return_quantity'] ?? 0) > 0) {
            $serviceTicket->saleLine->returnQuantity(
                (float) $data['return_quantity'],
                $request->user()->id,
                'SAV #'.$serviceTicket->id.' — '.$data['resolution_type'],
            );
        }

        $serviceTicket->resolve($request->user()->id, $data['resolution_type'], $data['resolution_notes'] ?? null);

        return redirect()->route('service-tickets.show', $serviceTicket)->with('success', 'Dossier SAV résolu.');
    }
}
