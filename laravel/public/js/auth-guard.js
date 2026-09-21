// js/auth-guard.js - Vérification connexion utilisateur via session Laravel
document.addEventListener("DOMContentLoaded", async () => {
  try {
    // Appel API pour vérifier si l'utilisateur est connecté
    const res = await fetch("/api/auth/me", {
      credentials: "include", // inclut les cookies de session Laravel
      headers: {
        "Accept": "application/json"
      }
    });

    // Si non ok, on considère que l'utilisateur n'est pas connecté
    if (!res.ok) throw new Error("Non connecté");

    // Récupération des infos utilisateur
    const user = await res.json();

    // Stockage global pour usage dans d'autres scripts
    window.IMOO = window.IMOO || {};
    window.IMOO.user = user;
    window.IMOO.isAuthenticated = true;

  } catch (err) {
    console.warn("Utilisateur non connecté ou erreur API:", err.message);

    // Redirection vers /login en ajoutant la page actuelle comme paramètre redirect
    const currentPage = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `/login?redirect=${currentPage}`;
  }
});
