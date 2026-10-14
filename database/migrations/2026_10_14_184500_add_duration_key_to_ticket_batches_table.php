<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_batches', function (Blueprint $table) {
            $table->string('duration_key', 20)->nullable()->after('original_filename')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_batches', function (Blueprint $table) {
            $table->dropColumn('duration_key');
        });
    }
};
