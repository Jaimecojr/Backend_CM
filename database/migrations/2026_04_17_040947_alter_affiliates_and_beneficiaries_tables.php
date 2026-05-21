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
        // Arreglar filas con fechas en ceros inteligentemente para no dañar reportes
        // Limpiamos los cumpleaños (bithdate) dañados en ambas tablas volviéndolos NULL
        \Illuminate\Support\Facades\DB::table('affiliates')->where('bithdate', 'like', '0000%')->update(['bithdate' => null]);
        \Illuminate\Support\Facades\DB::table('beneficiaries')->where('bithdate', 'like', '0000%')->update(['bithdate' => null]);

        // En caso hipotético de que la vigencia principal no exista, la iniciamos en una fecha neutra
        \Illuminate\Support\Facades\DB::table('affiliates')->where('validity', 'like', '0000%')->update(['validity' => '2024-01-01']);

        // Copiar la fecha de 'validity' al 'sale_date' roto, respetando reportes
        \Illuminate\Support\Facades\DB::table('affiliates')->where('sale_date', 'like', '0000%')->update(['sale_date' => \Illuminate\Support\Facades\DB::raw('validity')]);

        // Calcular que el fin de vigencia sea un año despegada
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::table('affiliates')->where('validity_end', 'like', '0000%')->update(['validity_end' => \Illuminate\Support\Facades\DB::raw('DATE_ADD(validity, INTERVAL 1 YEAR)')]);
        } else {
            // SQLite compatible date addition
            \Illuminate\Support\Facades\DB::table('affiliates')->where('validity_end', 'like', '0000%')->update(['validity_end' => \Illuminate\Support\Facades\DB::raw("date(validity, '+1 year')")]);
        }

        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('contract_code')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('address')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('company')->nullable()->change();
            
            $table->integer('value_sale')->default(0)->change();
            $table->integer('balance')->default(0)->change();
            $table->integer('comission')->default(0)->change();
            
            $table->enum('payment_commission', ['si', 'no'])->default('no')->change();
            $table->enum('carnet', ['si', 'no'])->default('no')->change();
            
            $table->tinyInteger('state')->default(1)->change();
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('id_card')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('contract_code')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('address')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('company')->nullable(false)->change();
            
            // Revert defaults by removing them (though dropping default natively is $table->integer('value_sale')->change() etc.)
            $table->integer('value_sale')->default(null)->change();
            $table->integer('balance')->default(null)->change();
            $table->integer('comission')->default(null)->change();
            
            $table->enum('payment_commission', ['si', 'no'])->default(null)->change();
            $table->enum('carnet', ['si', 'no'])->default(null)->change();
            
            $table->tinyInteger('state')->default(null)->change();
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('id_card')->nullable(false)->change();
        });
    }
};
