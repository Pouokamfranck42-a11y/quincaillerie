<x-layout title="Vérifier l'import du catalogue">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-clipboard-check text-primary"></i> Vérifier l'import</h1>
            <p>Rien n'est encore enregistré — décoche les lignes à exclure puis confirme.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-tile good">
            <div class="lbl"><i class="bi bi-plus-circle"></i> Nouveaux produits</div>
            <div class="val">{{ $counts['new'] }}</div>
        </div>
        <div class="stat-tile">
            <div class="lbl"><i class="bi bi-arrow-repeat"></i> Mises à jour</div>
            <div class="val">{{ $counts['update'] }}</div>
        </div>
        <div class="stat-tile @if($counts['error'] > 0) crit @endif">
            <div class="lbl"><i class="bi bi-x-circle"></i> Erreurs (ignorées)</div>
            <div class="val">{{ $counts['error'] }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('products.import.store') }}">
        @csrf
        <div class="card">
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th></th><th>Ligne</th><th>Statut</th><th>Référence</th><th>Nom</th><th class="num">Prix achat</th><th class="num">Prix vente</th><th>Remarques</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr>
                                @if ($row['status'] === 'error')
                                    <td></td>
                                    <td class="mono">{{ $row['line_number'] }}</td>
                                    <td><span class="badge badge-crit">erreur</span></td>
                                    <td>{{ $row['raw']['reference'] ?? '—' }}</td>
                                    <td>{{ $row['raw']['nom'] ?? '—' }}</td>
                                    <td class="num">—</td>
                                    <td class="num">—</td>
                                    <td class="muted" style="font-size:13px">
                                        @foreach ($row['messages'] as $message)
                                            <div><i class="bi bi-exclamation-circle text-danger"></i> {{ $message }}</div>
                                        @endforeach
                                    </td>
                                @else
                                    <td><input type="checkbox" name="rows[{{ $i }}][include]" value="1" checked></td>
                                    <td class="mono">{{ $row['line_number'] }}</td>
                                    <td>
                                        @if ($row['status'] === 'new')
                                            <span class="badge badge-good">nouveau</span>
                                        @else
                                            <span class="badge badge-warn">mise à jour</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['data']['reference'] }}</td>
                                    <td>{{ $row['data']['name'] }}</td>
                                    <td class="num">{{ number_format($row['data']['purchase_price'], 0, ',', ' ') }}</td>
                                    <td class="num">{{ number_format($row['data']['sale_price'], 0, ',', ' ') }}</td>
                                    <td class="muted" style="font-size:13px">
                                        @foreach ($row['messages'] as $message)
                                            <div><i class="bi bi-info-circle"></i> {{ $message }}</div>
                                        @endforeach
                                        @if ($row['stock_initial'] !== null)
                                            <div><i class="bi bi-box-seam"></i> Stock initial : {{ rtrim(rtrim(number_format($row['stock_initial'], 2, ',', ' '), '0'), ',') }}</div>
                                        @endif
                                    </td>

                                    <input type="hidden" name="rows[{{ $i }}][status]" value="{{ $row['status'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][reference]" value="{{ $row['data']['reference'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][name]" value="{{ $row['data']['name'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][brand]" value="{{ $row['data']['brand'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][description]" value="{{ $row['data']['description'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][category_id]" value="{{ $row['data']['category_id'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][category_name]" value="{{ $row['data']['category_name'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][supplier_id]" value="{{ $row['data']['supplier_id'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][supplier_sku]" value="{{ $row['data']['supplier_sku'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][product_family_id]" value="{{ $row['data']['product_family_id'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][family_name]" value="{{ $row['data']['family_name'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][variant_attributes]" value="{{ json_encode($row['data']['variant_attributes']) }}">
                                    <input type="hidden" name="rows[{{ $i }}][purchase_price]" value="{{ $row['data']['purchase_price'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][sale_price]" value="{{ $row['data']['sale_price'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][pro_price]" value="{{ $row['data']['pro_price'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][barcode]" value="{{ $row['data']['barcode'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][location]" value="{{ $row['data']['location'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][unit]" value="{{ $row['data']['unit'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][sale_unit]" value="{{ $row['data']['sale_unit'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][sale_unit_factor]" value="{{ $row['data']['sale_unit_factor'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][purchase_unit]" value="{{ $row['data']['purchase_unit'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][purchase_unit_factor]" value="{{ $row['data']['purchase_unit_factor'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][low_stock_threshold]" value="{{ $row['data']['low_stock_threshold'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][security_stock]" value="{{ $row['data']['security_stock'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][reorder_point]" value="{{ $row['data']['reorder_point'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][max_stock]" value="{{ $row['data']['max_stock'] }}">
                                    <input type="hidden" name="rows[{{ $i }}][tracks_lots]" value="{{ $row['data']['tracks_lots'] ? '1' : '0' }}">
                                    <input type="hidden" name="rows[{{ $i }}][active]" value="{{ $row['data']['active'] ? '1' : '0' }}">
                                    <input type="hidden" name="rows[{{ $i }}][stock_initial]" value="{{ $row['stock_initial'] }}">
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" @if($counts['new'] + $counts['update'] === 0) disabled @endif><i class="bi bi-check-lg"></i> Confirmer l'import</button>
            <a href="{{ route('products.import') }}" class="btn btn-ghost">Annuler, recommencer</a>
        </div>
    </form>
</x-layout>
