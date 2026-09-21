<section class="profile-section">
    <style>
        .profile-section-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #1f1f1f;
        }

        .profile-section-text {
            margin: 8px 0 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
        }

        .profile-form {
            margin-top: 22px;
        }

        .profile-field {
            margin-bottom: 18px;
        }

        .profile-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #222;
        }

        .profile-input {
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

        .profile-input:focus {
            outline: none;
            border-color: #ff7a18;
            box-shadow: 0 0 0 4px rgba(255, 122, 24, 0.12);
        }

        .profile-error {
            margin-top: 8px;
            color: #dc2626;
            font-size: 13px;
            font-weight: 600;
        }

        .profile-warning-box {
            margin-top: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
        }

        .profile-warning-text {
            margin: 0;
            font-size: 14px;
            color: #9a3412;
            line-height: 1.6;
        }

        .profile-link-btn {
            border: none;
            background: transparent;
            color: #c2410c;
            font-weight: 700;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
            margin-left: 4px;
        }

        .profile-success {
            margin-top: 10px;
            color: #15803d;
            font-size: 13px;
            font-weight: 700;
        }

        .profile-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .profile-btn {
            min-width: 150px;
            height: 48px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #ff7a18, #ffb347);
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(255, 122, 24, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(255, 122, 24, 0.32);
        }

        .profile-saved {
            color: #15803d;
            font-size: 14px;
            font-weight: 700;
        }
    </style>

    <header>
        <h2 class="profile-section-title">Informations du profil</h2>
        <p class="profile-section-text">
            Modifiez votre nom et votre adresse e-mail.
        </p>
    </header>

    @if (Route::has('verification.send'))
        <form id="send-verification" method="post" action="{{ route('verification.send') }}">
            @csrf
        </form>
    @endif

    <form method="post" action="{{ route('profile.update') }}" class="profile-form">
        @csrf
        @method('patch')

        <div class="profile-field">
            <label for="name" class="profile-label">Nom</label>
            <input
                id="name"
                name="name"
                type="text"
                class="profile-input"
                value="{{ old('name', $user->name) }}"
                required
                autofocus
                autocomplete="name"
            >
            @error('name')
                <div class="profile-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="profile-field">
            <label for="email" class="profile-label">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                class="profile-input"
                value="{{ old('email', $user->email) }}"
                required
                autocomplete="username"
            >
            @error('email')
                <div class="profile-error">{{ $message }}</div>
            @enderror

            @if (
                Route::has('verification.send') &&
                $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&
                ! $user->hasVerifiedEmail()
            )
                <div class="profile-warning-box">
                    <p class="profile-warning-text">
                        Votre adresse e-mail n’est pas encore vérifiée.
                        <button form="send-verification" class="profile-link-btn" type="submit">
                            Renvoyer l’e-mail de vérification
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <div class="profile-success">
                            Un nouveau lien de vérification a été envoyé à votre adresse e-mail.
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="profile-actions">
            <button type="submit" class="profile-btn">Enregistrer</button>

            @if (session('status') === 'profile-updated')
                <span
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="profile-saved"
                >
                    Enregistré.
                </span>
            @endif
        </div>
    </form>
</section>