<section class="delete-account-section">
    <style>
        .delete-account-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #1f1f1f;
        }

        .delete-account-text {
            margin: 8px 0 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.7;
        }

        .delete-account-trigger {
            margin-top: 22px;
            min-width: 180px;
            height: 48px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(220, 38, 38, 0.22);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .delete-account-trigger:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(220, 38, 38, 0.32);
        }

        .delete-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.6);
            z-index: 9999;
        }

        .delete-modal.show {
            display: flex;
        }

        .delete-modal-box {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 22px 60px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.2s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .delete-modal-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .delete-modal-text {
            margin: 12px 0 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.7;
        }

        .delete-field {
            margin-top: 18px;
        }

        .delete-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #222;
        }

        .delete-input {
            width: 100%;
            height: 48px;
            border: 1px solid #dcdfe4;
            border-radius: 12px;
            padding: 0 14px;
            font-size: 15px;
            color: #222;
            background: #fff;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .delete-input:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
        }

        .delete-error {
            margin-top: 8px;
            color: #dc2626;
            font-size: 13px;
            font-weight: 600;
        }

        .delete-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .delete-cancel-btn {
            min-width: 120px;
            height: 46px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: #fff;
            color: #374151;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .delete-cancel-btn:hover {
            background: #f9fafb;
        }

        .delete-confirm-btn {
            min-width: 160px;
            height: 46px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #b91c1c, #ef4444);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(185, 28, 28, 0.22);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .delete-confirm-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(185, 28, 28, 0.3);
        }

        @media (max-width: 640px) {
            .delete-modal-box {
                padding: 18px;
                border-radius: 16px;
            }

            .delete-actions {
                flex-direction: column;
            }

            .delete-cancel-btn,
            .delete-confirm-btn,
            .delete-account-trigger {
                width: 100%;
            }
        }
    </style>

    <header>
        <h2 class="delete-account-title">Supprimer le compte</h2>

        <p class="delete-account-text">
            Une fois votre compte supprimé, toutes vos données et ressources seront définitivement effacées.
            Assurez-vous d’avoir sauvegardé les informations que vous souhaitez conserver.
        </p>
    </header>

    <button type="button" class="delete-account-trigger" onclick="openDeleteAccountModal()">
        Supprimer mon compte
    </button>

    <div
        id="deleteAccountModal"
        class="delete-modal {{ $errors->userDeletion->isNotEmpty() ? 'show' : '' }}"
        onclick="closeDeleteAccountModalOnBackdrop(event)"
    >
        <div class="delete-modal-box">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <h3 class="delete-modal-title">Confirmer la suppression</h3>

                <p class="delete-modal-text">
                    Cette action est irréversible. Entrez votre mot de passe pour confirmer la suppression définitive de votre compte.
                </p>

                <div class="delete-field">
                    <label for="delete_account_password" class="delete-label">Mot de passe</label>
                    <input
                        id="delete_account_password"
                        name="password"
                        type="password"
                        class="delete-input"
                        placeholder="Entrez votre mot de passe"
                    >

                    @if ($errors->userDeletion->get('password'))
                        <div class="delete-error">
                            {{ $errors->userDeletion->first('password') }}
                        </div>
                    @endif
                </div>

                <div class="delete-actions">
                    <button type="button" class="delete-cancel-btn" onclick="closeDeleteAccountModal()">
                        Annuler
                    </button>

                    <button type="submit" class="delete-confirm-btn">
                        Supprimer définitivement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openDeleteAccountModal() {
            const modal = document.getElementById('deleteAccountModal');
            if (modal) modal.classList.add('show');
        }

        function closeDeleteAccountModal() {
            const modal = document.getElementById('deleteAccountModal');
            if (modal) modal.classList.remove('show');
        }

        function closeDeleteAccountModalOnBackdrop(event) {
            const modal = document.getElementById('deleteAccountModal');
            if (event.target === modal) {
                closeDeleteAccountModal();
            }
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDeleteAccountModal();
            }
        });
    </script>
</section>