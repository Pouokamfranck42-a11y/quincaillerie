<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleLine;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? Carbon::today()->subDays(29);
        $to = $request->date('to') ?? Carbon::today();

        $sales = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $salesByDay = $sales->groupBy(fn (Sale $s) => $s->created_at->format('Y-m-d'))
            ->map(fn ($group) => $group->sum('total'))
            ->sortKeys();

        $lines = SaleLine::with('product')
            ->whereHas('sale', function ($q) use ($from, $to) {
                $q->where('status', 'completed')
                    ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
            })
            ->get();

        $topProducts = $lines->groupBy('product_id')
            ->map(function ($group) {
                $product = $group->first()->product;

                return [
                    'name' => $product->name,
                    'quantity' => $group->sum('quantity'),
                    'revenue' => $group->sum(fn (SaleLine $l) => $l->quantity * $l->unit_price),
                ];
            })
            ->sortByDesc('revenue')
            ->take(10);

        $grossMargin = $lines->sum(fn (SaleLine $l) => ($l->unit_price - $l->product->purchase_price) * $l->quantity);

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'totalSales' => $sales->sum('total'),
            'totalTax' => $sales->sum('tax_amount'),
            'salesCount' => $sales->count(),
            'grossMargin' => $grossMargin,
            'salesByDay' => $salesByDay,
            'topProducts' => $topProducts,
        ]);
    }
}
