// =======================================
// ADMIN – GESTION DES PROMOTIONS (100% SERVEUR)
// =======================================

document.addEventListener('DOMContentLoaded', async () => {

  const promoFormSection = document.getElementById('promo-form-section');
  const promoForm = document.getElementById('promo-form');
  const formTitle = document.getElementById('form-title');
  const tableBody = document.querySelector('.promo-list tbody');

  const nameInput = document.getElementById('promo-name');
  const typeInput = document.getElementById('promo-type');
  const startDateInput = document.getElementById('start-date');
  const endDateInput = document.getElementById('end-date');
  const productsInput = document.getElementById('products');

  let editPromoId = null;

  /* ===============================
     UTIL API SÉCURISÉ
  ================================== */
  async function apiFetch(url, options = {}) {
    try {
      const res = await fetch(url, {
        credentials: "include", // ✅ JWT côté serveur
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
      console.error('Erreur API:', err.message);
      alert(err.message);
      return null;
    }
  }

  /* ===============================
     Vérification admin
  ================================== */
  const currentUser = await apiFetch("/api/auth/me");
  if (!currentUser || currentUser.role !== "admin") {
    alert("Accès réservé à l’administrateur");
    return window.location.href = "/login.html";
  }

  /* ===============================
     CHARGER LES PROMOTIONS
  ================================== */
  async function fetchPromotions() {
    const promos = await apiFetch('/api/promotions');
    if (!promos) return;

    tableBody.innerHTML = '';
    promos.forEach(addPromoToTable);
  }

  await fetchPromotions();

  /* ===============================
     FORMULAIRE
  ================================== */
  window.showAddPromoForm = () => {
    formTitle.innerText = 'Ajouter une promotion';
    promoForm.reset();
    editPromoId = null;
    promoFormSection.classList.remove('hidden');
  };

  window.hideAddPromoForm = () => {
    promoFormSection.classList.add('hidden');
  };

  promoForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const promoData = {
      name: nameInput.value.trim(),
      type: typeInput.value,
      start: startDateInput.value,
      end: endDateInput.value,
      products: productsInput.value.trim()
    };

    if (!promoData.name || !promoData.start || !promoData.end) {
      alert('❌ Veuillez remplir tous les champs obligatoires');
      return;
    }

    const url = editPromoId ? `/api/promotions/${editPromoId}` : '/api/promotions';
    const method = editPromoId ? 'PUT' : 'POST';

    const result = await apiFetch(url, { method, body: JSON.stringify(promoData) });
    if (result) {
      await fetchPromotions();
      hideAddPromoForm();
    }
  });

  /* ===============================
     AJOUTER UNE LIGNE AU TABLEAU
  ================================== */
  function addPromoToTable(promo) {
    const row = document.createElement('tr');
    row.dataset.id = promo.id;

    row.innerHTML = `
      <td>${promo.id}</td>
      <td>${promo.name}</td>
      <td>${promo.type}</td>
      <td>${promo.start}</td>
      <td>${promo.end}</td>
      <td>${promo.products}</td>
      <td>
        <button class="btn-edit">Modifier</button>
        <button class="btn-delete">Supprimer</button>
      </td>
    `;
    tableBody.appendChild(row);
  }

  /* ===============================
     ACTIONS EDIT / DELETE
  ================================== */
  tableBody.addEventListener('click', async (e) => {
    const btn = e.target;
    const row = btn.closest('tr');
    if (!row) return;

    const promoId = row.dataset.id;

    if (btn.classList.contains('btn-edit')) {
      editPromoId = promoId;
      formTitle.innerText = 'Modifier la promotion';

      nameInput.value = row.cells[1].innerText;
      typeInput.value = row.cells[2].innerText;
      startDateInput.value = row.cells[3].innerText;
      endDateInput.value = row.cells[4].innerText;
      productsInput.value = row.cells[5].innerText;

      promoFormSection.classList.remove('hidden');
    }

    if (btn.classList.contains('btn-delete')) {
      if (!confirm('❌ Supprimer cette promotion ?')) return;

      const result = await apiFetch(`/api/promotions/${promoId}`, { method: 'DELETE' });
      if (result) row.remove();
    }
  });

});
