<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs TitanZero\Engines\Executive\Implementations\GovernanceEngine.
 * Added during the fix pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_logs', function (Blueprint $table) {
            $table->id();
            $table->json('event');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_logs');
    }
};
