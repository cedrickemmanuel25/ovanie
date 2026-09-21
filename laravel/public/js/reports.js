// =======================================
// ADMIN – RAPPORTS & STATISTIQUES (SERVEUR ONLY)
// =======================================

document.addEventListener('DOMContentLoaded', async () => {

  let categoryChart = null;
  let trafficChart = null;

  const categoryCanvas = document.getElementById('salesCategoryChart');
  const trafficCanvas = document.getElementById('monthlyTrafficChart');

  // 🔐 Vérification admin avant affichage
  const currentUser = await apiFetch('/api/auth/me');
  if (!currentUser || currentUser.role !== 'admin') {
    alert("Accès réservé à l’administrateur");
    return window.location.href = '/login.html';
  }

  if (categoryCanvas) loadSalesByCategory();
  if (trafficCanvas) loadMonthlyTraffic();

  // Rafraîchissement automatique toutes les 5 secondes
  setInterval(() => {
    if (categoryCanvas) loadSalesByCategory();
    if (trafficCanvas) loadMonthlyTraffic();
  }, 5000);

});

/* ===============================
   UTIL API SÉCURISÉ
================================ */
async function apiFetch(url, options = {}) {
  try {
    const res = await fetch(url, {
      credentials: "include", // cookies / JWT envoyés
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
    return null;
  }
}

/* ===============================
   VENTES PAR CATÉGORIE
================================ */
async function loadSalesByCategory() {
  const ctxEl = document.getElementById('salesCategoryChart');
  if (!ctxEl) return;

  try {
    const data = await apiFetch('/api/stats/sales-by-category');
    if (!data) return;

    const ctx = ctxEl.getContext('2d');

    if (categoryChart) {
      categoryChart.data.labels = data.labels;
      categoryChart.data.datasets[0].data = data.values;
      categoryChart.update();
      return;
    }

    categoryChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Ventes par catégorie',
          data: data.values,
          borderWidth: 1,
          backgroundColor: data.labels.map(() => `hsl(${Math.random()*360},70%,60%)`)
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });

  } catch (err) {
    console.error('Erreur stats catégories:', err);
  }
}

/* ===============================
   TRAFIC MENSUEL
================================ */
async function loadMonthlyTraffic() {
  const ctxEl = document.getElementById('monthlyTrafficChart');
  if (!ctxEl) return;

  try {
    const data = await apiFetch('/api/stats/monthly-traffic');
    if (!data) return;

    const ctx = ctxEl.getContext('2d');

    if (trafficChart) {
      trafficChart.data.labels = data.labels;
      trafficChart.data.datasets[0].data = data.values;
      trafficChart.update();
      return;
    }

    trafficChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Activité mensuelle',
          data: data.values,
          tension: 0.3,
          fill: true,
          pointRadius: 4,
          backgroundColor: 'rgba(54,162,235,0.2)',
          borderColor: 'rgba(54,162,235,1)',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

  } catch (err) {
    console.error('Erreur trafic:', err);
  }
}
