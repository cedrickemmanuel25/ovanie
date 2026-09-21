// =======================================
// ADMIN DASHBOARD – SERVER-FIRST
// =======================================

// ==========================
// UTILITAIRES
// ==========================
const formatFCFA = value => Number(value || 0).toLocaleString("fr-FR") + " FCFA";

async function apiFetch(url, options = {}) {
  const res = await fetch(url, {
    ...options,
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {})
    }
  });

  if (res.status === 401) {
    // non authentifié
    window.location.href = "/admin/admin_login.html";
    return;
  }

  if (res.status === 403) {
    throw new Error("Accès interdit : droits administrateur requis");
  }

  if (!res.ok) {
    const errData = await res.json().catch(() => ({}));
    throw new Error(errData.error || `Erreur API : ${url}`);
  }

  return res.json();
}

// ==========================
// DASHBOARD – CHARGEMENT
// ==========================
async function loadDashboard() {
  try {
    // Vérifier que l'admin est connecté
    const currentUser = await apiFetch("/api/auth/me");
    if (!currentUser || currentUser.role !== "admin") {
      alert("Accès réservé à l’administrateur");
      return window.location.href = "/admin/admin_login.html";
    }

    // Charger toutes les données
    const [products, orders, users] = await Promise.all([
      apiFetch("/api/products"),
      apiFetch("/api/orders"),
      apiFetch("/api/users")
    ]);

    // Mettre à jour les KPI
    updateKPIs({ products, orders, users });

    // Afficher derniers produits et commandes
    renderLatestOrders(orders);
    renderLatestProducts(products);

  } catch (err) {
    console.error(err.message);
    alert(err.message);
  }
}

// ==========================
// KPI ADMIN
// ==========================
function updateKPIs({ products = [], orders = [], users = [] }) {
  const now = new Date();
  const currentMonth = now.getMonth();
  const currentYear = now.getFullYear();

  const monthlySales = orders
    .filter(o => {
      const d = new Date(o.createdAt);
      return d.getMonth() === currentMonth && d.getFullYear() === currentYear;
    })
    .reduce((sum, o) => sum + Number(o.total || 0), 0);

  const inactiveUsers = users.filter(u => !u.lastLogin).length;

  document.getElementById("kpi-sales").textContent       = formatFCFA(monthlySales);
  document.getElementById("kpi-orders").textContent      = orders.length;
  document.getElementById("kpi-products").textContent    = products.length;
  document.getElementById("kpi-clients").textContent     = users.length;
  document.getElementById("kpi-inactive").textContent    = inactiveUsers;
}

// ==========================
// TABLE DERNIERES COMMANDES
// ==========================
function renderLatestOrders(orders = []) {
  const tbody = document.getElementById("latestOrders");
  if (!tbody) return;

  tbody.innerHTML = "";

  const recent = [...orders]
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
    .slice(0, 5);

  if (!recent.length) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center">Aucune commande</td></tr>`;
    return;
  }

  recent.forEach(o => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>#${o.id}</td>
      <td>${o.clientId || "—"}</td>
      <td>${o.vendorId || "—"}</td>
      <td>${formatFCFA(o.total)}</td>
      <td class="status ${o.status || "pending"}">${o.status || "En attente"}</td>
    `;
    tbody.appendChild(tr);
  });
}

// ==========================
// TABLE DERNIERS PRODUITS
// ==========================
function renderLatestProducts(products = []) {
  const tbody = document.getElementById("latestProducts");
  if (!tbody) return;

  tbody.innerHTML = "";

  const recent = [...products].slice(-5).reverse();

  if (!recent.length) {
    tbody.innerHTML = `<tr><td colspan="3" style="text-align:center">Aucun produit</td></tr>`;
    return;
  }

  recent.forEach(p => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${p.name || "—"}</td>
      <td>${p.stock ?? 0}</td>
      <td>${formatFCFA(p.price)}</td>
    `;
    tbody.appendChild(tr);
  });
}
// ==========================
// KPI COMMISSIONS & SMS
// ==========================
function updateCommissionsAndSMS(orders = []) {
  const totalCommission = orders
    .filter(o => o.commissionPaid)
    .reduce((sum, o) => sum + Number(o.commissionAmount || 0), 0);

  const unpaidCommission = orders
    .filter(o => !o.commissionPaid)
    .reduce((sum, o) => sum + Number(o.commissionAmount || 0), 0);

  const smsPending = orders.filter(o => !o.smsSent).length;

  document.getElementById("kpi-total-commission").textContent  = formatFCFA(totalCommission);
  document.getElementById("kpi-unpaid-commission").textContent = formatFCFA(unpaidCommission);
  document.getElementById("kpi-sms-pending").textContent       = smsPending;
}

// ==========================
// LOAD DASHBOARD – version étendue
// ==========================
async function loadDashboard() {
  try {
    const currentUser = await apiFetch("/api/auth/me");
    if (!currentUser || currentUser.role !== "admin") {
      alert("Accès réservé à l’administrateur");
      return window.location.href = "/admin/admin_login.html";
    }

    const [products, orders, users] = await Promise.all([
      apiFetch("/api/products"),
      apiFetch("/api/orders"),
      apiFetch("/api/users")
    ]);

    updateKPIs({ products, orders, users });
    updateCommissionsAndSMS(orders);
    renderLatestOrders(orders);
    renderLatestProducts(products);

  } catch (err) {
    console.error(err.message);
    alert(err.message);
  }
}

// ==========================
// INIT
// ==========================
document.addEventListener("DOMContentLoaded", loadDashboard);
