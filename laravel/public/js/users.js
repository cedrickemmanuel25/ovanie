const rowsPerPage = 5;
let currentPage = 1;
let allUsers = [];
let filteredUsers = [];

document.addEventListener('DOMContentLoaded', () => {
  fetchUsers();
});

async function apiFetch(url, options = {}) {
  try {
    const res = await fetch(url, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      ...options
    });

    const contentType = res.headers.get('content-type') || '';
    const data = contentType.includes('application/json')
      ? await res.json()
      : await res.text();

    if (!res.ok) {
      throw new Error(
        typeof data === 'object' && data !== null
          ? (data.message || data.error || `HTTP ${res.status}`)
          : `HTTP ${res.status}`
      );
    }

    return data;
  } catch (err) {
    console.error('Erreur API:', err.message);
    alert(err.message);
    return null;
  }
}

async function fetchUsers() {
  const users = await apiFetch('/api/admin/users'); // IMPORTANT
  if (!users) return;

  allUsers = Array.isArray(users) ? users : (users.data || []);
  filteredUsers = [...allUsers];

  renderUsers();
  setupPagination();
  showPage(1);
}

function renderUsers() {
  const tbody = document.querySelector('#usersTable tbody');
  tbody.innerHTML = '';

  if (!filteredUsers.length) {
    tbody.innerHTML = '<tr><td colspan="8">Aucun utilisateur</td></tr>';
    return;
  }

  filteredUsers.forEach((user) => {
    const tr = document.createElement('tr');

    tr.innerHTML = `
      <td>${user.id ?? '-'}</td>
      <td>${user.name ?? '-'}</td>
      <td>${user.email ?? '-'}</td>
      <td>${user.phone ?? '-'}</td>
      <td>${user.role ?? 'client'}</td>
      <td>${user.status ?? 'actif'}</td>
      <td>${user.created_at ? new Date(user.created_at).toLocaleDateString('fr-FR') : '-'}</td>
      <td>
        <button type="button" class="btn-edit">✏️</button>
        <button type="button" class="btn-delete">🗑️</button>
      </td>
    `;

    tr.querySelector('.btn-edit').addEventListener('click', () => editUser(user));
    tr.querySelector('.btn-delete').addEventListener('click', () => deleteUser(user));

    tbody.appendChild(tr);
  });
}

function searchUsers() {
  const input = document.getElementById('searchInput').value.toLowerCase();

  filteredUsers = allUsers.filter(user =>
    (user.name || '').toLowerCase().includes(input) ||
    (user.email || '').toLowerCase().includes(input) ||
    (user.phone || '').toLowerCase().includes(input) ||
    (user.role || '').toLowerCase().includes(input)
  );

  currentPage = 1;
  renderUsers();
  setupPagination();
  showPage(currentPage);
}

function setupPagination() {
  const pagination = document.getElementById('pagination');
  pagination.innerHTML = '';

  const pageCount = Math.ceil(filteredUsers.length / rowsPerPage);

  for (let i = 1; i <= pageCount; i++) {
    const btn = document.createElement('button');
    btn.innerText = i;
    btn.className = 'page-btn';
    if (i === currentPage) btn.classList.add('active');

    btn.addEventListener('click', () => {
      currentPage = i;
      showPage(i);
      updatePagination();
    });

    pagination.appendChild(btn);
  }
}

function showPage(page) {
  const rows = document.querySelectorAll('#usersTable tbody tr');
  rows.forEach((row, index) => {
    const start = (page - 1) * rowsPerPage;
    const end = page * rowsPerPage;
    row.style.display = index >= start && index < end ? '' : 'none';
  });
}

function updatePagination() {
  document.querySelectorAll('.page-btn').forEach((btn, idx) => {
    btn.classList.toggle('active', idx === currentPage - 1);
  });
}

function showAddUserForm() {
  document.getElementById('form-title').innerText = 'Ajouter un utilisateur';
  document.getElementById('user-form').reset();
  document.getElementById('user-id').value = '';
  document.getElementById('user-form-section').classList.remove('hidden');
}

function hideAddUserForm() {
  document.getElementById('user-form-section').classList.add('hidden');
}

async function saveUser(e) {
  e.preventDefault();

  const userId = document.getElementById('user-id').value;
  const userData = {
    name: document.getElementById('user-name').value.trim(),
    email: document.getElementById('user-email').value.trim(),
    phone: document.getElementById('user-phone').value.trim(),
    role: document.getElementById('user-role').value,
    status: document.getElementById('user-status').value
  };

  if (!userData.name || !userData.email) {
    alert('Nom et email obligatoires');
    return;
  }

  const url = userId ? `/api/admin/users/${userId}` : '/api/admin/users';
  const method = userId ? 'PUT' : 'POST';

  const result = await apiFetch(url, {
    method,
    body: JSON.stringify(userData)
  });

  if (result) {
    await fetchUsers();
    hideAddUserForm();
  }
}

function editUser(user) {
  showAddUserForm();

  document.getElementById('form-title').innerText = 'Modifier un utilisateur';
  document.getElementById('user-id').value = user.id ?? '';
  document.getElementById('user-name').value = user.name ?? '';
  document.getElementById('user-email').value = user.email ?? '';
  document.getElementById('user-phone').value = user.phone ?? '';
  document.getElementById('user-role').value = user.role ?? 'client';
  document.getElementById('user-status').value = user.status ?? 'actif';
}

async function deleteUser(user) {
  if (!user.id) {
    alert("ID utilisateur introuvable");
    return;
  }

  if (!confirm(`Supprimer l’utilisateur "${user.name || 'sans nom'}" ?`)) return;

  const result = await apiFetch(`/api/admin/users/${user.id}`, {
    method: 'DELETE'
  });

  if (result) {
    await fetchUsers();
  }
}