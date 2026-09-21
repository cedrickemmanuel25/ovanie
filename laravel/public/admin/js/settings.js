(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.settings-premium-page input[type="file"]').forEach(function (input) {
            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file || !file.type.startsWith('image/')) return;

                const container = input.closest('.visual-setting-item, .settings-logo-row');
                const preview = container && container.querySelector('img');
                if (!preview) return;

                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    preview.src = String(reader.result || '');
                });
                reader.readAsDataURL(file);
            });
        });

        const form = document.getElementById('settingsForm');
        const submitButton = form && form.querySelector('.btn-submit');

        form?.addEventListener('submit', function () {
            if (!submitButton) return;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span>⏳</span> Enregistrement en cours…';
        });
    });
})();
