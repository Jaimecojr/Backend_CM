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
        // Tabla Afiliados / Usuarios
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counselor_id'); // foránea counselor
            $table->string('contract_code')->nullable();
            $table->string('name');
            $table->string('lastname');
            $table->date('bithdate')->nullable();
            $table->string('id_card');
            $table->string('phone')->nullable();
            $table->string('movil');
            $table->string('address')->nullable();
            $table->unsignedBigInteger('city_id'); // foránea ciudad
            $table->string('email')->nullable();
            $table->date('validity');
            $table->unsignedBigInteger('agreement_id'); // foránea convenio
            $table->string('company')->nullable();
            $table->string('photo')->nullable();
            $table->string('photo_rename')->nullable();
            $table->date('validity_end');
            $table->date('payment_date')->nullable();
            $table->integer('value')->default(0);
            $table->integer('balance')->default(0);
            $table->integer('commission')->default(0);
            $table->enum('payment_commission', ['si', 'no'])->default('no');
            $table->tinyInteger('stade')->default(1);
            $table->enum('carnet', ['si', 'no'])->default('no');
            $table->tinyInteger('state')->default(1);
            $table->unsignedBigInteger('user_id'); // foránea franquicia / usuario
            $table->timestamps();

            // Foreign keys
            $table->foreign('counselor_id')->references('id')->on('counselors')->onDelete('cascade');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
            $table->foreign('agreement_id')->references('id')->on('agreements')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('city_id', 'affiliates_city_id_index');
            $table->index('counselor_id', 'affiliates_counselor_id_index');
            $table->index('agreement_id', 'affiliates_agreement_id_index');
            $table->index('user_id', 'affiliates_user_id_index');
            $table->index('stade', 'affiliates_stade_index');
            $table->index(['stade', 'id'], 'affiliates_stade_id_index');
            $table->index('id_card', 'affiliates_id_card_index');
            $table->index('validity_end', 'affiliates_validity_end_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
