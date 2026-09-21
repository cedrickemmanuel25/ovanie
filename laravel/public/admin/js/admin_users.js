(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('userFormModal');
        const form = document.getElementById('userAdminForm');
        const createButton = document.getElementById('openCreateUserModal');
        const closeButton = document.getElementById('closeUserModal');
        const cancelButton = document.getElementById('cancelUserModal');
        const methodInput = document.getElementById('userFormMethod');
        const modeInput = document.getElementById('userFormMode');
        const idInput = document.getElementById('userFormId');
        const title = document.getElementById('userModalTitle');
        const subtitle = document.getElementById('userModalSubtitle');
        const saveButton = document.getElementById('saveUserButton');
        const password = document.getElementById('user_password');
        const passwordConfirmation = document.getElementById('user_password_confirmation');
        const passwordRequiredMark = document.getElementById('passwordRequiredMark');
        const passwordConfirmationRequiredMark = document.getElementById('passwordConfirmationRequiredMark');
        const passwordHelp = document.getElementById('passwordHelp');

        if (!modal || !form) {
            return;
        }

        function openDialog() {
            document.body.classList.add('users-modal-open');

            if (typeof modal.showModal === 'function') {
                if (!modal.open) modal.showModal();
            } else {
                modal.setAttribute('open', 'open');
            }

            window.requestAnimationFrame(function () {
                const firstInput = modal.querySelector('input:not([type=hidden]), select, textarea, button');
                firstInput?.focus({ preventScroll: true });
            });
        }

        function closeDialog() {
            document.body.classList.remove('users-modal-open');

            if (typeof modal.close === 'function') {
                if (modal.open) modal.close();
            } else {
                modal.removeAttribute('open');
            }
        }

        function setPasswordMode(isCreate) {
            password.required = isCreate;
            passwordConfirmation.required = isCreate;
            passwordRequiredMark.hidden = !isCreate;
            passwordConfirmationRequiredMark.hidden = !isCreate;
            passwordHelp.textContent = isCreate
                ? '8 caractères minimum, avec lettres et chiffres.'
                : 'Laissez vide pour conserver le mot de passe actuel.';
        }

        function setCreateMode() {
            form.reset();
            form.action = form.dataset.storeUrl;
            methodInput.value = 'POST';
            modeInput.value = 'create';
            idInput.value = '';
            title.textContent = 'Ajouter un utilisateur';
            subtitle.textContent = 'Créez un compte client, vendeur ou administrateur.';
            saveButton.textContent = 'Créer le compte';
            setPasswordMode(true);
        }

        function setEditMode(data) {
            form.action = data.updateUrl;
            methodInput.value = 'PUT';
            modeInput.value = 'edit';
            idInput.value = data.userId;
            document.getElementById('user_name').value = data.userName || '';
            document.getElementById('user_email').value = data.userEmail || '';
            document.getElementById('user_phone').value = data.userPhone || '';
            document.getElementById('user_role').value = data.userRole || 'client';
            document.getElementById('user_status').value = data.userStatus || 'active';
            password.value = '';
            passwordConfirmation.value = '';
            title.textContent = 'Modifier un utilisateur';
            subtitle.textContent = 'Mettez à jour les informations et le statut du compte.';
            saveButton.textContent = 'Enregistrer les modifications';
            setPasswordMode(false);
        }

        createButton?.addEventListener('click', function () {
            setCreateMode();
            openDialog();
        });

        document.querySelectorAll('.js-edit-user').forEach(function (button) {
            button.addEventListener('click', function () {
                setEditMode(button.dataset);
                openDialog();
            });
        });

        closeButton?.addEventListener('click', closeDialog);
        cancelButton?.addEventListener('click', closeDialog);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeDialog();
            }
        });

        modal.addEventListener('cancel', function (event) {
            event.preventDefault();
            closeDialog();
        });

        modal.addEventListener('close', function () {
            document.body.classList.remove('users-modal-open');
        });

        const oldData = window.ovanieUserFormOldData || {};
        if (modal.dataset.mustOpen === '1') {
            if (oldData.mode === 'edit' && oldData.user_id) {
                const matchingButton = document.querySelector(`.js-edit-user[data-user-id="${oldData.user_id}"]`);
                if (matchingButton) {
                    setEditMode(matchingButton.dataset);
                    document.getElementById('user_name').value = oldData.name || '';
                    document.getElementById('user_email').value = oldData.email || '';
                    document.getElementById('user_phone').value = oldData.phone || '';
                    document.getElementById('user_role').value = oldData.role || 'client';
                    document.getElementById('user_status').value = oldData.status || 'active';
                } else {
                    setCreateMode();
                }
            } else {
                setCreateMode();
                document.getElementById('user_name').value = oldData.name || '';
                document.getElementById('user_email').value = oldData.email || '';
                document.getElementById('user_phone').value = oldData.phone || '';
                document.getElementById('user_role').value = oldData.role || 'client';
                document.getElementById('user_status').value = oldData.status || 'active';
            }

            openDialog();
        }
    });
})();
