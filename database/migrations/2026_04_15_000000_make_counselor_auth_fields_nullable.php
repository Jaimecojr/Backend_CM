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
        // Disable strict mode for this session
        \Illuminate\Support\Facades\DB::statement("SET SESSION sql_mode = ''");

        // Limpiamos los "0000-00-00" que rompen el alter table en MySQL moderno
        \Illuminate\Support\Facades\DB::statement("UPDATE counselors SET date_admission = NULL WHERE date_admission = '0000-00-00'");

        Schema::table('counselors', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counselors', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
