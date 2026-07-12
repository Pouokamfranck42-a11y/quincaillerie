<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $todaySales = Sale::whereDate('created_at', today())->where('status', 'completed');

        $lowStockCount = Product::query()
            ->withSum('stockMovements as stock_quantity', 'quantity')
            ->where('active', true)
            ->get()
            ->filter(fn (Product $p) => (float) ($p->stock_quantity ?? 0) <= (float) $p->low_stock_threshold)
            ->count();

        $recentSales = Sale::with(['user', 'lines'])
            ->where('status', 'completed')
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard.index', [
            'todaySalesTotal' => (clone $todaySales)->sum('total'),
            'todaySalesCount' => (clone $todaySales)->count(),
            'lowStockCount' => $lowStockCount,
            'recentSales' => $recentSales,
        ]);
    }
}
