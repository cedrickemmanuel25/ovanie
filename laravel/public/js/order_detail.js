// =======================================
// ADMIN – DÉTAIL COMMANDE (100% SERVEUR)
// =======================================

document.addEventListener('DOMContentLoaded', async () => {

  const els = {
    title: document.getElementById('order-title'),

    clientName: document.getElementById('client-name'),
    clientEmail: document.getElementById('client-email'),
    clientPhone: document.getElementById('client-phone'),

    shipAddress: document.getElementById('shipping-address'),
    shipCity: document.getElementById('shipping-city'),
    shipPostal: document.getElementById('shipping-postal'),

    productsBody: document.getElementById('order-products-body'),

    subtotal: document.getElementById('subtotal'),
    shippingFee: document.getElementById('shipping-fee'),
    total: document.getElementById('order-total'),
    commission: document.getElementById('commission'),

    statusSelect: document.getElementById('status-select'),
    saveBtn: document.getElementById('saveStatusBtn')
  };

  /* ===============================
     UTILITAIRES
  ================================ */
  const formatFCFA = v => `${Number(v || 0).toLocaleString('fr-FR')} FCFA`;
  const getOrderId = () => new URLSearchParams(window.location.search).get('id');

  const capitalize = txt => txt ? txt.charAt(0).toUpperCase() + txt.slice(1) : '—';

  /* ===============================
     FETCH API CENTRALISÉ
  ================================ */
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

  /* ===============================
     Vérification admin
  ================================ */
  const currentUser = await apiFetch("/api/auth/me");
  if (!currentUser || currentUser.role !== "admin") {
    alert("Accès réservé à l’administrateur");
    return window.location.href = "/login.html";
  }

  /* ===============================
     Charger commande
  ================================ */
  async function loadOrder(orderId) {
    return await apiFetch(`/api/orders/${orderId}`);
  }

  /* ===============================
     Affichage commande
  ================================ */
  function renderOrder(order) {
    els.title.textContent = `Commande #${order.id}`;

    // Client
    els.clientName.textContent = order.client?.prenom || order.client?.userId || '—';
    els.clientEmail.textContent = order.client?.email || '—';
    els.clientPhone.textContent = order.client?.telephone || '—';

    // Livraison
    els.shipAddress.textContent = order.shippingAddress || '—';
    els.shipCity.textContent = order.shippingCity || '—';
    els.shipPostal.textContent = order.shippingPostal || '—';

    // Produits
    els.productsBody.innerHTML = '';
    let subtotal = 0;

    (order.items || []).forEach(item => {
      const total = (item.price || 0) * (item.quantity || 0);
      subtotal += total;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${item.name || '—'}</td>
        <td>${item.quantity || 0}</td>
        <td>${formatFCFA(item.price)}</td>
        <td>${formatFCFA(total)}</td>
      `;
      els.productsBody.appendChild(tr);
    });

    els.subtotal.textContent = formatFCFA(subtotal);
    els.shippingFee.textContent = formatFCFA(order.shippingFee || 0);
    els.total.textContent = formatFCFA(subtotal + (order.shippingFee || 0));

    // Commission (10% par défaut ou valeur serveur)
    const commissionValue = order.commission ?? Math.round((subtotal + (order.shippingFee || 0)) * 0.10);
    els.commission.textContent = formatFCFA(commissionValue);

    els.statusSelect.value = order.status || 'pending';
  }

  /* ===============================
     Mise à jour statut + SMS
  ================================ */
  els.saveBtn.addEventListener('click', async () => {
    const orderId = getOrderId();
    const status = els.statusSelect.value;

    if (!orderId || !status) return;

    try {
      // Update status serveur
      await apiFetch(`/api/orders/${orderId}/status`, {
        method: 'PUT',
        body: JSON.stringify({ status })
      });

      // Envoi SMS automatique au client
      try {
        await apiFetch(`/api/orders/${orderId}/notify`, { method: 'POST' });
      } catch (smsErr) {
        console.warn('Erreur envoi SMS :', smsErr.message);
      }

      els.saveBtn.textContent = '✔ Statut enregistré';
      els.saveBtn.disabled = true;
      setTimeout(() => {
        els.saveBtn.textContent = 'Enregistrer les modifications';
        els.saveBtn.disabled = false;
      }, 2000);

    } catch (err) {
      console.error(err);
      alert('Erreur lors de la mise à jour du statut');
    }
  });

  /* ===============================
     INIT
  ================================ */
  try {
    const orderId = getOrderId();
    if (!orderId) throw new Error('ID commande manquant');

    const order = await loadOrder(orderId);
    if (!order) throw new Error('Commande introuvable');

    renderOrder(order);

  } catch (err) {
    console.error(err);
    els.title.textContent = 'Commande introuvable';
  }

});
