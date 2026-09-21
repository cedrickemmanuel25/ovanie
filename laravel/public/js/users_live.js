// =======================================
// ADMIN – UTILISATEURS LIVE (soumissions)
// =======================================

const tableBody = document.querySelector('#usersTable tbody');
let lastFetchedUsers = [];

// ===============================
// UTIL API
// ===============================
async function apiFetch(url, options = {}) {
  try {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      ...options
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
  } catch (err) {
    console.error('Erreur API:', err.message);
    return null;
  }
}

// ===============================
// RÉCUPÉRER UTILISATEURS LIVE
// ===============================
async function fetchLiveUsers() {
  const users = await apiFetch('/api/submissions');
  if (!users) return;

  // Détecter nouvelles soumissions
  const newUsers = users.filter(
    u => !lastFetchedUsers.some(l => l.id === u.id)
  );

  // Ajouter les nouvelles en haut du tableau
  newUsers.reverse().forEach(user => {
    const tr = document.createElement('tr');
    tr.dataset.id = user.id;

    tr.innerHTML = `
      <td><strong>NEW</strong></td>
      <td>${user.name || '-'}</td>
      <td>${user.email || '-'}</td>
      <td>${user.message || '-'}</td>
      <td>${new Date(user.createdAt).toLocaleString()}</td>
      <td>visiteur</td>
      <td>actif</td>
      <td>
        <button onclick="viewUser('${user.id}')">Voir</button>
      </td>
    `;

    tableBody.prepend(tr);
  });

  lastFetchedUsers = users;
}

// ===============================
// UTILITAIRE : Voir utilisateur
// ===============================
function viewUser(userId) {
  // Redirection vers la page de détails (à implémenter)
  window.location.href = `user_detail.html?id=${userId}`;
}

// ===============================
// INIT & REFRESH
// ===============================
document.addEventListener('DOMContentLoaded', () => {
  fetchLiveUsers();
  setInterval(fetchLiveUsers, 5000);
});
