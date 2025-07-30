<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type_probleme');
            $table->text('description');
            $table->string('adresse');
            $table->json('photos')->nullable();
            $table->enum('statut', ['pending', 'assign','planified', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('date_rdv')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
