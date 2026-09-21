<script>
document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('type');
    const title = document.getElementById('title');
    const text = document.getElementById('text');
    const link = document.getElementById('link');
    const duration = document.getElementById('duration');
    const sortOrder = document.getElementById('sort_order');
    const media = document.getElementById('media');

    const previewTitle = document.getElementById('previewTitle');
    const previewText = document.getElementById('previewText');
    const previewLink = document.getElementById('previewLink');
    const previewDuration = document.getElementById('previewDuration');
    const previewOrder = document.getElementById('previewOrder');
    const previewTypeBadge = document.getElementById('previewTypeBadge');
    const livePreviewMedia = document.getElementById('livePreviewMedia');

    function refreshPreview() {
        if (previewTitle) previewTitle.textContent = title?.value.trim() || 'Titre de votre publicité';
        if (previewText) previewText.textContent = text?.value.trim() || 'Votre texte de publicité apparaîtra ici.';
        if (previewLink) previewLink.textContent = link?.value.trim() || 'Aucun lien renseigné';
        if (previewDuration) previewDuration.textContent = (duration?.value || 5000) + ' ms';
        if (previewOrder) previewOrder.textContent = sortOrder?.value || '0';
        if (previewTypeBadge) previewTypeBadge.textContent = (type?.value || 'image').toUpperCase();
    }

    function refreshMediaPreview(file) {
        if (!file || !livePreviewMedia) return;
        const objectUrl = URL.createObjectURL(file);

        if ((type?.value || 'image') === 'video') {
            livePreviewMedia.innerHTML = `<video controls muted playsinline><source src="${objectUrl}"></video>`;
        } else {
            livePreviewMedia.innerHTML = `<img src="${objectUrl}" alt="Aperçu publicité">`;
        }
    }

    [type, title, text, link, duration, sortOrder].forEach(el => {
        el?.addEventListener('input', refreshPreview);
        el?.addEventListener('change', refreshPreview);
    });

    type?.addEventListener('change', () => {
        refreshPreview();
        if (media?.files && media.files[0]) refreshMediaPreview(media.files[0]);
    });

    media?.addEventListener('change', event => {
        const file = event.target.files?.[0];
        if (file) refreshMediaPreview(file);
    });

    refreshPreview();
});
</script>
