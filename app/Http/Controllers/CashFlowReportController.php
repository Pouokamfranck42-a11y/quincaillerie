<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Sale;

class CashFlowReportController extends Controller
{
    /**
     * Aucun suivi de paiement fournisseur dans le modèle de données actuel (payment_terms est un
     * champ libre, non exploitable) : on suppose conventionnellement un règlement 30 jours après
     * la commande pour toutes les commandes en cours — approximation signalée à l'utilisateur.
     */
    private const SUPPLIER_PAYMENT_ASSUMPTION_DAYS = 30;

    public function index()
    {
        $today = today();

        $dailyCashInflow = (float) Sale::where('status', 'completed')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('total') / 30;

        $dueSales = Sale::where('payment_status', 'due')->whereNotNull('due_date')->get();

        $pendingOrders = PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
            ->whereNotNull('ordered_at')
            ->with('lines')
            ->get();

        $projections = collect([30, 60, 90])->map(function (int $days) use ($today, $dailyCashInflow, $dueSales, $pendingOrders) {
            $windowEnd = $today->copy()->addDays($days);

            $cashInflow = round($dailyCashInflow * $days, 0);
            $creditCollections = (float) $dueSales
                ->filter(fn (Sale $s) => $s->due_date->lte($windowEnd))
                ->sum(fn (Sale $s) => (float) $s->total - (float) $s->paid_amount);

            $outflow = (float) $pendingOrders
                ->filter(fn (PurchaseOrder $po) => $po->ordered_at->copy()->addDays(self::SUPPLIER_PAYMENT_ASSUMPTION_DAYS)->lte($windowEnd))
                ->sum(fn (PurchaseOrder $po) => $po->total() + (float) $po->extra_costs);

            $inflow = $cashInflow + $creditCollections;

            return [
                'days' => $days,
                'cash_inflow' => $cashInflow,
                'credit_collections' => $creditCollections,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => $inflow - $outflow,
            ];
        });

        return view('reports.cash-flow', compact('projections'));
    }
}
