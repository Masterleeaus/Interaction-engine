<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs TitanZero\Engines\Memory\Implementations\EpisodicMemoryEngine.
 * Added during the fix pass — the 80-engine library's Memory domain shipped
 * with no migration for this table, so store()/recall() would fatal with
 * "table not found" on first use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodic_memory', function (Blueprint $table) {
            $table->id();
            $table->json('event');
            $table->timestamp('timestamp')->useCurrent();
            $table->index('timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodic_memory');
    }
};
