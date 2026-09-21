// vendor_returns.js
/* ============================================
   GESTION DES RETOURS – VENDOR (SERVER-CENTRIC)
============================================ */

if (window.IMOO.isAuthenticated) {
  console.log("Utilisateur connecté :", window.IMOO.user);
}

/* ===================== TOAST ===================== */
function showToast(msg, type = "success") {
  const t = document.createElement("div");
  t.className = `toast ${type}`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

/* ===================== ACTIONS ===================== */
async function acceptReturn(returnId) {
  try {
    const res = await fetch(`/api/returns/${returnId}/accept`, {
      method: "PATCH",
      credentials: "include"
    });
    if (!res.ok) throw new Error("Impossible d'accepter le retour");
    showToast("Retour accepté ✔", "success");
    await loadReturns();
  } catch (err) {
    console.error(err);
    showToast("Erreur lors de l'acceptation du retour", "error");
  }
}

async function rejectReturn(returnId) {
  try {
    const res = await fetch(`/api/returns/${returnId}/reject`, {
      method: "PATCH",
      credentials: "include"
    });
    if (!res.ok) throw new Error("Impossible de rejeter le retour");
    showToast("Retour refusé ✖", "info");
    await loadReturns();
  } catch (err) {
    console.error(err);
    showToast("Erreur lors du rejet du retour", "error");
  }
}

/* ===================== RENDU ===================== */
function renderReturns(returns) {
  const tbody = document.getElementById("returnsTableBody");
  if (!tbody) return;
  tbody.innerHTML = "";

  if (!returns || returns.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Aucun retour disponible</td></tr>`;
    return;
  }

  returns.forEach(r => {
    const tr = document.createElement("tr");

    tr.innerHTML = `
      <td>${r.id}</td>
      <td>${r.orderId}</td>
      <td>${r.product}</td>
      <td>${r.quantity}</td>
      <td>${r.status}</td>
      <td>
        <button class="btn-accept">Accepter</button>
        <button class="btn-reject">Refuser</button>
      </td>
    `;

    // ⚡ Event listeners pour chaque bouton
    const btnAccept = tr.querySelector(".btn-accept");
    const btnReject = tr.querySelector(".btn-reject");

    btnAccept.addEventListener("click", () => acceptReturn(r.id));
    btnReject.addEventListener("click", () => rejectReturn(r.id));

    tbody.appendChild(tr);
  });
}

/* ===================== FETCH ===================== */
async function loadReturns() {
  try {
    if (!window.currentUser) throw new Error("Utilisateur non défini");

    const res = await fetch(`/api/vendors/${window.currentUser.id}/returns`, {
      credentials: "include"
    });
    if (!res.ok) throw new Error("Impossible de charger les retours");

    const returns = await res.json();
    renderReturns(returns);
  } catch (err) {
    console.error(err);
    showToast(err.message || "Erreur serveur", "error");
  }
}

/* ===================== INIT ===================== */
document.addEventListener("DOMContentLoaded", () => {
  if (!window.currentUser) return;
  loadReturns();
});
