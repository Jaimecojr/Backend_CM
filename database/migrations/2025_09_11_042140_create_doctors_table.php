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
        // Tabla doctores
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('specialty_id');
            $table->string('name');
            $table->string('lastname');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('movil');
            $table->string('address');
            $table->string('secretary_name');
            $table->integer('value_agreement');
            $table->tinyInteger('state')->default(1);
            $table->unsignedBigInteger('city_id');
            $table->timestamps();

            // Llaves foraneas
            $table->foreign('specialty_id')->references('id')->on('specialties')->onDelete('cascade');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');

            // Indexes
            $table->index('specialty_id', 'doctors_specialty_id_index');
            $table->index('city_id', 'doctors_city_id_index');
            $table->index('state', 'doctors_state_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
