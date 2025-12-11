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
        // Tabla administradores
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('lastname', 100);
            $table->string('phone', 50);
            $table->string('login', 10);
            $table->string('pass', 100);
            $table->enum('type', ['admin', 'adviser']);
            $table->boolean('status')->default(1);
            $table->unsignedBigInteger('city_id');

            // Definimos la FK
            $table->foreign('city_id')
                ->references('id')
                ->on('cities')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
