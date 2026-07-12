<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:compute-cross-sell')]
#[Description("Calcule les associations de produits fréquemment achetés ensemble, à partir de l'historique des ventes (aucun appel IA — statistique pure)")]
class ComputeCrossSell extends Command
{
    private const TOP_N_PER_PRODUCT = 5;

    public function handle(): void
    {
        $pairs = DB::table('sale_lines as sl1')
            ->join('sale_lines as sl2', function ($join) {
                $join->on('sl1.sale_id', '=', 'sl2.sale_id')
                    ->on('sl1.product_id', '!=', 'sl2.product_id');
            })
            ->join('sales', 'sales.id', '=', 'sl1.sale_id')
            ->where('sales.status', 'completed')
            ->select('sl1.product_id as product_id', 'sl2.product_id as associated_product_id')
            ->selectRaw('COUNT(DISTINCT sl1.sale_id) as co_occurrence_count')
            ->groupBy('sl1.product_id', 'sl2.product_id')
            ->get();

        $rows = [];
        foreach ($pairs->groupBy('product_id') as $productId => $group) {
            foreach ($group->sortByDesc('co_occurrence_count')->take(self::TOP_N_PER_PRODUCT) as $row) {
                $rows[] = [
                    'product_id' => $productId,
                    'associated_product_id' => $row->associated_product_id,
                    'co_occurrence_count' => $row->co_occurrence_count,
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('product_associations')->truncate();

        if ($rows !== []) {
            DB::table('product_associations')->insert($rows);
        }

        $this->info(count($rows).' association(s) de produits calculée(s).');
    }
}
