<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\TestVendorSeeder;

class CreateTestVendorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-vendor:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crée le compte vendeur de test (Cocody Riviera 2) avec produits à 25 FCFA et livraison à 1 FCFA par commune';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Création du vendeur de test avec sa propre logistique...');

        if (! class_exists(\Database\Seeders\TestVendorSeeder::class)) {
            $seederFile = database_path('seeders/TestVendorSeeder.php');
            if (file_exists($seederFile)) {
                require_once $seederFile;
            } else {
                $this->error("Le fichier {$seederFile} est introuvable sur le serveur. Veuillez l'uploader.");
                return Command::FAILURE;
            }
        }

        $seeder = new \Database\Seeders\TestVendorSeeder();
        $seeder->setCommand($this);
        $seeder->run();
        $this->info('Vendeur de test créé et configuré avec succès !');

        return Command::SUCCESS;
    }
}
