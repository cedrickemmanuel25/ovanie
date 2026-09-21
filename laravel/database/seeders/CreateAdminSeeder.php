<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class CreateAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Supprimer l'ancien admin si existant
        User::where('email', 'admin@ovanie.com')->delete();

        $admin = User::create([
            'first_name'        => 'Admin',
            'last_name'         => 'Ovanie',
            'name'              => 'Admin Ovanie',
            'email'             => 'admin@ovanie.com',
            'password'          => Hash::make('Ovanie@Admin2024!'),
            'role'              => 'admin',
            'is_admin'          => 1,
            'status'            => 'active',
            'email_verified_at' => now(),
            'account_type'      => 'professionnel',
        ]);

        echo "✅ Admin créé avec succès !\n";
        echo "   ID    : " . $admin->id . "\n";
        echo "   Email : " . $admin->email . "\n";
        echo "   Mot de passe : Ovanie\@Admin2024!\n";
    }
}
