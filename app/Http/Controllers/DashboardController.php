<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $todaySales = Sale::whereDate('created_at', today())->where('status', 'completed');

        $products = Product::where('active', true)
            ->withSum('stockMovements as stock_quantity', 'quantity')
            ->get();

        $lowStockCount = $products->filter(fn (Product $p) => (float) ($p->stock_quantity ?? 0) <= (float) $p->low_stock_threshold)->count();
        $stockoutCount = $products->filter(fn (Product $p) => (float) ($p->stock_quantity ?? 0) <= 0)->count();
        $dormantCount = $products->filter(fn (Product $p) => $p->isDormant())->count();
        $overstockCount = $products->filter(fn (Product $p) => $p->isOverstock())->count();
        $stockValue = (float) $products->sum(fn (Product $p) => (float) ($p->stock_quantity ?? 0) * (float) $p->purchase_price);

        // Marge et rotation sur 90 jours (même fenêtre que reports.stock, pour ne pas avoir deux
        // définitions différentes de "récent" dans l'application).
        $recentLines = SaleLine::with('product')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->where('created_at', '>=', now()->subDays(90)))
            ->get();
        $revenue90d = (float) $recentLines->sum(fn (SaleLine $l) => $l->quantity * $l->unit_price);
        $cost90d = (float) $recentLines->sum(fn (SaleLine $l) => $l->quantity * (float) $l->product->purchase_price);
        $marginPercent = $revenue90d > 0 ? round((($revenue90d - $cost90d) / $revenue90d) * 100, 1) : 0;
        // Nombre de fois où le stock s'est "renouvelé" sur la période, en valeur (coût des ventes
        // rapporté à la valeur du stock actuel) — pas d'historique de valorisation quotidienne,
        // donc pas de moyenne glissante possible : le stock actuel sert de référence.
        $turnoverRate = $stockValue > 0 ? round($cost90d / $stockValue, 2) : 0;

        $recentSales = Sale::with(['user', 'lines'])
            ->where('status', 'completed')
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard.index', [
            'todaySalesTotal' => (clone $todaySales)->sum('total'),
            'todaySalesCount' => (clone $todaySales)->count(),
            'lowStockCount' => $lowStockCount,
            'stockoutCount' => $stockoutCount,
            'dormantCount' => $dormantCount,
            'overstockCount' => $overstockCount,
            'stockValue' => $stockValue,
            'marginPercent' => $marginPercent,
            'turnoverRate' => $turnoverRate,
            'recentSales' => $recentSales,
        ]);
    }
}
