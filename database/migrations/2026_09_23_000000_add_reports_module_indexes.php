<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consolidated index migration for the Reportes module (one migration
     * per module/sprint, not one per index, per project convention).
     */
    public function up(): void
    {
        $this->withLegacyZeroDatesAllowed(function () {
            Schema::table('affiliates', function (Blueprint $table) {
                // Reporte 1 (Ventas): primary filter/sort column.
                $table->index('payment_date', 'affiliates_payment_date_index');
            });

            Schema::table('whatsapp_messages', function (Blueprint $table) {
                // Reporte 6 (Carnets No Enviados): date-range filter column.
                $table->index('created_at', 'whatsapp_messages_created_at_index');
            });
        });
    }

    /**
     * Rows imported from the legacy system hold '0000-00-00' dates (e.g. affiliates.bithdate). Under
     * Laravel's strict sql_mode, MySQL re-validates every row when it alters the table and rejects
     * them, so the zero-date flags are relaxed for this session only and restored afterwards.
     * The data itself is left untouched.
     */
    private function withLegacyZeroDatesAllowed(callable $callback): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $callback();

            return;
        }

        $originalMode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        $relaxedMode = implode(',', array_diff(explode(',', $originalMode), ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE']));

        DB::statement('SET SESSION sql_mode = ?', [$relaxedMode]);

        try {
            $callback();
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$originalMode]);
        }
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropIndex('affiliates_payment_date_index');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('whatsapp_messages_created_at_index');
        });
    }
};
