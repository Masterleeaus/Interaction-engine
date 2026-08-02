<?php

declare(strict_types=1);

use TitanZero\Interaction\Domain\Adapters\WorkCoreAdapter;

return [
    'definitions_path' => env('INTERACTION_DEFINITIONS_PATH', base_path('interactions')),
    'fragments_path' => env('INTERACTION_FRAGMENTS_PATH', base_path('interactions/fragments')),
    'cache_ttl' => (int) env('INTERACTION_CACHE_TTL', 3600),
    'renderer' => env('INTERACTION_RENDERER', 'blade'),
    'view' => env('INTERACTION_VIEW', 'interaction::run'),

    'adapter' => env('INTERACTION_DOMAIN_ADAPTER', WorkCoreAdapter::class),
    'adapter_config' => [],

    'template' => [
        'definitions_path' => env('INTERACTION_TEMPLATE_PATH', base_path('templates')),
    ],

    'wizard' => [
        'definitions_path' => env('INTERACTION_WIZARD_PATH', base_path('wizards')),
        'outbox_secret' => env('INTERACTION_OUTBOX_SECRET', env('APP_KEY', 'change-me')),
        'default_renderer' => env('INTERACTION_WIZARD_RENDERER', 'hybrid'),
        'approval_threshold' => (float) env('INTERACTION_APPROVAL_THRESHOLD', 1000),
        'session_ttl' => (int) env('INTERACTION_WIZARD_SESSION_TTL', 86400),
    ],

    'local_intelligence' => [
        'enabled' => (bool) env('INTERACTION_LOCAL_INTELLIGENCE', true),
        'minimum_confidence' => (float) env('INTERACTION_LOCAL_MIN_CONFIDENCE', 0.65),
        'memory_limit' => (int) env('INTERACTION_LOCAL_MEMORY_LIMIT', 1000),
        'share_model_weights' => (bool) env('INTERACTION_SHARE_MODEL_WEIGHTS', false),
        'share_aggregate_counts' => (bool) env('INTERACTION_SHARE_AGGREGATES', false),
    ],

    'authority' => [
        'approval_secret' => env('INTERACTION_APPROVAL_SECRET', env('APP_KEY', 'change-this-approval-secret')),
        'fresh_authentication_seconds' => (int) env('INTERACTION_FRESH_AUTH_SECONDS', 300),
        'default_deny' => true,
    ],

    'offline' => [
        'enabled' => (bool) env('INTERACTION_OFFLINE_ENABLED', true),
        'health_check_url' => env('INTERACTION_HEALTH_CHECK_URL', config('app.url')),
        'sync_batch_size' => (int) env('INTERACTION_SYNC_BATCH_SIZE', 100),
        'max_attempts' => (int) env('INTERACTION_SYNC_MAX_ATTEMPTS', 5),
    ],

    'ai' => [
        'enabled' => (bool) env('INTERACTION_AI_ENABLED', false),
        'provider' => env('INTERACTION_AI_PROVIDER', 'openai'),
        'api_key' => env('INTERACTION_AI_API_KEY'),
        'model' => env('INTERACTION_AI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => (int) env('INTERACTION_AI_MAX_TOKENS', 150),
        'temperature' => (float) env('INTERACTION_AI_TEMPERATURE', 0.3),
    ],
];
