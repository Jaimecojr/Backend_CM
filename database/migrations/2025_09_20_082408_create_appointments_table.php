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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->integer('afi_code')->comment('Puede ser el codigo del usuario o el codigo del beneficiario');
            $table->unsignedBigInteger('doctor_id'); // Foranea con doctor
            $table->date('date');
            $table->string('hour');
            $table->string('address');
            $table->unsignedBigInteger('city_id'); // Foranea con Ciudad
            $table->string('phone')->comment('Telefono del usuario que tiene la cita');
            $table->integer('value');
            $table->integer('type')->comment('1: titular 2: beneficiario');
            $table->string('name');
            $table->unsignedBigInteger('user_id'); // foránea franquicia / usuario
            $table->timestamps();

            // Foreign keys
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
