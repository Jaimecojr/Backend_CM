<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->withRelaxedSqlMode(function () {
            Schema::table('membership_forms', function (Blueprint $table) {
                $table->string('seller')->nullable()->change();
            });
        });
    }

    public function down(): void
    {
        $this->withRelaxedSqlMode(function () {
            Schema::table('membership_forms', function (Blueprint $table) {
                $table->string('seller')->nullable(false)->change();
            });
        });
    }

    /**
     * Existing rows carry a legacy '0000-00-00' `date` value. MySQL revalidates every column
     * against the session's sql_mode while altering this table, so NO_ZERO_DATE (strict mode)
     * would reject the ALTER even though `date` itself isn't the column being changed here.
     * Relaxed for this session/migration only — never touches the stored data or global config.
     * SQLite (used by the test suite) has no sql_mode concept and no such restriction, so it runs
     * the callback unchanged.
     */
    private function withRelaxedSqlMode(callable $callback): void
    {
        if (config('database.default') !== 'mysql') {
            $callback();
            return;
        }

        $originalMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            $callback();
        } finally {
            DB::statement("SET SESSION sql_mode = '{$originalMode}'");
        }
    }
};
