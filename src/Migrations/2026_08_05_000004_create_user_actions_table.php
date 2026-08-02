<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs TitanZero\Engines\Learning\Implementations\BehaviourLearningEngine.
 * Added during the fix pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_actions');
    }
};
