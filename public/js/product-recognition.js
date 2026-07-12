// Reconnaissance produit par photo — dégrade silencieusement si les éléments ne sont pas présents sur la page.
(function () {
    const trigger = document.getElementById('recognize-photo-btn');
    const input = document.getElementById('recognize-photo-input');
    const results = document.getElementById('recognize-results');

    if (!trigger || !input || !results) {
        return;
    }

    trigger.addEventListener('click', () => input.click());

    input.addEventListener('change', async () => {
        const file = input.files[0];
        if (!file) {
            return;
        }

        results.style.display = 'block';
        results.innerHTML = '<p class="muted">Analyse de la photo en cours…</p>';

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const formData = new FormData();
        formData.append('photo', file);

        try {
            const response = await fetch('/products/recognize-photo', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: formData,
            });

            if (!response.ok) {
                throw new Error('request failed');
            }

            const data = await response.json();
            renderResults(data);
        } catch (e) {
            results.innerHTML = '<p class="muted">Impossible d\'analyser la photo pour le moment.</p>';
        }

        input.value = '';
    });

    function renderResults(data) {
        let html = '<h3 class="mt-0">Résultat de la reconnaissance</h3>';
        html += `<p>${escapeHtml(data.object_type || 'Objet non identifié')}${data.probable_use ? ' — ' + escapeHtml(data.probable_use) : ''}</p>`;

        if (data.matches && data.matches.length > 0) {
            html += '<p class="muted">Correspondances probables dans le catalogue :</p><ul>';
            data.matches.forEach((m) => {
                html += `<li><a href="${m.url}">${escapeHtml(m.name)}</a> <span class="muted mono">(${escapeHtml(m.reference)})</span></li>`;
            });
            html += '</ul>';
        } else {
            const params = new URLSearchParams({
                name: data.object_type || '',
                description: [data.probable_material, data.probable_use].filter(Boolean).join(' — '),
            });
            html += `<p class="muted">Aucune correspondance trouvée dans le catalogue.</p>`;
            html += `<a class="btn btn-sm btn-primary" href="/products/create?${params.toString()}">Créer un nouveau produit avec ces informations</a>`;
        }

        results.innerHTML = html;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }
})();
