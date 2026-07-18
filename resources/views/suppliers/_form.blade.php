<div class="field-row">
    <div class="field">
        <label for="name">Nom</label>
        <input type="text" id="name" name="name" value="{{ old('name', $supplier->name ?? '') }}" required autofocus>
        @error('name') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="contact_name">Personne de contact</label>
        <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name', $supplier->contact_name ?? '') }}">
    </div>
</div>
<div class="field-row">
    <div class="field">
        <label for="phone">Téléphone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone ?? '') }}">
    </div>
    <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="{{ old('email', $supplier->email ?? '') }}">
        @error('email') <div class="error">{{ $message }}</div> @enderror
    </div>
</div>
<div class="field">
    <label for="address">Adresse</label>
    <textarea id="address" name="address" rows="2">{{ old('address', $supplier->address ?? '') }}</textarea>
</div>
<div class="field">
    <label for="lead_time_days">Délai de livraison moyen (jours)</label>
    <input type="number" id="lead_time_days" name="lead_time_days" min="0" max="365" value="{{ old('lead_time_days', $supplier->lead_time_days ?? 7) }}" required>
    @error('lead_time_days') <div class="error">{{ $message }}</div> @enderror
</div>
<div class="field-row">
    <div class="field">
        <label for="payment_terms">Conditions de paiement (notes)</label>
        <textarea id="payment_terms" name="payment_terms" rows="2" placeholder="Ex : 30 jours net, 50% à la commande…">{{ old('payment_terms', $supplier->payment_terms ?? '') }}</textarea>
    </div>
    <div class="field">
        <label for="payment_terms_days">Délai de paiement réel (jours)</label>
        <input type="number" id="payment_terms_days" name="payment_terms_days" min="0" max="365" value="{{ old('payment_terms_days', $supplier->payment_terms_days ?? '') }}" placeholder="Ex : 30">
        <div class="hint">Utilisé pour la prévision de trésorerie — laisser vide si inconnu (une hypothèse par défaut de 30 jours sera utilisée en dernier recours).</div>
        @error('payment_terms_days') <div class="error">{{ $message }}</div> @enderror
    </div>
</div>
