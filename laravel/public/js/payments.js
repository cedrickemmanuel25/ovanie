/* ============================================
   VENDOR PAYMENTS – PAGE PROTÉGÉE
   ============================================ */

document.addEventListener("DOMContentLoaded", async () => {

  const tbody = document.getElementById("payments-tbody");
  const balanceAvailableEl = document.getElementById("balance-available");
  const balancePendingEl = document.getElementById("balance-pending");

  /* =========================
     UTILITAIRE FORMAT FCFA
  ========================= */
  const formatFCFA = amount =>
    Number(amount || 0).toLocaleString("fr-FR") + " FCFA";

  /* =========================
     FETCH PAIEMENTS SERVER
  ========================= */
  async function fetchPayments() {
    try {
      // 🔹 Vérifier utilisateur connecté côté serveur
      const resUser = await fetch("/api/auth/current", { credentials: "include" });
      if (!resUser.ok) throw new Error("Utilisateur non connecté");
      const currentUser = await resUser.json();

      // 🔹 Récupérer les paiements du vendeur
      const res = await fetch("/api/vendor-payments", { credentials: "include" });
      if (!res.ok) throw new Error("Erreur récupération paiements");

      const data = await res.json();
      return Array.isArray(data.payments) ? data.payments : [];

    } catch (err) {
      console.error("Erreur fetchPayments :", err);
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">Impossible de charger les paiements.</td></tr>`;
      }
      return [];
    }
  }

  /* =========================
     RENDU PAIEMENTS
  ========================= */
  function renderPayments(payments) {
    if (!tbody) return;
    tbody.innerHTML = "";

    if (payments.length === 0) {
      tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">Aucun paiement disponible.</td></tr>`;
      return;
    }

    payments.forEach(p => {
      const tr = document.createElement("tr");
      const date = new Date(p.date);
      const dateStr = date.toLocaleDateString("fr-FR");

      tr.innerHTML = `
        <td>${dateStr}</td>
        <td>${p.orderId || "—"}</td>
        <td>${formatFCFA(p.amount)}</td>
        <td class="${p.status || "pending"}">${p.status === "paid" ? "Payé" : "En attente"}</td>
      `;

      tbody.appendChild(tr);
    });
  }

  /* =========================
     CALCUL SOLDES
  ========================= */
  function updateBalances(payments) {
    if (!balanceAvailableEl || !balancePendingEl) return;

    let totalAvailable = 0;
    let totalPending = 0;

    payments.forEach(p => {
      if (p.status === "paid") totalAvailable += p.amount || 0;
      else totalPending += p.amount || 0;
    });

    balanceAvailableEl.textContent = formatFCFA(totalAvailable);
    balancePendingEl.textContent = formatFCFA(totalPending);
  }

  /* =========================
     INITIALISATION
  ========================= */
  async function init() {
    const payments = await fetchPayments();
    renderPayments(payments);
    updateBalances(payments);
  }

  init();

});
