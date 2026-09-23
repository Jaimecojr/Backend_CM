<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consolidated index migration for the Reportes module (one migration
     * per module/sprint, not one per index, per project convention).
     */
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            // Reporte 1 (Ventas): primary filter/sort column.
            $table->index('payment_date', 'affiliates_payment_date_index');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Reporte 6 (Carnets No Enviados): date-range filter column.
            $table->index('created_at', 'whatsapp_messages_created_at_index');
        });
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
