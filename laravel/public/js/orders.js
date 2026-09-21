// =======================================
// ADMIN – ORDERS (100% SERVEUR)
// =======================================

document.addEventListener('DOMContentLoaded', async () => {
  const tableBody = document.getElementById('ordersTableBody');
  const statusFilter = document.getElementById('statusFilter');
  const dateFilter = document.getElementById('dateFilter');
  const filterBtn = document.getElementById('filterBtn');

  let ordersData = [];

  /* =========================
     FETCH API CENTRALISÉ
  ========================= */
  async function apiFetch(url, options = {}) {
    try {
      const res = await fetch(url, {
        credentials: "include",
        headers: { "Content-Type": "application/json", ...(options.headers || {}) },
        ...options
      });

      if (res.status === 401) {
        alert("Veuillez vous reconnecter");
        return window.location.href = "/login.html";
      }

      if (res.status === 403) {
        throw new Error("Accès interdit : droits administrateur requis");
      }

      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
      return data;

    } catch (err) {
      console.error("Erreur API:", err.message);
      alert(err.message);
      return null;
    }
  }

  /* =========================
     Vérifier admin
  ========================= */
  const currentUser = await apiFetch("/api/auth/me");
  if (!currentUser || currentUser.role !== "admin") {
    alert("Accès réservé à l’administrateur");
    return window.location.href = "/login.html";
  }

  /* =========================
     LOAD ORDERS
  ========================= */
  async function loadOrders() {
    try {
      ordersData = await apiFetch("/api/orders");
      if (!ordersData) ordersData = [];
      renderOrders(ordersData);
    } catch (err) {
      console.error("Erreur chargement commandes:", err.message);
      tableBody.innerHTML = `<tr><td colspan="9">Erreur lors du chargement des commandes</td></tr>`;
    }
  }

  /* =========================
     RENDER TABLE
  ========================= */
  function renderOrders(data = []) {
    tableBody.innerHTML = '';

    if (!data.length) {
      tableBody.innerHTML = `<tr><td colspan="9">Aucune commande trouvée</td></tr>`;
      return;
    }

    data.forEach(order => {
      const tr = document.createElement('tr');
      tr.dataset.id = order.id;

      tr.innerHTML = `
        <td>#${order.id}</td>
        <td>${order.client?.prenom || order.client?.userId || '—'}</td>
        <td>${formatDate(order.createdAt)}</td>
        <td>${formatFCFA(order.total)}</td>
        <td>${formatFCFA(order.commission || 0)}</td>
        <td><span class="status ${order.status}">${capitalize(order.status)}</span></td>
        <td><span class="status ${order.payment_status}">${capitalize(order.payment_status)}</span></td>
        <td>
          <button class="btn-update-status">Mettre à jour</button>
          <a href="order_detail.html?id=${order.id}" class="btn-view">Voir</a>
        </td>
      `;

      // Action rapide pour mise à jour
      tr.querySelector('.btn-update-status').onclick = () => updateOrderStatus(order.id);

      tableBody.appendChild(tr);
    });
  }

  /* =========================
     UPDATE STATUS & SMS
  ========================= */
  async function updateOrderStatus(orderId) {
    const newStatus = prompt('Nouveau statut (pending / validated / shipped / canceled) :');
    if (!newStatus) return;

    try {
      await apiFetch(`/api/orders/${orderId}/status`, {
        method: 'PUT',
        body: JSON.stringify({ status: newStatus })
      });

      // Optionnel : envoyer SMS au client
      try {
        await apiFetch(`/api/orders/${orderId}/notify`, { method: 'POST' });
      } catch (smsErr) {
        console.warn('Erreur envoi SMS :', smsErr.message);
      }

      // Recharger tableau
      await loadOrders();

    } catch (err) {
      alert('Erreur mise à jour : ' + err.message);
    }
  }

  /* =========================
     FILTERING
  ========================= */
  function applyFilters() {
    const status = statusFilter.value;
    const date = dateFilter.value;

    const filtered = ordersData.filter(order => {
      let valid = true;

      if (status && order.status !== status) valid = false;
      if (date && formatDate(order.createdAt) !== date) valid = false;

      return valid;
    });

    renderOrders(filtered);
  }

  /* =========================
     UTILITAIRES
  ========================= */
  function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
  }

  function formatFCFA(value = 0) {
    return Number(value).toLocaleString('fr-FR') + ' FCFA';
  }

  function capitalize(text = '') {
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  /* =========================
     EVENTS
  ========================= */
  filterBtn.addEventListener('click', applyFilters);
  statusFilter.addEventListener('change', applyFilters);
  dateFilter.addEventListener('change', applyFilters);

  /* =========================
     INIT
  ========================= */
  loadOrders();
});
