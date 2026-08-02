<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->string('question_key');
            $table->json('value')->nullable();
            $table->string('source')->default('user');
            $table->float('confidence')->nullable();
            $table->timestamp('answered_at')->useCurrent();
            $table->boolean('edited_by_user')->default(false);
            $table->boolean('edited_by_ai')->default(false);
            $table->string('validation_state')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'question_key']);
            $table->foreign('run_id')->references('id')->on('interaction_runs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_answers');
    }
};