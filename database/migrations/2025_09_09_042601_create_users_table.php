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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nit', 100)->unique();
            $table->string('name', 100);
            $table->string('contact', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('movil', 50)->nullable();
            $table->string('address', 150)->nullable();
            $table->date('date_afi')->nullable();
            $table->string('email');
            $table->string('user', 100)->unique();
            $table->string('password');
            $table->boolean('state')->default(true);

            // Relación con ciudades
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id')
                ->references('id')
                ->on('cities')
                ->cascadeOnDelete();
            $table->enum('type', [1, 2, 3])->comment('1.SuperAdmin, 2.Admin, 3.Asesor');;

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
