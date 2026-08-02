<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interaction_cognitive_events', function (Blueprint $table): void {
            $table->uuid('event_id');
            $table->string('event_type', 64);
            $table->string('tenant_id', 128);
            $table->string('team_id', 128)->nullable();
            $table->string('user_id', 128)->nullable();
            $table->string('device_id', 128)->nullable();
            $table->string('subject_type', 128)->nullable();
            $table->string('subject_id', 128)->nullable();
            $table->string('interaction_run_id', 128)->nullable();
            $table->string('wizard_run_id', 128)->nullable();
            $table->json('payload');
            $table->decimal('confidence', 8, 7)->nullable();
            $table->json('evidence')->nullable();
            $table->json('policy_decision')->nullable();
            $table->string('model_version', 128)->nullable();
            $table->uuid('parent_event_id')->nullable();
            $table->uuid('correlation_id');
            $table->string('privacy_class', 32)->default('tenant_private');
            $table->unsignedInteger('sequence')->default(0);
            $table->timestampTz('occurred_at');
            $table->timestampTz('recorded_at');

            $table->primary(['tenant_id', 'event_id']);
            $table->index(['tenant_id', 'correlation_id', 'sequence'], 'ice_tenant_correlation_sequence');
            $table->index(['tenant_id', 'subject_type', 'subject_id', 'occurred_at'], 'ice_tenant_subject_time');
            $table->index(['tenant_id', 'event_type', 'occurred_at'], 'ice_tenant_type_time');
            $table->index(['tenant_id', 'user_id', 'occurred_at'], 'ice_tenant_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_cognitive_events');
    }
};
