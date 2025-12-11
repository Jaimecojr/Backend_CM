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
        // Tabla formulario afiliacion
        Schema::create('membership_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lastname');
            $table->string('id_card');
            $table->string('phone');
            $table->string('email');
            $table->date('bithdate')->nullable();
            $table->string('address');
            $table->unsignedBigInteger('city_id'); // foránea ciudad
            $table->date('date');
            $table->string('seller');
            $table->tinyInteger('state')->default(0);
            $table->timestamps();

            // Llaves Foraneas
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
        });        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_forms');
    }
};
