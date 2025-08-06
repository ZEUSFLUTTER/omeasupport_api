<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vérifier si la colonne n'existe pas déjà
            if (!Schema::hasColumn('users', 'photo_profile')) {
                $table->string('photo_profile')->nullable()->after('role');
            }
        });

        // Créer le répertoire pour les photos de profil
        $profilePhotosPath = storage_path('app/public/profile_photos');
        if (!file_exists($profilePhotosPath)) {
            mkdir($profilePhotosPath, 0755, true);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'photo_profile')) {
                $table->dropColumn('photo_profile');
            }
        });
    }
};