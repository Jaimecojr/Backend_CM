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
        // Tabla ciudades
        Schema::create('cities', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('department_id'); // FK hacia departments
            $table->string('name', 150);
            $table->timestamps();

            // Definimos la FK
            $table->foreign('department_id')
                  ->references('id')
                  ->on('departments')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
