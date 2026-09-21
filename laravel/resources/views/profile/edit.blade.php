@extends('layouts.guest')

@section('title', 'Mon profil')

@section('styles')
    <style>
        .profile-page {
            max-width: 1100px;
            margin: 30px auto 50px;
            padding: 0 16px;
        }

        .profile-hero {
            background: linear-gradient(135deg, #fff8f2, #ffffff);
            border: 1px solid rgba(255, 122, 24, 0.12);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.06);
            margin-bottom: 22px;
        }

        .profile-hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .profile-title {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: #1f1f1f;
        }

        .profile-subtitle {
            margin: 8px 0 0;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.6;
        }

        .profile-dashboard-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: linear-gradient(135deg, #ff7a18, #ffb347);
            color: #fff;
            font-weight: 700;
            border-radius: 12px;
            padding: 12px 18px;
            box-shadow: 0 10px 24px rgba(255, 122, 24, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .profile-dashboard-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(255, 122, 24, 0.35);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .profile-card {
            background: #ffffff;
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.07);
            border: 1px solid #f2f2f2;
        }

        .profile-card.full {
            grid-column: 1 / -1;
        }

        @media (max-width: 900px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .profile-page {
                margin-top: 20px;
            }

            .profile-title {
                font-size: 24px;
            }

            .profile-card,
            .profile-hero {
                padding: 18px;
                border-radius: 18px;
            }

            .profile-dashboard-btn {
                width: 100%;
            }
        }
    </style>
@endsection

@section('content')
    <div class="profile-page">
        <div class="profile-hero">
            <div class="profile-hero-top">
                <div>
                    <h1 class="profile-title">Mon profil</h1>
                    <p class="profile-subtitle">
                        Gérez vos informations personnelles et la sécurité de votre compte.
                    </p>
                </div>

                @if (Route::has('client.dashboard'))
                    <a href="{{ route('client.dashboard') }}" class="profile-dashboard-btn">
                        Accéder à mon tableau de bord
                    </a>
                @endif
            </div>
        </div>

        <div class="profile-grid">
            <div class="profile-card">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="profile-card">
                @include('profile.partials.update-password-form')
            </div>
            <div class="profile-card full">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
@endsection