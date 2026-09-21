// =======================================
// ADMIN – CREATION PRODUIT (SERVEUR)
// =======================================

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.querySelector('.product-form');
  if (!form) return;

  // Charger catégories depuis le serveur
  await loadCategories();

  // Soumission produit
  form.addEventListener('submit', handleProductSubmit);
});

/* ===============================
   UTIL API – CENTRALISÉ
================================ */
async function apiFetch(url, options = {}) {
  try {
    const res = await fetch(url, {
      credentials: "include",      // ✅ JWT via serveur
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
   Charger catégories
================================ */
async function loadCategories() {
  const select = document.querySelector('select[name="category_id"]');
  if (!select) return;

  const categories = await apiFetch('/api/categories');
  if (!categories) return;

  select.innerHTML = '<option value="">Choisir une catégorie</option>';
  categories.forEach(cat => {
    if (!cat.id) return;
    const opt = document.createElement('option');
    opt.value = cat.id;
    opt.textContent = cat.name;
    select.appendChild(opt);
  });
}

/* ===============================
   Soumission produit
================================ */
async function handleProductSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  submitBtn.disabled = true;

  // Vérification des champs obligatoires
  if (!form.name.value || !form.description.value || !form.price.value || !form.stock.value || !form.category_id.value) {
    alert('Veuillez remplir tous les champs obligatoires (*)');
    submitBtn.disabled = false;
    return;
  }

  if (form.price.value <= 0 || form.stock.value < 0) {
    alert('Le prix et le stock doivent être valides');
    submitBtn.disabled = false;
    return;
  }

  // Construction FormData pour upload images et options
  const formData = new FormData();
  formData.append('name', form.name.value.trim());
  formData.append('description', form.description.value.trim());
  formData.append('category_id', form.category_id.value);
  formData.append('status', form.status.value);
  formData.append('price', form.price.value);
  formData.append('promo_price', form.promo_price.value || '');
  formData.append('stock', form.stock.value);
  formData.append('weight', form.weight.value || '');
  formData.append('delivery_time', form.delivery_time.value || '');
  formData.append('commission_paid', form.commission_paid.checked ? '1' : '0');
  formData.append('lock_contacts', form.lock_contacts.checked ? '1' : '0');

  // Images
  if (form.main_image.files[0]) formData.append('main_image', form.main_image.files[0]);
  Array.from(form['gallery[]'].files).forEach(file => formData.append('gallery[]', file));

  // Envoi vers serveur
  try {
    const result = await apiFetch('/api/products', { method: 'POST', body: formData });

    if (result) {
      alert('Produit enregistré avec succès !');

      // SMS au vendeur si applicable
      if (result.vendorPhone) {
        try {
          await apiFetch(`/api/products/${result.id}/notify-vendor`, { method: 'POST' });
        } catch (err) {
          console.warn('Erreur envoi SMS au vendeur:', err.message);
        }
      }

      form.reset();
    }

  } catch (err) {
    alert(err.message || 'Erreur serveur');
  } finally {
    submitBtn.disabled = false;
  }
}
