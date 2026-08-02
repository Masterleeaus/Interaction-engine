<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queued_commands', function (Blueprint $table): void {
            $table->id();
            $table->string('capability');
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'synced', 'failed'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->index(['status', 'created_at']);
            $table->index('capability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queued_commands');
    }
};
