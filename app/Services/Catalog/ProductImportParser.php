<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;

/**
 * Parse un fichier CSV de catalogue (colonnes documentées dans products.import),
 * normalise les valeurs et résout / détecte les doublons contre la base — en lecture
 * seule. Rien n'est écrit en base ici : c'est ProductImportController::store() qui
 * persiste, à partir des valeurs revues par l'utilisateur sur la page de contrôle.
 */
class ProductImportParser
{
    public const MAX_ROWS = 500;

    public const REQUIRED_HEADERS = ['reference', 'nom', 'prix_achat', 'prix_vente'];

    public const KNOWN_HEADERS = [
        'reference', 'nom', 'marque', 'description', 'categorie', 'fournisseur',
        'reference_fournisseur', 'famille', 'variante', 'prix_achat', 'prix_vente',
        'prix_pro', 'code_barre', 'emplacement', 'unite', 'unite_vente',
        'facteur_unite_vente', 'unite_achat', 'facteur_unite_achat', 'seuil_alerte',
        'stock_securite', 'point_commande', 'stock_max', 'suit_lots', 'actif', 'stock_initial',
    ];

    /**
     * @return array{rows: array<int, array<string, mixed>>, fileErrors: array<int, string>}
     */
    public function parse(UploadedFile $file): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = $this->toUtf8($content);

        $lines = preg_split("/\r\n|\r|\n/", trim($content));
        $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== ''));

        if (empty($lines)) {
            return ['rows' => [], 'fileErrors' => ['Le fichier est vide.']];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => $this->slug($h), str_getcsv($lines[0], $delimiter));

        $missing = array_diff(self::REQUIRED_HEADERS, $header);
        if (! empty($missing)) {
            return ['rows' => [], 'fileErrors' => ["Colonnes obligatoires manquantes : ".implode(', ', $missing).'.']];
        }

        $dataLines = array_slice($lines, 1);
        if (count($dataLines) > self::MAX_ROWS) {
            return ['rows' => [], 'fileErrors' => ['Le fichier contient '.count($dataLines).' lignes de données, la limite est de '.self::MAX_ROWS.' par import.']];
        }

        $rows = [];
        $seenReferences = [];
        $seenBarcodes = [];

        foreach ($dataLines as $i => $line) {
            $lineNumber = $i + 2;
            $values = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = $this->normalizeText((string) ($values[$idx] ?? ''));
            }

            $rows[] = $this->buildRow($row, $lineNumber, $seenReferences, $seenBarcodes);
        }

        return ['rows' => $rows, 'fileErrors' => []];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $seenReferences
     * @param  array<string, int>  $seenBarcodes
     * @return array<string, mixed>
     */
    private function buildRow(array $row, int $lineNumber, array &$seenReferences, array &$seenBarcodes): array
    {
        $reference = $row['reference'] ?? '';
        $name = $row['nom'] ?? '';
        $purchasePrice = $this->toFloat($row['prix_achat'] ?? '');
        $salePrice = $this->toFloat($row['prix_vente'] ?? '');

        if ($reference === '' || $name === '' || $purchasePrice === null || $salePrice === null) {
            return $this->errorRow($row, $lineNumber, "Champs obligatoires manquants ou invalides (référence, nom, prix d'achat, prix de vente).");
        }

        $referenceKey = mb_strtolower($reference);
        if (isset($seenReferences[$referenceKey])) {
            return $this->errorRow($row, $lineNumber, "Référence en double dans le fichier (déjà à la ligne {$seenReferences[$referenceKey]}).");
        }
        $seenReferences[$referenceKey] = $lineNumber;

        $existingByReference = Product::withTrashed()->where('reference', 'ilike', $reference)->first();
        $barcode = $row['code_barre'] ?? '';

        if ($barcode !== '') {
            if (isset($seenBarcodes[$barcode])) {
                return $this->errorRow($row, $lineNumber, "Code-barres en double dans le fichier (déjà à la ligne {$seenBarcodes[$barcode]}).");
            }
            $seenBarcodes[$barcode] = $lineNumber;
        }

        $existingByBarcode = $barcode !== '' ? Product::withTrashed()->where('barcode', $barcode)->first() : null;

        if ($existingByBarcode && (! $existingByReference || $existingByBarcode->id !== $existingByReference->id)) {
            return $this->errorRow($row, $lineNumber, "Le code-barres est déjà utilisé par un autre produit (#{$existingByBarcode->id} — {$existingByBarcode->name}).");
        }

        $messages = [];
        $status = 'new';
        $existing = $existingByReference;

        if ($existing) {
            $status = 'update';
            $messages[] = "Produit existant #{$existing->id} : sera mis à jour.";
            if ($existing->trashed()) {
                $messages[] = 'Ce produit est actuellement dans la corbeille — sera restauré.';
            }
        } else {
            $normalizedName = $this->normalizeForComparison($name);
            $nameMatch = Product::where('active', true)->get(['id', 'name'])
                ->first(fn ($p) => $this->normalizeForComparison($p->name) === $normalizedName);
            if ($nameMatch) {
                $messages[] = "Nom très proche du produit existant #{$nameMatch->id} ({$nameMatch->name}) — vérifiez qu'il ne s'agit pas d'un doublon.";
            }
        }

        $categoryName = trim($row['categorie'] ?? '');
        $categoryId = null;
        if ($categoryName !== '') {
            $category = Category::where('name', 'ilike', $categoryName)->first();
            $categoryId = $category?->id;
            if (! $category) {
                $messages[] = "Catégorie « {$categoryName} » introuvable — sera créée.";
            }
        }

        $familyName = trim($row['famille'] ?? '');
        $familyId = null;
        if ($familyName !== '') {
            $family = ProductFamily::where('name', 'ilike', $familyName)->first();
            $familyId = $family?->id;
            if (! $family) {
                $messages[] = "Famille « {$familyName} » introuvable — sera créée.";
            }
        }

        $supplierName = trim($row['fournisseur'] ?? '');
        $supplierId = null;
        if ($supplierName !== '') {
            $supplier = Supplier::where('name', 'ilike', $supplierName)->first();
            $supplierId = $supplier?->id;
            if (! $supplier) {
                $messages[] = "Fournisseur « {$supplierName} » introuvable — produit importé sans fournisseur (créez-le d'abord dans Fournisseurs si besoin).";
            }
        }

        $tracksLots = $this->toBool($row['suit_lots'] ?? '', false);
        $active = $this->toBool($row['actif'] ?? '', true);

        $stockInitial = $this->toFloat($row['stock_initial'] ?? '');
        if ($stockInitial !== null && $tracksLots) {
            $messages[] = "Produit à lots : le stock initial ne peut pas être pré-rempli automatiquement — saisissez-le manuellement (mouvement d'entrée avec numéro de lot) après import.";
            $stockInitial = null;
        }
        if ($stockInitial !== null && $existing) {
            $messages[] = 'Produit existant : le stock initial du fichier est ignoré (utilisez un inventaire ou un mouvement de stock pour ajuster un produit déjà suivi).';
            $stockInitial = null;
        }

        $data = [
            'reference' => $reference,
            'name' => $name,
            'brand' => trim($row['marque'] ?? '') ?: null,
            'description' => trim($row['description'] ?? '') ?: null,
            'category_id' => $categoryId,
            'category_name' => $categoryId ? null : ($categoryName ?: null),
            'supplier_id' => $supplierId,
            'supplier_sku' => trim($row['reference_fournisseur'] ?? '') ?: null,
            'product_family_id' => $familyId,
            'family_name' => $familyId ? null : ($familyName ?: null),
            'variant_attributes' => $this->parseVariant($row['variante'] ?? ''),
            'purchase_price' => $purchasePrice,
            'sale_price' => $salePrice,
            'pro_price' => $this->toFloat($row['prix_pro'] ?? ''),
            'barcode' => $barcode !== '' ? $barcode : null,
            'location' => trim($row['emplacement'] ?? '') ?: null,
            'unit' => trim($row['unite'] ?? '') ?: 'unité',
            'sale_unit' => trim($row['unite_vente'] ?? '') ?: null,
            'sale_unit_factor' => $this->toFloat($row['facteur_unite_vente'] ?? '') ?? 1.0,
            'purchase_unit' => trim($row['unite_achat'] ?? '') ?: null,
            'purchase_unit_factor' => $this->toFloat($row['facteur_unite_achat'] ?? '') ?? 1.0,
            'low_stock_threshold' => $this->toFloat($row['seuil_alerte'] ?? '') ?? 5.0,
            'security_stock' => $this->toFloat($row['stock_securite'] ?? '') ?? 0.0,
            'reorder_point' => $this->toFloat($row['point_commande'] ?? ''),
            'max_stock' => $this->toFloat($row['stock_max'] ?? ''),
            'tracks_lots' => $tracksLots,
            'active' => $active,
        ];

        return [
            'line_number' => $lineNumber,
            'status' => $status,
            'messages' => $messages,
            'importable' => true,
            'raw' => $row,
            'data' => $data,
            'existing_product_id' => $existing?->id,
            'stock_initial' => $stockInitial,
        ];
    }

    /** @param  array<string, string>  $row */
    private function errorRow(array $row, int $lineNumber, string $message): array
    {
        return [
            'line_number' => $lineNumber,
            'status' => 'error',
            'messages' => [$message],
            'importable' => false,
            'raw' => $row,
            'data' => null,
            'existing_product_id' => null,
            'stock_initial' => null,
        ];
    }

    /**
     * Excel sur Windows en français exporte le plus souvent en Windows-1252 (ANSI), pas en
     * UTF-8 — sans conversion, les caractères accentués font planter l'insertion en base
     * (PostgreSQL rejette les octets qui ne sont pas de l'UTF-8 valide).
     */
    private function toUtf8(string $content): string
    {
        if ($content === '' || mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1252');

        return $converted !== false ? $converted : $content;
    }

    private function normalizeText(string $value): string
    {
        $value = str_replace(["\t", "\n", "\r"], ' ', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function slug(string $header): string
    {
        $header = trim($header);
        $header = iconv('UTF-8', 'ASCII//TRANSLIT', $header) ?: $header;
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function normalizeForComparison(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;

        return $value;
    }

    private function toFloat(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function toBool(string $value, bool $default): bool
    {
        $value = trim($value);
        if ($value === '') {
            return $default;
        }

        return in_array(mb_strtolower($value), ['1', 'oui', 'true', 'vrai', 'yes'], true);
    }

    /** @return array<string, string> */
    private function parseVariant(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $pairs = [];
        foreach (explode('|', $value) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }
            [$key, $val] = explode('=', $pair, 2);
            $key = trim($key);
            $val = trim($val);
            if ($key !== '') {
                $pairs[$key] = $val;
            }
        }

        return $pairs;
    }
}
