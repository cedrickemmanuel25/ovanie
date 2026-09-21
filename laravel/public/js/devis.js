document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("devisForm");
    const errorMessage = document.getElementById("errorMessage");
    const secteurSelect = document.getElementById("secteur");
    const categorySelect = document.getElementById("category");
    const activitesContainer = document.getElementById("activitesContainer");
    const imageInput = document.getElementById("image");
    const submitBtn = form?.querySelector("button[type='submit']");

    const API_BASE = `${window.location.origin}/api`;

    if (imageInput) {
        imageInput.addEventListener("change", () => {
            const file = imageInput.files[0];
            if (!file) return;

            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                errorMessage.textContent =
                    `Image trop lourde (${(file.size / 1024 / 1024).toFixed(2)} MB). Maximum 10MB.`;
                imageInput.value = "";
            }
        });
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    async function fetchJSON(url) {
        const res = await fetch(url);

        if (!res.ok) {
            const text = await res.text();
            console.error("Erreur API :", text);
            throw new Error("Erreur serveur.");
        }

        return res.json();
    }

    async function sendToServer(data) {
        const formData = new FormData();

        for (const key in data) {
            const value = data[key];

            if (Array.isArray(value)) {
                value.forEach(v => formData.append(`${key}[]`, v));
            } else if (value instanceof File) {
                formData.append(key, value);
            } else if (value !== null && value !== undefined) {
                formData.append(key, value);
            }
        }

        const csrfToken = document.querySelector('input[name="_token"]').value;
        formData.append("_token", csrfToken);

        const res = await fetch(`${API_BASE}/devis`, {
            method: "POST",
            body: formData,
            credentials: "include",
            headers: { Accept: "application/json" }
        });

        if (!res.ok) {
            const err = await res.json().catch(() => null);

            if (err?.errors) {
                const firstError = Object.values(err.errors)[0][0];
                throw new Error(firstError);
            }

            throw new Error("Erreur lors de l'envoi du devis.");
        }

        return res.json();
    }

    async function loadSecteurs() {
        secteurSelect.innerHTML = `<option>Chargement...</option>`;

        try {
            const secteurs = await fetchJSON(`${API_BASE}/secteurs`);

            secteurSelect.innerHTML = `<option value="">Sélectionnez un secteur</option>`;

            secteurs.forEach(secteur => {
                const option = document.createElement("option");
                option.value = secteur.slug;
                option.textContent = secteur.name;
                secteurSelect.appendChild(option);
            });
        } catch (err) {
            console.warn("Impossible de charger les secteurs :", err);

            secteurSelect.innerHTML = `
                <option value="">Sélectionnez un secteur</option>
                <option value="btp">BTP</option>
                <option value="habitat">Habitat</option>
                <option value="energie">Énergie</option>
            `;
        }
    }

    async function loadActivites(secteurSlug) {
        activitesContainer.innerHTML = "";
        if (!secteurSlug) return;

        try {
            const activites = await fetchJSON(`${API_BASE}/secteurs/${secteurSlug}/activites`);

            if (!activites.length) {
                activitesContainer.innerHTML = "<p>Aucune activité disponible.</p>";
                return;
            }

            activites.forEach(act => {
                const label = document.createElement("label");
                label.className = "checkbox-item";

                const input = document.createElement("input");
                input.type = "checkbox";
                input.name = "activites[]";
                input.value = act.slug;

                const span = document.createElement("span");
                span.textContent = act.name;

                label.appendChild(input);
                label.appendChild(span);
                activitesContainer.appendChild(label);
            });
        } catch (err) {
            console.warn("Impossible de charger les activités :", err);
            activitesContainer.innerHTML = "<p>Impossible de charger les activités.</p>";
        }
    }

    form?.addEventListener("submit", async (e) => {
        e.preventDefault();
        errorMessage.textContent = "";

        const secteur = secteurSelect.value;
        const category = categorySelect.value;

        const activites = [
            ...form.querySelectorAll('input[name="activites[]"]:checked')
        ].map(i => i.value);

        const prenom = document.getElementById("prenom").value.trim();
        const nom = document.getElementById("nom").value.trim();
        const email = document.getElementById("email").value.trim();
        const telephone = document.getElementById("telephone").value.trim();
        const ville = document.getElementById("ville").value.trim();
        const pays = document.getElementById("pays").value.trim();
        const message = document.getElementById("message").value.trim();
        const budget = parseFloat(document.getElementById("budget").value);
        const projet = document.getElementById("projet")?.value.trim() || "Demande de devis";
        const imageFile = imageInput?.files?.[0] || null;

        if (!secteur) {
            errorMessage.textContent = "Veuillez sélectionner un secteur.";
            return;
        }

        if (!category) {
            errorMessage.textContent = "Veuillez sélectionner une catégorie.";
            return;
        }

        if (!activites.length) {
            errorMessage.textContent = "Sélectionnez au moins une activité.";
            return;
        }

        if (message.length < 10) {
            errorMessage.textContent = "Veuillez décrire votre demande (min. 10 caractères).";
            return;
        }

        if (!budget || isNaN(budget) || budget <= 0) {
            errorMessage.textContent = "Budget invalide.";
            return;
        }

        if (!prenom || !nom || !validateEmail(email)) {
            errorMessage.textContent = "Veuillez remplir correctement vos informations.";
            return;
        }

        const devisData = {
            secteur,
            category,
            activites,
            message,
            budget,
            projet,
            prenom,
            nom,
            email,
            telephone,
            ville,
            pays,
            image: imageFile
        };

        submitBtn.disabled = true;
        submitBtn.textContent = "Envoi en cours...";

        try {
            const result = await sendToServer(devisData);
            console.log("Réponse serveur :", result);

            alert("Votre demande de devis a été envoyée avec succès.");
            form.reset();
            activitesContainer.innerHTML = "";
        } catch (err) {
            console.error("Erreur envoi :", err);
            errorMessage.textContent = err.message;
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = "Envoyer la demande";
        }
    });

    loadSecteurs();

    secteurSelect.addEventListener("change", (e) => {
        loadActivites(e.target.value);
    });
});