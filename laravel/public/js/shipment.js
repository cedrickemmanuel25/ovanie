/* ============================================
   SHIPMENT – SERVER-FIRST (SESSION)
   ============================================ */

document.addEventListener("DOMContentLoaded", async () => {
  const shipmentForm = document.getElementById("shipmentForm");
  if (!shipmentForm) return;

  // =========================
  // AUTH – UTILISATEUR
  // =========================
  let currentUser;
  try {
    const resUser = await fetch("/api/auth/me", { credentials: "include" });
    if (!resUser.ok) throw new Error("Non connecté");
    currentUser = await resUser.json();
    if (currentUser.role !== "vendor") throw new Error("Accès refusé");
  } catch (err) {
    console.error(err);
    window.location.href = "/login.html";
    return;
  }

  // =========================
  // LISTE COMMANDES DU VENDEUR
  // =========================
  const ordersTable = document.getElementById("ordersTable");
  async function loadOrders() {
    try {
      const res = await fetch("/api/vendor/orders", { credentials: "include" });
      if (!res.ok) throw new Error("Impossible de récupérer les commandes");
      const orders = await res.json();

      if (!orders.length) {
        ordersTable.innerHTML = `<tr><td colspan="7" style="text-align:center">Aucune commande</td></tr>`;
        return;
      }

      ordersTable.innerHTML = "";
      for (const o of orders) {
        const tr = document.createElement("tr");
        tr.dataset.orderId = o.id;

        // Affichage du statut selon verrouillage et commission
        let statusText = o.status;
        if (o.status === "verrouillée") {
          statusText += " (Commission non payée)";
        }

        tr.innerHTML = `
          <td>${o.id}</td>
          <td>${o.productName}</td>
          <td>${o.quantity}</td>
          <td>${o.amount.toLocaleString("fr-FR")} FCFA</td>
          <td>${statusText}</td>
          <td>${new Date(o.createdAt).toLocaleDateString("fr-FR")}</td>
          <td>
            ${o.status === "verrouillée" ? `<button class="btn pay-commission">Payer commission</button>` : ""}
            <button class="btn ship-order">Expédier</button>
          </td>
        `;

        ordersTable.appendChild(tr);

        // 🔹 Bouton payer commission
        const payBtn = tr.querySelector(".pay-commission");
        if (payBtn) {
          payBtn.addEventListener("click", async () => {
            try {
              const res = await fetch(`/api/orders/${o.id}/pay-commission`, {
                method: "POST",
                credentials: "include"
              });
              if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.error || "Erreur paiement commission");
              }
              showToast("Commission payée, contact client déverrouillé !");
              tr.querySelector("td:nth-child(5)").textContent = "livre";
              payBtn.remove();
            } catch (err) {
              console.error(err);
              showToast(err.message || "Erreur lors du paiement de la commission", "error");
            }
          });
        }

        // 🔹 Bouton expédition
        tr.querySelector(".ship-order").addEventListener("click", () => {
          shipmentForm["order-id"].value = o.id;
          shipmentForm.scrollIntoView({ behavior: "smooth" });
        });
      }

    } catch (err) {
      console.error(err);
      ordersTable.innerHTML = `<tr><td colspan="7" style="text-align:center">Erreur lors du chargement des commandes</td></tr>`;
    }
  }

  await loadOrders();

  // =========================
  // SOUMISSION FORMULAIRE EXPÉDITION
  // =========================
  shipmentForm.addEventListener("submit", async e => {
    e.preventDefault();

    const orderId = shipmentForm["order-id"].value.trim();
    const carrier = shipmentForm["carrier"].value.trim();
    const trackingNumber = shipmentForm["tracking-number"].value.trim();
    const shipmentDate = shipmentForm["shipment-date"].value;

    if (!orderId || !carrier || !trackingNumber || !shipmentDate) {
      return showToast("Veuillez remplir tous les champs.", "error");
    }

    const payload = { carrier, trackingNumber, shipmentDate };

    try {
      const res = await fetch(`/api/orders/${orderId}/shipment`, {
        method: "PUT",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.error || "Erreur serveur");
      }

      showToast("Expédition enregistrée avec succès.");
      shipmentForm.reset();
      await loadOrders(); // refresh table

    } catch (err) {
      console.error(err);
      showToast(err.message, "error");
    }
  });
});

/* =========================
   IMPRESSION
========================= */
function printSlip() {
  window.print();
}

/* =========================
   TOAST UI
========================= */
function showToast(msg, type = "success") {
  const t = document.createElement("div");
  t.className = `toast ${type}`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 2500);
}
