<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Import CSV de clients (Phase 4) — plus simple que ProductImportController (pas d'étape de
 * revue intermédiaire) : les données client n'ont ni prix ni variantes, le risque d'une
 * mauvaise ligne est bien plus faible qu'un mauvais prix de vente importé en masse. Détection
 * de doublon par e-mail (identifiant naturel le plus fiable ici) — une ligne dont l'e-mail
 * correspond à un client existant MET À JOUR plutôt que dupliquer.
 */
class CustomerImportController extends Controller
{
    private const HEADERS = ['name', 'type', 'phone', 'email', 'address', 'niu', 'credit_limit', 'payment_terms_days'];

    public function create()
    {
        return view('customers.import');
    }

    public function template()
    {
        $example = ['Jean Dupont', 'particulier', '+237600000000', 'jean@example.com', 'Douala', '', '0', '0'];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, self::HEADERS, ';');
        fputcsv($stream, $example, ';');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-clients.csv"',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'clients' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('clients')->getRealPath();
        $handle = fopen($path, 'r');

        // Excel en français exporte souvent en Windows-1252 — même précaution que
        // ProductImportParser pour ne pas produire des noms/adresses illisibles.
        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            return back()->with('error', 'Fichier vide ou illisible.');
        }
        $header = array_map(fn ($h) => mb_check_encoding($h, 'UTF-8') ? trim($h) : trim(mb_convert_encoding($h, 'UTF-8', 'Windows-1252')), $header);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($handle, $delimiter, $header, &$created, &$updated, &$skipped) {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count($row) === 1 && trim($row[0]) === '') {
                    continue; // ligne vide en fin de fichier
                }

                $row = array_map(fn ($v) => mb_check_encoding((string) $v, 'UTF-8') ? $v : mb_convert_encoding((string) $v, 'UTF-8', 'Windows-1252'), $row);
                $data = array_combine($header, array_pad($row, count($header), null));

                $name = trim((string) ($data['name'] ?? ''));
                if ($name === '') {
                    $skipped++;

                    continue;
                }

                $email = filled($data['email'] ?? null) ? trim($data['email']) : null;
                $type = in_array($data['type'] ?? null, ['particulier', 'professionnel'], true) ? $data['type'] : 'particulier';

                $payload = [
                    'name' => $name,
                    'type' => $type,
                    'phone' => ($data['phone'] ?? null) ?: null,
                    'email' => $email,
                    'address' => ($data['address'] ?? null) ?: null,
                    'niu' => ($data['niu'] ?? null) ?: null,
                    'credit_limit' => is_numeric($data['credit_limit'] ?? null) ? (float) $data['credit_limit'] : 0,
                    'payment_terms_days' => is_numeric($data['payment_terms_days'] ?? null) ? (int) $data['payment_terms_days'] : 30,
                ];

                $existing = $email ? Customer::where('email', $email)->first() : null;

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    Customer::create($payload);
                    $created++;
                }
            }
        });

        fclose($handle);

        return redirect()->route('customers.index')
            ->with('success', "{$created} client(s) créé(s), {$updated} mis à jour".($skipped > 0 ? ", {$skipped} ligne(s) ignorée(s) (nom manquant)" : '').'.');
    }
}
