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
        // Tabla renovaciones
        Schema::create('renovations', function (Blueprint $table) {
            $table->id();
            $table->date('date_ini');
            $table->date('date_end');
            $table->date('date_payment');
            $table->integer('value');
            $table->unsignedBigInteger('affiliate_id');
            $table->timestamps();

            // Llave Foranea
            $table->foreign('affiliate_id')->references('id')->on('affiliates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renovations');
    }
};
