<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SaleLine;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class StockReportController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'supplier'])->where('active', true)->get();

        $recentLines = SaleLine::with('product')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->where('created_at', '>=', now()->subDays(90)))
            ->get();

        $salesByProduct = $recentLines->groupBy('product_id');

        // --- Valorisation ---
        $totalValue = (float) $products->sum(fn (Product $p) => $p->currentStock() * (float) $p->purchase_price);
        $valueByCategory = $products->groupBy(fn (Product $p) => $p->category?->name ?? 'Sans catégorie')
            ->map(fn ($group) => $group->sum(fn (Product $p) => $p->currentStock() * (float) $p->purchase_price))
            ->sortDesc();
        $valueByWarehouse = Warehouse::all()->mapWithKeys(function (Warehouse $w) {
            $value = (float) $w->stockMovements()->join('products', 'products.id', '=', 'stock_movements.product_id')
                ->selectRaw('SUM(stock_movements.quantity * products.purchase_price) as total')
                ->value('total');

            return [$w->name => $value ?? 0];
        });

        // --- Rotation & produits dormants / surstock ---
        $rotation = $products->map(function (Product $p) use ($salesByProduct) {
            $soldQty = (float) ($salesByProduct->get($p->id)?->sum('quantity') ?? 0);
            $avgStock = max($p->currentStock(), 0.01);

            return [
                'product' => $p,
                'sold_90d' => $soldQty,
                'turnover' => round($soldQty / $avgStock, 2),
            ];
        });

        $dormant = $rotation->filter(fn ($r) => $r['sold_90d'] == 0 && $r['product']->currentStock() > 0)
            ->sortByDesc(fn ($r) => $r['product']->currentStock() * (float) $r['product']->purchase_price)
            ->take(20);

        $overstock = $products->filter(fn (Product $p) => $p->isOverstock());

        // --- Taux de rupture (proxy : part des produits actifs actuellement à stock ≤ 0) ---
        $stockoutCount = $products->filter(fn (Product $p) => $p->currentStock() <= 0)->count();
        $stockoutRate = $products->count() > 0 ? round($stockoutCount / $products->count() * 100, 1) : 0;

        // --- Analyse ABC (contribution au chiffre d'affaires sur 90 jours) ---
        $revenueByProduct = $salesByProduct->map(fn ($lines, $productId) => [
            'product' => $lines->first()->product,
            'revenue' => (float) $lines->sum(fn (SaleLine $l) => $l->quantity * $l->unit_price),
        ])->sortByDesc('revenue')->values();

        $totalRevenue = $revenueByProduct->sum('revenue');
        $cumulative = 0;
        $abc = $revenueByProduct->map(function ($row) use (&$cumulative, $totalRevenue) {
            $cumulative += $row['revenue'];
            $share = $totalRevenue > 0 ? $cumulative / $totalRevenue : 0;
            $row['class'] = $share <= 0.8 ? 'A' : ($share <= 0.95 ? 'B' : 'C');

            return $row;
        });

        return view('reports.stock', [
            'totalValue' => $totalValue,
            'valueByCategory' => $valueByCategory,
            'valueByWarehouse' => $valueByWarehouse,
            'dormant' => $dormant,
            'overstock' => $overstock,
            'stockoutRate' => $stockoutRate,
            'stockoutCount' => $stockoutCount,
            'abc' => $abc,
        ]);
    }

    /** État du stock actuel, une ligne par produit — pour audit externe ou reprise dans un tableur. */
    public function export()
    {
        $products = Product::with('category')->where('active', true)->orderBy('name')->get();

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['reference', 'name', 'category', 'unit', 'stock_actuel', 'seuil_alerte', 'stock_max', 'valeur_stock', 'statut'], ';');

        foreach ($products as $product) {
            $stock = $product->currentStock();
            $status = match (true) {
                $stock <= 0 => 'rupture',
                $stock <= (float) $product->low_stock_threshold => 'stock_bas',
                $product->isOverstock() => 'surstock',
                $product->isDormant() => 'dormant',
                default => 'ok',
            };

            fputcsv($stream, [
                $product->reference,
                $product->name,
                $product->category?->name,
                $product->unit,
                rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format((float) $product->low_stock_threshold, 2, '.', ''), '0'), '.'),
                $product->max_stock !== null ? rtrim(rtrim(number_format((float) $product->max_stock, 2, '.', ''), '0'), '.') : '',
                number_format($stock * (float) $product->purchase_price, 2, '.', ''),
                $status,
            ], ';');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="etat-stock-'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
