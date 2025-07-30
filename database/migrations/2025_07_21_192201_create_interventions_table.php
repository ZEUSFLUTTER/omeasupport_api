<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('technician_id')->constrained('users')->onDelete('cascade'); // si techniciens sont des users
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->time('duree')->nullable();
            $table->date('date_prise_en_charge')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('interventions');
    }

};
