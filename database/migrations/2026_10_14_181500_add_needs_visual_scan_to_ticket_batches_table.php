<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_batches', function (Blueprint $table) {
            $table->boolean('needs_visual_scan')->default(false)->after('current_index');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_batches', function (Blueprint $table) {
            $table->dropColumn('needs_visual_scan');
        });
    }
};
