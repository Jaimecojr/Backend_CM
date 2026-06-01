<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega índices a la tabla affiliates para mejorar el rendimiento:
     * - Índices en llaves foráneas (evitan full table scans en JOINs)
     * - Índice en stade (filtro más usado en el listado)
     * - Índice compuesto (stade, id) para la query: WHERE stade=? ORDER BY id DESC
     */
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            // Índices en llaves foráneas
            $table->index('city_id',       'affiliates_city_id_index');
            $table->index('counselor_id',  'affiliates_counselor_id_index');
            $table->index('agreement_id',  'affiliates_agreement_id_index');
            $table->index('user_id',       'affiliates_user_id_index');

            // Índice simple en stade
            $table->index('stade', 'affiliates_stade_index');

            // Índice compuesto para: WHERE stade = ? ORDER BY id DESC
            $table->index(['stade', 'id'], 'affiliates_stade_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropIndex('affiliates_city_id_index');
            $table->dropIndex('affiliates_counselor_id_index');
            $table->dropIndex('affiliates_agreement_id_index');
            $table->dropIndex('affiliates_user_id_index');
            $table->dropIndex('affiliates_stade_index');
            $table->dropIndex('affiliates_stade_id_index');
        });
    }
};
