// Génération de description produit via IA — dégrade silencieusement si les éléments sont absents.
(function () {
    const trigger = document.getElementById('generate-description-btn');

    if (!trigger) {
        return;
    }

    trigger.addEventListener('click', async () => {
        const name = document.getElementById('name')?.value ?? '';
        if (name.trim() === '') {
            alert('Renseigne au moins le nom du produit avant de générer une description.');
            return;
        }

        const brand = document.getElementById('brand')?.value ?? '';
        const categoryId = document.getElementById('category_id')?.value ?? '';
        const descriptionField = document.getElementById('description');
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        trigger.disabled = true;
        trigger.textContent = 'Génération en cours…';

        try {
            const response = await fetch('/products/generate-description', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ name, brand, category_id: categoryId || null }),
            });

            const data = await response.json();

            if (!response.ok) {
                alert(data.error || "Impossible de générer une description pour le moment.");
            } else if (descriptionField && data.description) {
                descriptionField.value = data.description;
            }
        } catch (e) {
            alert("Impossible de générer une description pour le moment.");
        }

        trigger.disabled = false;
        trigger.textContent = "✨ Générer une description avec l'IA";
    });
})();
