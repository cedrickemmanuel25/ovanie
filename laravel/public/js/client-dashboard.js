document.addEventListener("DOMContentLoaded", async () => {
  // -------------------------------
  // Vérifier si l'utilisateur est connecté
  // -------------------------------
  let user;
  try {
    const res = await fetch("/api/auth/me");
    if (!res.ok) throw new Error("Non connecté");
    user = await res.json();
  } catch {
    window.location.href = "/login.html";
    return;
  }

  // -------------------------------
  // Affichage infos utilisateur
  // -------------------------------
  document.getElementById("user-name").textContent = `Nom: ${user.firstName} ${user.lastName}`;
  document.getElementById("user-email").textContent = `Email: ${user.email}`;
  document.getElementById("user-phone").textContent = `Téléphone: ${user.phone}`;

  // -------------------------------
  // Déconnexion
  // -------------------------------
  document.getElementById("logoutBtn").addEventListener("click", async () => {
    await fetch("/api/auth/logout", { method: "POST" });
    window.location.href = "/login.html";
  });

  // -------------------------------
  // Charger commandes client
  // -------------------------------
  const ordersContainer = document.getElementById("orders-list");
  try {
    const res = await fetch("/api/orders");
    const orders = await res.json();
    const clientOrders = orders.filter(o => o.clientId === user.id);

    if (!clientOrders.length) {
      ordersContainer.textContent = "Aucune commande pour le moment.";
    } else {
      ordersContainer.innerHTML = clientOrders.map(o => {
        const amount = o.quantity * (o.productPrice || 0);
        return `
          <div class="order">
            <p>Commande: ${o.id}</p>
            <p>Produit: ${o.productName}</p>
            <p>Quantité: ${o.quantity}</p>
            <p>Montant: ${amount} FCFA</p>
            <p>Status: ${o.locked ? "Verrouillée" : "Livrée"}</p>
            ${o.locked ? `<button class="pay-wave-btn" data-order-id="${o.id}">Payer avec Wave</button>` : ""}
            <div class="qr-container" id="qr-${o.id}"></div>
          </div>
        `;
      }).join("");
    }
  } catch (err) {
    ordersContainer.textContent = "Impossible de charger les commandes.";
    console.error(err);
  }

  // -------------------------------
  // Charger panier
  // -------------------------------
  const cartContainer = document.getElementById("cart-list");
  try {
    const res = await fetch("/api/cart");
    const cartItems = await res.json();
    if (!cartItems.length) {
      cartContainer.textContent = "Votre panier est vide.";
    } else {
      cartContainer.innerHTML = cartItems.map(i => `
        <div class="cart-item">
          <p>${i.name} x${i.quantity} - ${i.price} FCFA</p>
        </div>
      `).join("");
    }
  } catch (err) {
    cartContainer.textContent = "Impossible de charger le panier.";
    console.error(err);
  }

  // -------------------------------
  // Gestion paiement Wave
  // -------------------------------
  document.body.addEventListener("click", async e => {
    if (e.target.classList.contains("pay-wave-btn")) {
      const orderId = e.target.dataset.orderId;
      const qrDiv = document.getElementById(`qr-${orderId}`);

      try {
        const res = await fetch(`/api/orders/${orderId}/wave-qr`, { method: "POST" });
        const data = await res.json();

        if (res.ok) {
          qrDiv.innerHTML = `<img src="${data.qrCodeUrl}" alt="QR Wave" class="wave-qr">`;
        } else {
          alert(data.error || "Erreur lors de la génération du QR Wave");
        }
      } catch (err) {
        alert("Erreur lors de la génération du QR Wave");
        console.error(err);
      }
    }
  });
});
