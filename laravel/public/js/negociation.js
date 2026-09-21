/* ==================================================
   NÉGOCIATION – LOGIQUE CATALOGUE / PANIER SERVER
   ================================================== */

if (window.IMOO.isAuthenticated) {
  console.log("Utilisateur connecté :", window.IMOO.user);
}

/* =======================
   NÉGOCIATION PRIX
======================= */
async function negotiatePrice(offer, productId) {
  try {
    const res = await fetch(`/api/products/${productId}`);
    if (!res.ok) throw new Error("Produit introuvable");
    const product = await res.json();

    const { price_p1, price_p2, price_p3 } = product;

    // Acceptation immédiate
    if (offer >= price_p1) return { status: "accepted", finalPrice: offer, reason: "offer >= P1" };

    // Zone de contre-offre
    if (offer >= price_p2) return { status: "counter", counterPrice: price_p1, minAcceptable: price_p2 };

    // Offre minimale
    if (offer >= price_p3) return { status: "accepted", finalPrice: offer, reason: "offer >= P3" };

    // Offre trop basse
    return { status: "rejected", reason: "offer < P3" };

  } catch (err) {
    console.error(err);
    return { status: "error", reason: err.message };
  }
}

/* =======================
   SOUMISSION UTILISATEUR
======================= */
async function onUserSubmitOffer(offer, productId) {
  const result = await negotiatePrice(offer, productId);

  switch (result.status) {
    case "accepted":
      showSuccess(`Prix accepté : ${result.finalPrice.toLocaleString("fr-FR")} FCFA`);
      await addToCartServer(productId, result.finalPrice, 1);
      break;

    case "counter":
      showMessage(`Proposition refusée. Prix conseillé : ${result.counterPrice.toLocaleString("fr-FR")} FCFA`);
      break;

    case "rejected":
      showError("Offre trop basse. Prix non négociable.");
      break;

    case "error":
      showError("Erreur serveur : " + result.reason);
      break;
  }
}

/* =======================
   PANIER SERVER
======================= */
async function addToCartServer(productId, price, qty = 1) {
  try {
    const res = await fetch("/api/cart", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ productId, price, quantity: qty })
    });
    if (!res.ok) throw new Error("Erreur ajout panier");
    await updateCartCountServer();
  } catch (err) {
    console.error(err);
    showError("Impossible d'ajouter au panier !");
  }
}

/* =======================
   COMPTEUR PANIER
======================= */
async function updateCartCountServer() {
  try {
    const res = await fetch("/api/cart/count", { credentials: "include" });
    if (!res.ok) return;
    const data = await res.json();
    const countEl = document.querySelector(".cart-count");
    if (countEl) countEl.textContent = `(${data.count})`;
  } catch (err) {
    console.error(err);
  }
}

/* =======================
   TOASTS / FEEDBACK
======================= */
function showSuccess(msg) { showToast(msg, "success"); }
function showMessage(msg) { showToast(msg, "info"); }
function showError(msg) { showToast(msg, "error"); }

function showToast(msg, type = "info") {
  const t = document.createElement("div");
  t.className = `toast ${type}`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

/* =======================
   BOUTON ENVOI OFFRE
======================= */
const btn = document.getElementById("btn-send-offer");
if (btn) {
  btn.addEventListener("click", () => {
    const input = document.getElementById("offer-input");
    if (!input) return showError("Champ offre introuvable");

    const offer = Number(input.value);
    const productId = Number(input.dataset.productId);

    if (!offer || !productId) return showError("Valeur invalide");

    onUserSubmitOffer(offer, productId);
  });
}
