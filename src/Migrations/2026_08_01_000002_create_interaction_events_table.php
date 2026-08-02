<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->nullable();
            $table->string('event_type');
            $table->json('data');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['run_id', 'event_type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_events');
    }
};