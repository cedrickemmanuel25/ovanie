/* ===========================================
   GESTION DES LITIGES - VENDOR
   =========================================== */

if (window.IMOO.isAuthenticated) {
  console.log("Utilisateur connecté :", window.IMOO.user);
}

document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("disputesTableBody");
  const currentUser = window.currentUser;

  if (!currentUser || !tbody) return;

  /* ===============================
     ENVOYER RÉPONSE
  =============================== */
  async function sendResponse(disputeId, inputEl) {
    const message = inputEl.value.trim();
    if (!message) {
      alert("Veuillez saisir un message avant d'envoyer.");
      return;
    }

    try {
      const res = await fetch(`/api/disputes/${disputeId}/response`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ message })
      });

      if (!res.ok) throw new Error("Impossible d'envoyer la réponse");

      alert("Réponse envoyée au client ✔");
      inputEl.value = "";
      await loadDisputes(); // rafraîchir la liste
    } catch (err) {
      console.error(err);
      alert("Erreur lors de l'envoi de la réponse");
    }
  }

  /* ===============================
     CHARGER LES LITIGES
  =============================== */
  async function loadDisputes() {
    try {
      const res = await fetch(`/api/vendors/${currentUser.id}/disputes`, {
        credentials: "include"
      });
      if (!res.ok) throw new Error("Impossible de charger les litiges");

      const disputes = await res.json();
      renderDisputes(disputes);
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Impossible de charger les litiges.</td></tr>`;
    }
  }

  /* ===============================
     RENDU DES LITIGES
  =============================== */
  function renderDisputes(disputes) {
    tbody.innerHTML = "";
    if (!disputes || disputes.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Aucun litige disponible.</td></tr>`;
      return;
    }

    disputes.forEach(d => {
      const tr = document.createElement("tr");

      // Création dynamique de l'input et bouton
      const input = document.createElement("input");
      input.type = "text";
      input.placeholder = "Votre réponse";
      input.className = "dispute-message-input";

      const btn = document.createElement("button");
      btn.textContent = "Envoyer";
      btn.type = "button";
      btn.addEventListener("click", () => sendResponse(d.id, input));

      tr.innerHTML = `
        <td>${d.id}</td>
        <td>${d.orderId}</td>
        <td>${d.client}</td>
        <td>${d.issue}</td>
        <td>${d.status}</td>
        <td></td>
      `;
      tr.children[5].appendChild(input);
      tr.children[5].appendChild(btn);

      tbody.appendChild(tr);
    });
  }

  // Initialisation
  loadDisputes();
});
