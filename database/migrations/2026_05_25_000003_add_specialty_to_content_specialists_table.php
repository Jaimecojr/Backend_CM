<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_specialists', function (Blueprint $table) {
            $table->string('specialty')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('content_specialists', function (Blueprint $table) {
            $table->dropColumn('specialty');
        });
    }
};
