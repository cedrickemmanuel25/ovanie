document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector(".contact-form");

    if (!form) return;

    const submitBtn = form.querySelector(".submit-btn");

    form.addEventListener("submit", async (e) => {
        e.preventDefault(); // Empêche le rechargement de la page

        // Récupérer les valeurs du formulaire
        const formData = new FormData(form);

        // Désactiver le bouton pour éviter les doubles clics
        submitBtn.disabled = true;
        submitBtn.innerHTML = "<span>Envoi en cours…</span>";

        try {
            const res = await fetch(form.action, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                    "Accept": "application/json"
                },
                body: formData
            });

            const data = await res.json().catch(() => null);

            if (!res.ok) {
                throw new Error(data?.message || "Erreur lors de l'envoi");
            }

            // Afficher le message de succès
            showAlert("Merci pour votre message, nous vous répondrons bientôt.", "success");

            // Réinitialiser le formulaire
            form.reset();

        } catch (err) {
            console.error(err);
            showAlert(err.message || "Erreur serveur", "error");
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Envoyer la demande</span>';
        }
    });

    // Fonction pour afficher un message
    function showAlert(message, type = "success") {
        let alertDiv = document.createElement("div");
        alertDiv.className = `alert ${type}`;
        alertDiv.textContent = message;

        // Insérer avant le formulaire
        form.parentNode.insertBefore(alertDiv, form);

        // Supprimer automatiquement après 5 secondes
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});
