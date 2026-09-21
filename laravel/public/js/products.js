document.addEventListener('DOMContentLoaded', async () => {
  const searchInput = document.querySelector('.filter-bar input');
  const categorySelect = document.querySelectorAll('.filter-bar select')[0];
  const statusSelect = document.querySelectorAll('.filter-bar select')[1];
  const addProductBtn = document.querySelector('.btn-primary');
  const tableBody = document.querySelector('tbody');

  let productsData = [];

  async function apiFetch(url, options = {}) {
    try {
      const res = await fetch(url, {
        credentials: "include",
        headers: { "Content-Type": "application/json", ...(options.headers || {}) },
        ...options
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
      return data;
    } catch (err) {
      console.error("Erreur API:", err.message);
      alert(err.message);
      return [];
    }
  }

  async function loadProducts() {
    productsData = await apiFetch('/api/products'); // côté admin, tu peux créer une route admin api
    renderProducts(productsData);
  }

  function renderProducts(products) {
    tableBody.innerHTML = '';

    if (!products.length) {
      tableBody.innerHTML = `<tr><td colspan="7">Aucun produit</td></tr>`;
      return;
    }

    products.forEach(product => {
      const tr = document.createElement('tr');

      tr.innerHTML = `
        <td><img src="${product.images?.[0] ? '/storage/products/' + product.images[0] : '/admin/images/sample-product.jpg'}" class="product-thumb" alt="${product.name}"></td>
        <td>${product.name || '—'}</td>
        <td>${product.category?.name || '—'}</td>
        <td>${formatFCFA(product.price)}</td>
        <td>${product.stock ?? 0}</td>
        <td>
          <span class="status ${product.status}">${capitalize(product.status)}</span>
        </td>
        <td class="actions">
          <button class="btn-edit">✏️</button>
          <button class="btn-delete">🗑️</button>
          <button class="btn-sms">📩 SMS</button>
        </td>
      `;

      tr.querySelector('.btn-edit').onclick = () => editProduct(product.id);
      tr.querySelector('.btn-delete').onclick = () => deleteProduct(product.id);
      tr.querySelector('.btn-sms').onclick = () => sendProductSMS(product.id);

      tableBody.appendChild(tr);
    });
  }

  function filterProducts() {
    const searchValue = searchInput.value.toLowerCase();
    const categoryValue = categorySelect.value;
    const statusValue = statusSelect.value;

    const filtered = productsData.filter(p => {
      if (searchValue && !p.name.toLowerCase().includes(searchValue)) return false;
      if (categoryValue && p.category?.name !== categoryValue) return false;
      if (statusValue && p.status !== statusValue) return false;
      return true;
    });

    renderProducts(filtered);
  }

  function editProduct(id) {
    window.location.href = `/admin/products/${id}/edit`;
  }

  async function deleteProduct(id) {
    if (!confirm('Supprimer ce produit ?')) return;
    await apiFetch(`/api/products/${id}`, { method: 'DELETE' });
    productsData = productsData.filter(p => p.id !== id);
    renderProducts(productsData);
  }

  async function sendProductSMS(id) {
    await apiFetch(`/api/products/${id}/notify-vendor`, { method: 'POST' });
    alert('SMS envoyé au vendeur ✔');
  }

  function formatFCFA(val = 0) {
    return Number(val).toLocaleString('fr-FR') + ' FCFA';
  }

  function capitalize(text = '') {
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  searchInput.addEventListener('keyup', filterProducts);
  categorySelect.addEventListener('change', filterProducts);
  statusSelect.addEventListener('change', filterProducts);
  addProductBtn.addEventListener('click', () => window.location.href = '/admin/products/create');

  loadProducts();
});
