<section class="profile-section">
    <style>
        .password-section-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #1f1f1f;
        }

        .password-section-text {
            margin: 8px 0 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
        }

        .password-form {
            margin-top: 22px;
        }

        .password-field {
            margin-bottom: 18px;
        }

        .password-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #222;
        }

        .password-input {
            width: 100%;
            height: 48px;
            border: 1px solid #dcdfe4;
            border-radius: 12px;
            padding: 0 14px;
            font-size: 15px;
            color: #222;
            background: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }

        .password-input:focus {
            outline: none;
            border-color: #ff7a18;
            box-shadow: 0 0 0 4px rgba(255, 122, 24, 0.12);
        }

        .password-error {
            margin-top: 8px;
            color: #dc2626;
            font-size: 13px;
            font-weight: 600;
        }

        .password-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .password-btn {
            min-width: 170px;
            height: 48px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #111827, #374151);
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(17, 24, 39, 0.22);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .password-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(17, 24, 39, 0.3);
        }

        .password-saved {
            color: #15803d;
            font-size: 14px;
            font-weight: 700;
        }
    </style>

    <header>
        <h2 class="password-section-title">Modifier le mot de passe</h2>
        <p class="password-section-text">
            Utilisez un mot de passe long et sécurisé pour mieux protéger votre compte.
        </p>
    </header>

    <form method="post" action="{{ route('user.password.update') }}" class="password-form">
        @csrf
        @method('patch')

        <div class="password-field">
            <label for="update_password_current_password" class="password-label">Mot de passe actuel</label>
            <input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="password-input"
                autocomplete="current-password"
            >
            @if ($errors->updatePassword->get('current_password'))
                <div class="password-error">{{ $errors->updatePassword->first('current_password') }}</div>
            @endif
        </div>

        <div class="password-field">
            <label for="update_password_password" class="password-label">Nouveau mot de passe</label>
            <input
                id="update_password_password"
                name="password"
                type="password"
                class="password-input"
                autocomplete="new-password"
            >
            @if ($errors->updatePassword->get('password'))
                <div class="password-error">{{ $errors->updatePassword->first('password') }}</div>
            @endif
        </div>

        <div class="password-field">
            <label for="update_password_password_confirmation" class="password-label">Confirmer le mot de passe</label>
            <input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="password"
                class="password-input"
                autocomplete="new-password"
            >
            @if ($errors->updatePassword->get('password_confirmation'))
                <div class="password-error">{{ $errors->updatePassword->first('password_confirmation') }}</div>
            @endif
        </div>

        <div class="password-actions">
            <button type="submit" class="password-btn">Mettre à jour</button>

            @if (session('status') === 'password-updated')
                <span
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="password-saved"
                >
                    Mot de passe mis à jour.
                </span>
            @endif
        </div>
    </form>
</section>