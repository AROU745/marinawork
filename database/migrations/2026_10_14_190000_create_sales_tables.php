<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('tickets_count')->default(0);
            $table->unsignedInteger('total_amount')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['user_id', 'business_date']);
        });

        Schema::create('ticket_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('day_closing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('duration_key', 20);
            $table->unsignedInteger('price');
            $table->date('sold_on');
            $table->timestamp('sold_at');
            $table->timestamps();

            $table->index(['user_id', 'sold_on']);
            $table->index(['user_id', 'day_closing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sales');
        Schema::dropIfExists('day_closings');
    }
};
