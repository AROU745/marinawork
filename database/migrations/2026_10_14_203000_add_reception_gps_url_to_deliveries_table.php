<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        if (! Schema::hasColumn('deliveries', 'reception_gps_url')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->string('reception_gps_url', 500)->nullable()->after('reception_place');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('deliveries') && Schema::hasColumn('deliveries', 'reception_gps_url')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropColumn('reception_gps_url');
            });
        }
    }
};
