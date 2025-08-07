<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('rapports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('technicien_id')->constrained('users');
            $table->string('duree');
            $table->text('solution');
            $table->float('prix');
            $table->enum('statut', ['termine', 'suspendu'])->default('termine');
            $table->date('date_intervention')->nullable();
            $table->text('rapport')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports');
    }
};
