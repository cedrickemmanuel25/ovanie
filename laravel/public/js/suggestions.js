document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('publicForm');
    const formMessage = document.getElementById('formMessage');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);

        try {
            const res = await fetch(form.action || '/suggestion', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    ...(csrfMeta ? { 'X-CSRF-TOKEN': csrfMeta.getAttribute('content') } : {})
                },
                body: formData
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data?.message || 'Erreur serveur');
            }

            if (formMessage) {
                formMessage.textContent = data.message || 'Suggestion envoyée avec succès.';
                formMessage.style.color = 'green';
            }

            form.reset();

        } catch (err) {
            if (formMessage) {
                formMessage.textContent = err.message || 'Une erreur est survenue';
                formMessage.style.color = 'red';
            }

            console.error(err);
        }
    });
});