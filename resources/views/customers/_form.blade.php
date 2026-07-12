<div class="field-row">
    <div class="field">
        <label for="name">Nom</label>
        <input type="text" id="name" name="name" value="{{ old('name', $customer->name ?? '') }}" required autofocus>
        @error('name') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="type">Type</label>
        <select id="type" name="type">
            <option value="particulier" @selected(old('type', $customer->type ?? 'particulier') === 'particulier')>Particulier</option>
            <option value="professionnel" @selected(old('type', $customer->type ?? '') === 'professionnel')>Professionnel</option>
        </select>
    </div>
</div>
<div class="field-row">
    <div class="field">
        <label for="phone">Téléphone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $customer->phone ?? '') }}">
    </div>
    <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="{{ old('email', $customer->email ?? '') }}">
        @error('email') <div class="error">{{ $message }}</div> @enderror
    </div>
</div>
<div class="field">
    <label for="address">Adresse</label>
    <textarea id="address" name="address" rows="2">{{ old('address', $customer->address ?? '') }}</textarea>
</div>
<h3>Compte professionnel &amp; crédit</h3>
<div class="field-row">
    <div class="field">
        <label for="credit_limit">Plafond de crédit (FCFA)</label>
        <input type="number" step="1" min="0" id="credit_limit" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}" required>
        <div class="hint">0 = pas de vente à crédit autorisée pour ce client.</div>
    </div>
    <div class="field">
        <label for="payment_terms_days">Délai de paiement (jours)</label>
        <input type="number" step="1" min="0" max="365" id="payment_terms_days" name="payment_terms_days" value="{{ old('payment_terms_days', $customer->payment_terms_days ?? 30) }}" required>
    </div>
</div>
