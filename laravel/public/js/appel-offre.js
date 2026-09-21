document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("appelForm");
    if (!form) return;

    const errorMessage = document.getElementById("errorMessage");
    const secteurSelect = document.getElementById("secteur");
    const categorySelect = document.getElementById("category");
    const servicesContainer = document.getElementById("servicesContainer");
    const submitBtn = form.querySelector('button[type="submit"]');

    const currentUser = window.AppUser || null;
    if (!currentUser || !currentUser.id) {
        const redirect = encodeURIComponent(window.location.pathname);
        window.location.href = `/login?redirect=${redirect}`;
        return;
    }

    const currentUserId = currentUser.id;

    async function fetchJsonOrFallback(url, fallback = []) {
        try {
            const res = await fetch(url, {
                credentials: "same-origin",
                headers: { Accept: "application/json" }
            });
            if (!res.ok) throw new Error(`Erreur API: ${url}`);
            const j = await res.json();
            return Array.isArray(j) ? j : (j.data || fallback);
        } catch (err) {
            console.warn("fetchJsonOrFallback failed:", err);
            return fallback;
        }
    }

    async function loadDynamicData() {
        const secteursFromApi = await fetchJsonOrFallback('/api/secteurs', []);
        const servicesFromApi = await fetchJsonOrFallback('/api/services', []);

        const secteurs = secteursFromApi.reduce((acc, s) => {
            const key = s.key || s.id || s.slug || s.name;
            const label = s.label || s.name || s.title || key;
            acc[key] = label;
            return acc;
        }, {});

        const services = servicesFromApi.map(s =>
            typeof s === 'string' ? s : (s.name || s.label || s.title)
        );

        renderSecteurs(secteurs);
        renderServices(services);
    }

    function renderSecteurs(secteursObj) {
        secteurSelect.innerHTML = '<option value="">Sélectionnez un secteur</option>';
        for (const [key, label] of Object.entries(secteursObj)) {
            const opt = document.createElement('option');
            opt.value = key;
            opt.textContent = label;
            secteurSelect.appendChild(opt);
        }
    }

    function renderServices(servicesArray) {
        servicesContainer.innerHTML = '';
        servicesArray.forEach(service => {
            const label = document.createElement('label');
            label.className = 'checkbox-item';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'services';
            input.value = service;

            const span = document.createElement('span');
            span.textContent = service;

            label.appendChild(input);
            label.appendChild(span);
            servicesContainer.appendChild(label);
        });
    }

    function showError(msg) {
        if (errorMessage) errorMessage.textContent = msg;
    }

    function clearError() {
        if (errorMessage) errorMessage.textContent = '';
    }

    function validateForm(values) {
        if (!values.secteur) return "Veuillez sélectionner un secteur.";
        if (!values.category) return "Veuillez sélectionner une catégorie.";
        if (!values.services || values.services.length === 0) return "Sélectionnez au moins un service.";
        if (!values.description || values.description.length < 10) return "Description trop courte (min 10 caractères).";
        if (!values.budget || isNaN(values.budget) || values.budget <= 0) return "Budget invalide.";
        return null;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();

        const description = (document.getElementById('description')?.value || '').trim();
        const budget = Number(document.getElementById('budget')?.value || 0);
        const delai = (document.getElementById('delai')?.value || '').trim();
        const secteur = (secteurSelect.value || '').trim();
        const category = (categorySelect.value || '').trim();
        const ville = (document.getElementById('ville')?.value || '').trim();
        const services = [...form.querySelectorAll('input[name="services"]:checked')].map(i => i.value);
        const prenom = (document.getElementById('prenom')?.value || '').trim();
        const nom = (document.getElementById('nom')?.value || '').trim();
        const email = (document.getElementById('email')?.value || '').trim();
        const telephone = (document.getElementById('telephone')?.value || '').trim();
        const imageFile = document.getElementById('image')?.files?.[0] || null;

        const err = validateForm({ secteur, category, services, description, budget });
        if (err) {
            showError(err);
            return;
        }

        const fd = new FormData();
        fd.append('description', description);
        fd.append('secteur', secteur);
        fd.append('category', category);
        services.forEach(s => fd.append('services[]', s));
        fd.append('budget', budget);

        if (delai) fd.append('delai', delai);
        if (ville) fd.append('ville', ville);

        fd.append('pays', "Côte d'Ivoire");
        fd.append('user_id', currentUserId);

        if (prenom) fd.append('prenom', prenom);
        if (nom) fd.append('nom', nom);
        if (email) fd.append('email', email);
        if (telephone) fd.append('telephone', telephone);
        if (imageFile) fd.append('image', imageFile);

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.orig = submitBtn.textContent;
            submitBtn.textContent = 'Envoi...';
        }

        try {
            const token = document.querySelector('meta[name="csrf-token"]').content;

            const res = await fetch('/api/appels', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': token },
                body: fd
            });

            if (!res.ok) {
                const json = await res.json().catch(() => null);
                throw new Error(
                    json?.message ||
                    json?.error ||
                    (json?.errors ? Object.values(json.errors)[0][0] : null) ||
                    "Erreur serveur lors de l'envoi."
                );
            }

            alert("Votre appel d'offre a été publié avec succès.");
            form.reset();
        } catch (err) {
            console.error(err);
            showError(err.message || "Erreur lors de l'envoi");
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.orig || 'Envoyer';
            }
        }
    });

    loadDynamicData();
});