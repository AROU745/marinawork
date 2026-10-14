<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('code');
            $table->string('ticket_no')->nullable()->after('ip_address');
            $table->string('duration')->nullable()->after('ticket_no');
            $table->string('price')->nullable()->after('duration');
            $table->string('image_path')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'ticket_no', 'duration', 'price', 'image_path']);
        });
    }
};
