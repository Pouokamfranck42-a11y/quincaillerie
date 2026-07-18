<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

/**
 * Export comptable "façon SYSCOHADA" — journal des ventes en partie double, mapping
 * de comptes SIMPLIFIÉ (701 ventes, 4431 TVA collectée, 411/571 client/caisse).
 * NON VALIDÉ par un comptable : voir le bandeau d'avertissement sur la page.
 */
class AccountingExportController extends Controller
{
    public function index()
    {
        return view('accounting-export.index');
    }

    public function export(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $sales = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$data['from'].' 00:00:00', $data['to'].' 23:59:59'])
            ->orderBy('created_at')
            ->get();

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Date', 'N° pièce', 'Compte', 'Libellé', 'Débit', 'Crédit'], ';');

        foreach ($sales as $sale) {
            $date = $sale->created_at->format('d/m/Y');
            $piece = 'VTE-'.$sale->id;
            $isCredit = $sale->payment_status === 'due';
            $counterpartAccount = $isCredit ? '411000' : '571000';
            $counterpartLabel = $isCredit ? 'Clients' : 'Caisse';

            fputcsv($stream, [$date, $piece, $counterpartAccount, $counterpartLabel.' — vente #'.$sale->id, number_format((float) $sale->total, 2, '.', ''), ''], ';');
            fputcsv($stream, [$date, $piece, '701000', 'Ventes de marchandises — vente #'.$sale->id, '', number_format((float) $sale->subtotal, 2, '.', '')], ';');

            if ((float) $sale->tax_amount > 0) {
                fputcsv($stream, [$date, $piece, '443100', 'TVA collectée — vente #'.$sale->id, '', number_format((float) $sale->tax_amount, 2, '.', '')], ';');
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="export-syscohada-'.$data['from'].'-au-'.$data['to'].'.csv"',
        ]);
    }
}
