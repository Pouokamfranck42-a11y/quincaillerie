<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Sale;

class CashFlowReportController extends Controller
{
    /**
     * Phase 7 : utilise le délai réel du fournisseur (suppliers.payment_terms_days) quand il
     * est renseigné. Ce n'est qu'en dernier recours — fournisseur sans délai connu — qu'on
     * retombe sur cette hypothèse conventionnelle de 30 jours.
     */
    private const SUPPLIER_PAYMENT_FALLBACK_DAYS = 30;

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
            ->with(['lines', 'supplier'])
            ->get();

        $projections = collect([30, 60, 90])->map(function (int $days) use ($today, $dailyCashInflow, $dueSales, $pendingOrders) {
            $windowEnd = $today->copy()->addDays($days);

            $cashInflow = round($dailyCashInflow * $days, 0);
            $creditCollections = (float) $dueSales
                ->filter(fn (Sale $s) => $s->due_date->lte($windowEnd))
                ->sum(fn (Sale $s) => (float) $s->total - (float) $s->paid_amount);

            $outflow = (float) $pendingOrders
                ->filter(function (PurchaseOrder $po) use ($windowEnd) {
                    $termDays = $po->supplier?->payment_terms_days ?? self::SUPPLIER_PAYMENT_FALLBACK_DAYS;

                    return $po->ordered_at->copy()->addDays($termDays)->lte($windowEnd);
                })
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

        $payables = $pendingOrders->map(function (PurchaseOrder $po) {
            $realTermDays = $po->supplier?->payment_terms_days;
            $termDays = $realTermDays ?? self::SUPPLIER_PAYMENT_FALLBACK_DAYS;

            return [
                'purchase_order' => $po,
                'supplier_name' => $po->supplier?->name ?? '—',
                'due_date' => $po->ordered_at->copy()->addDays($termDays),
                'term_days' => $termDays,
                'is_real_term' => $realTermDays !== null,
                'amount' => $po->total() + (float) $po->extra_costs,
            ];
        })->sortBy('due_date')->values();

        return view('reports.cash-flow', compact('projections', 'payables'));
    }
}
