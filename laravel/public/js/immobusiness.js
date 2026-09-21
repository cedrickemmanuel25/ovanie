/* ============================================
   IMMO BUSINESS CAROUSEL – index.html
   SERVER-CENTRIC
============================================ */

if (window.IMOO.isAuthenticated) {
  console.log("Utilisateur connecté :", window.IMOO.user);
}

// ========================
// UTILITAIRES
// ========================
function escapeHtml(text) {
  return String(text || "").replace(/[&<>"']/g, c => ({
    '&':'&amp;',
    '<':'&lt;',
    '>':'&gt;',
    '"':'&quot;',
    "'":'&#39;'
  })[c]);
}

function formatPrice(v) {
  const n = Number(v);
  if (isNaN(n)) return v || "—";
  return n.toLocaleString("fr-FR") + " FCFA";
}

function createBusinessCard(item) {
  const div = document.createElement("div");
  div.className = "product-card";

  let badgeHTML = "";
  if(item.type === "devis") badgeHTML = `<div class="badge immo-business">DEVIS</div>`;
  else if(item.type === "appel") badgeHTML = `<div class="badge immo-business">APPEL D'OFFRE</div>`;

  div.innerHTML = `
    ${badgeHTML}
    <div class="product-info">
      <h4>${escapeHtml(item.title)}</h4>
      <p><strong>Secteur :</strong> ${escapeHtml(item.secteur)}</p>
      <p><strong>Budget :</strong> ${formatPrice(item.budget)}</p>
      <p><strong>Date :</strong> ${new Date(item.date || item.dateCreation).toLocaleDateString("fr-FR")}</p>
    </div>
  `;

  return div;
}

function injectItems(itemsArray, container) {
  if (!container) return;
  container.innerHTML = "";
  itemsArray.forEach(item => {
    const card = createBusinessCard(item);
    container.appendChild(card);
  });
}

function setupCarousel(containerId) {
  const container = document.querySelector(`#${containerId}`);
  if (!container) return;

  const track = container.querySelector(".carousel-track2");
  const btnLeft = container.querySelector(".btn-left");
  const btnRight = container.querySelector(".btn-right");
  if (!track || !btnLeft || !btnRight) return;

  let scrollAmount = 0;
  const itemWidth = track.firstElementChild?.offsetWidth || 300;

  btnLeft.addEventListener("click", () => {
    scrollAmount -= itemWidth;
    if(scrollAmount < 0) scrollAmount = 0;
    track.scrollTo({ left: scrollAmount, behavior: "smooth" });
  });

  btnRight.addEventListener("click", () => {
    scrollAmount += itemWidth;
    const maxScroll = track.scrollWidth - track.clientWidth;
    if(scrollAmount > maxScroll) scrollAmount = maxScroll;
    track.scrollTo({ left: scrollAmount, behavior: "smooth" });
  });
}

// ========================
// INIT
// ========================
document.addEventListener("DOMContentLoaded", async () => {

  // 🔹 Récupérer le catalogue depuis le serveur
  let catalog = [];
  try {
    const res = await fetch("/catalog", { credentials: "include" });
    if (res.ok) catalog = await res.json();
  } catch (err) {
    console.error("Impossible de charger le catalogue :", err);
  }

  // Filtrer pour immo-business
  const immoBusinessItems = catalog.filter(item => item.type === "devis" || item.type === "appel");

  // Container du carousel
  const immoBusinessContainer = document.querySelector("#immo-business-carousel .carousel-track2");

  // Injection des cartes
  if(immoBusinessContainer) injectItems(immoBusinessItems, immoBusinessContainer);

  // Initialiser les boutons du carousel
  setupCarousel("immo-business-carousel");
});
