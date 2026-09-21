document.addEventListener('DOMContentLoaded', () => {
  const go = path => { window.location.href = path; };
  ['add-devis-btn', 'add-devis-shortcut', 'add-devis-tool', 'add-devis-catalog']
    .forEach(id => document.getElementById(id)?.addEventListener('click', () => go('/devis')));
  document.getElementById('add-appel-btn')?.addEventListener('click', () => go('/appel-offre'));
  const search = document.getElementById('search-input');
  search?.addEventListener('input', () => {
    const query = search.value.trim().toLocaleLowerCase('fr');
    document.querySelectorAll('.shop-card').forEach(card => {
      card.hidden = query !== '' && !card.textContent.toLocaleLowerCase('fr').includes(query);
    });
  });
  let seconds = 9937;
  const timer = document.querySelector('.timer');
  if (timer) window.setInterval(() => {
    seconds = Math.max(0, seconds - 1);
    const h = String(Math.floor(seconds / 3600)).padStart(2, '0');
    const m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    timer.textContent = `${h} : ${m} : ${s}`;
  }, 1000);
});
