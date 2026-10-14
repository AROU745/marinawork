<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('code')->nullable();
            $table->string('raw_text');
            $table->boolean('revealed')->default(false);
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_batch_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
