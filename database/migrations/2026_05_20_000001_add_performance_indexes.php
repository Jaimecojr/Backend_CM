<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Índices para médicos — specialty_id y city_id son FK sin índice explícito;
        // state se usa en todos los filtros de listado y endpoints públicos.
        Schema::table('doctors', function (Blueprint $table) {
            $table->index('specialty_id', 'doctors_specialty_id_index');
            $table->index('city_id',      'doctors_city_id_index');
            $table->index('state',        'doctors_state_index');
        });

        // Índices para asesores — city_id y user_id son FK sin índice explícito;
        // state se usa en activeCounselors() y en filtros de listado.
        Schema::table('counselors', function (Blueprint $table) {
            $table->index('city_id',  'counselors_city_id_index');
            $table->index('user_id',  'counselors_user_id_index');
            $table->index('state',    'counselors_state_index');
        });

        // afi_code en citas — usado por el eager load de affiliate y beneficiary.
        // No es FK convencional (apunta a id de affiliates O a id de beneficiaries
        // según el campo type), por eso no se creó automáticamente con foreign().
        Schema::table('appointments', function (Blueprint $table) {
            $table->index('afi_code', 'appointments_afi_code_index');
        });

        // validity_end en afiliados — usado por expiringToday() y el scheduler
        // affiliates:update-expired. Sin este índice ambas queries hacen full scan.
        Schema::table('affiliates', function (Blueprint $table) {
            $table->index('validity_end', 'affiliates_validity_end_index');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropIndex('doctors_specialty_id_index');
            $table->dropIndex('doctors_city_id_index');
            $table->dropIndex('doctors_state_index');
        });

        Schema::table('counselors', function (Blueprint $table) {
            $table->dropIndex('counselors_city_id_index');
            $table->dropIndex('counselors_user_id_index');
            $table->dropIndex('counselors_state_index');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_afi_code_index');
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropIndex('affiliates_validity_end_index');
        });
    }
};
