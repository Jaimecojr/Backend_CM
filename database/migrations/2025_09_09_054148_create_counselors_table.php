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
        // Tabla vendedores
        Schema::create('counselors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lastname');
            $table->string('id_card');
            $table->string('address')->nullable();
            $table->date('date_admission')->nullable();
            $table->enum('type_contra', [
                'Término Fijo',
                'Término Indefinido',
                'Corretaje',
                'Con Garantizado'
            ]);
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('rol')->nullable();
            $table->string('phone')->nullable();
            $table->string('movil')->nullable();
            $table->tinyInteger('state')->default(1);
            $table->unsignedBigInteger('city_id');
            $table->unsignedBigInteger('user_id');

            $table->timestamps();

            // Foreign Keys
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('city_id', 'counselors_city_id_index');
            $table->index('user_id', 'counselors_user_id_index');
            $table->index('state', 'counselors_state_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counselors');
    }
};
