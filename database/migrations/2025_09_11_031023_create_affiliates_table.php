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
            $table->string('contract_code');
            $table->string('name');
            $table->string('lastname');
            $table->date('bithdate')->nullable();
            $table->string('id_card');
            $table->string('phone');
            $table->string('movil');
            $table->string('address');
            $table->unsignedBigInteger('city_id'); // foránea ciudad
            $table->string('email');
            $table->date('validity');
            $table->integer('value_sale');
            $table->unsignedBigInteger('agreement_id'); // foránea convenio
            $table->integer('balance');
            $table->integer('comission');
            $table->enum('payment_commission', ['si', 'no']);
            $table->string('company');
            $table->string('photo')->nullable();
            $table->string('photo_rename')->nullable();
            $table->date('validity_end');
            $table->tinyInteger('stade')->default(1);
            $table->enum('carnet', ['si', 'no']);
            $table->tinyInteger('state');
            $table->unsignedBigInteger('user_id'); // foránea franquicia / usuario
            $table->date('sale_date');
            $table->timestamps();

            // Foreign keys
            $table->foreign('counselor_id')->references('id')->on('counselors')->onDelete('cascade');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
            $table->foreign('agreement_id')->references('id')->on('agreements')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
