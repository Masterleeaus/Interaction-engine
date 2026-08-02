<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs TitanZero\Engines\Learning\Implementations\PreferenceLearningEngine.
 * Added during the fix pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
