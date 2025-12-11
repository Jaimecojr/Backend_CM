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
        Schema::create('membership_form_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_form_id'); // foránea membership_forms
            $table->string('name');
            $table->timestamps();

            // Llaves Foraneas
            $table->foreign('membership_form_id')->references('id')->on('membership_forms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_form_beneficiaries');
    }
};
