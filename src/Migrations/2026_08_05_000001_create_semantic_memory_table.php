<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs TitanZero\Engines\Memory\Implementations\SemanticMemoryEngine.
 * Added during the fix pass — see episodic_memory migration note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semantic_memory', function (Blueprint $table) {
            $table->id();
            $table->json('fact');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semantic_memory');
    }
};
