// include-back-home.js
/* ===========================================
   Bouton Retour à l’accueil – Script léger
   =========================================== */

document.addEventListener('DOMContentLoaded', async () => {
  // 🔹 Initialisation globale
  window.currentUser = window.currentUser || null;

  // 🔹 Essayer de récupérer l'utilisateur courant côté serveur (facultatif)
  try {
    const res = await fetch("/api/auth/me", { credentials: "include" });
    if (res.ok) {
      const data = await res.json();
      window.currentUser = data.user || null;
    }
  } catch (err) {
    console.warn("Impossible de récupérer l'utilisateur courant :", err);
  }

  // 🔹 Ajouter le bouton "Retour à l’accueil"
  const main = document.querySelector('main');
  if (main && window.homeUrl) {
    const link = document.createElement('a');
    link.href = window.homeUrl;
    link.className = 'btn-back-home';
    link.textContent = '← Retour à l’accueil';
    main.prepend(link);
  }
});
