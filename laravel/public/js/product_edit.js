// =======================================
// ADMIN – MODIFICATION / SUPPRESSION PRODUIT
// 100% SERVER.JS (JWT via cookie)
// =======================================

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.querySelector('#productForm');
  const saveBtn = document.querySelector('.btn-save');
  const deleteBtn = document.querySelector('.btn-delete');

  const urlParams = new URLSearchParams(window.location.search);
  const productId = urlParams.get('id');

  if (!form || !productId) return;

  // 🔐 Vérifier que l'utilisateur est admin
  const currentUser = await apiFetch('/api/auth/me');
  if (!currentUser || currentUser.role !== 'admin') {
    alert("Accès réservé à l’administrateur");
    return window.location.href = '/login.html';
  }

  // Charger catégories et produit
  await loadCategories();
  await loadProduct(productId);

  // Sauvegarder
  saveBtn.addEventListener('click', async (e) => {
    e.preventDefault();
    await saveProduct(productId);
  });

  // Supprimer produit
  deleteBtn.addEventListener('click', async () => {
    await deleteProduct(productId);
  });

  // Envoyer SMS au vendeur
  const smsBtn = document.querySelector('.btn-sms');
  if (smsBtn) {
    smsBtn.addEventListener('click', async () => {
      try {
        const res = await apiFetch(`/api/products/${productId}/notify-vendor`, { method: 'POST' });
        if (res?.success) alert('SMS envoyé au vendeur ✔');
      } catch (err) {
        alert(err.message || 'Erreur SMS');
      }
    });
  }
});

/* ===============================
   UTIL API SERVER
================================ */
async function apiFetch(url, options = {}) {
  try {
    const res = await fetch(url, {
      ...options,
      credentials: "include",
      headers: { "Content-Type": "application/json", ...(options.headers || {}) }
    });

    if (res.status === 401) {
      window.location.href = "/login.html";
      return;
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
  const select = document.querySelector('#categorySelect');
  if (!select) return;

  const categories = await apiFetch('/api/categories');
  if (!categories) return;

  select.innerHTML = '<option value="">Choisir une catégorie</option>';
  categories.forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat.id;
    opt.textContent = cat.name;
    select.appendChild(opt);
  });
}

/* ===============================
   Charger produit depuis serveur
================================ */
async function loadProduct(id) {
  const product = await apiFetch(`/api/products/${id}`);
  if (!product) return;

  const form = document.querySelector('#productForm');
  form.elements['name'].value = product.name || '';
  form.elements['description'].value = product.description || '';
  form.elements['price'].value = product.price || '';
  form.elements['promo_price'].value = product.promo_price || '';
  form.elements['stock'].value = product.stock || '';
  form.elements['status'].value = product.status || '';
  form.elements['category_id'].value = product.category_id || '';
  form.elements['commission_paid'].checked = product.commissionPaid || false;
  form.elements['lock_contacts'].checked = product.lockContacts || false;

  // Images
  const imagesContainer = document.querySelector('#imagesContainer');
  imagesContainer.innerHTML = '';
  (product.images || []).forEach((imgUrl, idx) => {
    const div = document.createElement('div');
    div.className = 'img-box';
    div.innerHTML = `
      <img src="${imgUrl}" alt="Produit">
      <button type="button" class="btn-delete-image">Supprimer</button>
    `;
    imagesContainer.appendChild(div);

    div.querySelector('.btn-delete-image').addEventListener('click', () => {
      if (confirm('Supprimer cette image ?')) div.remove();
    });
  });
}

/* ===============================
   Sauvegarder produit
================================ */
async function saveProduct(id) {
  const form = document.querySelector('#productForm');
  const submitBtn = document.querySelector('.btn-save');
  submitBtn.disabled = true;

  const formData = new FormData(form);

  // Ajouter les images restantes
  document.querySelectorAll('#imagesContainer img').forEach((img, idx) => {
    formData.append(`existing_images[${idx}]`, img.src);
  });

  try {
    const result = await fetch(`/api/products/${id}`, {
      method: 'PUT',
      credentials: "include",
      body: formData
    });

    if (result.ok) {
      alert('Produit mis à jour avec succès !');
    } else {
      const err = await result.json().catch(() => ({}));
      alert(err.error || "Erreur lors de la mise à jour");
    }
  } catch (err) {
    alert(err.message || "Erreur serveur");
  } finally {
    submitBtn.disabled = false;
  }
}

/* ===============================
   Supprimer produit
================================ */
async function deleteProduct(id) {
  if (!confirm('Supprimer ce produit ?')) return;

  try {
    const result = await fetch(`/api/products/${id}`, {
      method: 'DELETE',
      credentials: "include"
    });

    if (result.ok) {
      alert('Produit supprimé !');
      window.location.href = 'products.html';
    } else {
      const err = await result.json().catch(() => ({}));
      alert(err.error || "Erreur lors de la suppression");
    }
  } catch (err) {
    alert(err.message || "Erreur serveur");
  }
}
