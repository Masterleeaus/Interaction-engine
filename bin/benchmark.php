<?php

declare(strict_types=1);

use TitanZero\Engines\Planning\Implementations\ExecutionEngine;
use TitanZero\Interaction\Authority\AuthorityLevel;
use TitanZero\Interaction\Authority\CapabilityPolicy;
use TitanZero\Interaction\Compiler\ConditionRegistry;
use TitanZero\Interaction\Compiler\FragmentResolver;
use TitanZero\Interaction\Compiler\InteractionDefinitionCompiler;
use TitanZero\Interaction\Compiler\SchemaValidator;
use TitanZero\Interaction\Policy\PolicyEngine;

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'TitanZero\\Engines\\' => [$root . '/src/Engines/'],
        'TitanZero\\Interaction\\' => [$root . '/packages/policy-engine/src/', $root . '/src/'],
    ];
    foreach ($prefixes as $prefix => $directories) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        foreach ($directories as $directory) {
            $file = $directory . $relative . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
});

$samples = 400;
$definitions = [];
foreach (glob($root . '/interactions/*.json') ?: [] as $file) {
    $definition = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (is_array($definition)) {
        $definitions[] = $definition;
    }
}
if ($definitions === []) {
    fwrite(STDERR, "No interaction definitions were available to compile.\n");
    exit(2);
}

$compiler = new InteractionDefinitionCompiler(
    new SchemaValidator(),
    new FragmentResolver($root . '/interactions/fragments'),
    new ConditionRegistry(),
);
$compileTimes = [];
for ($iteration = 0; $iteration < $samples; $iteration++) {
    foreach ($definitions as $definition) {
        $start = hrtime(true);
        $compiler->compile($definition);
        $compileTimes[] = (hrtime(true) - $start) / 1_000_000;
    }
}

$policy = new PolicyEngine();
$policy->registerCapabilityPolicy(new CapabilityPolicy(
    'benchmark.command', AuthorityLevel::DelegatedAutonomous,
    delegatedScopes: ['benchmark:execute'],
));
$execution = new ExecutionEngine($policy);
$handlerCalls = 0;
$execution->registerHandler('benchmark.command', static function () use (&$handlerCalls): array {
    $handlerCalls++;
    return ['accepted' => true];
});
$parameters = [
    'value' => 'fixed-benchmark-payload',
    'idempotency_key' => 'benchmark-command-once',
    '_context' => ['tenant_id' => 'benchmark-tenant', 'actor_type' => 'agent', 'delegated_scopes' => ['benchmark:execute']],
];
$initial = $execution->execute('benchmark.command', $parameters);
if (($initial['status'] ?? null) !== 'success') {
    fwrite(STDERR, "The benchmark command was not accepted: " . ($initial['status'] ?? 'unknown') . "\n");
    exit(2);
}
$duplicateTimes = [];
for ($iteration = 0; $iteration < $samples; $iteration++) {
    $start = hrtime(true);
    $result = $execution->execute('benchmark.command', $parameters);
    $duplicateTimes[] = (hrtime(true) - $start) / 1_000_000;
    if (($result['status'] ?? null) !== 'duplicate') {
        fwrite(STDERR, "Duplicate command was not safely deduplicated.\n");
        exit(2);
    }
}
if ($handlerCalls !== 1) {
    fwrite(STDERR, "Duplicate benchmark unexpectedly executed the handler more than once.\n");
    exit(2);
}

$report = [
    'suite' => 'interaction-engine-performance-php',
    'date_utc' => gmdate(DATE_ATOM),
    'commit' => trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: 'unavailable',
    'php_version' => PHP_VERSION,
    'os' => PHP_OS_FAMILY,
    'definition_count' => count($definitions),
    'compile_samples' => count($compileTimes),
    'compile_ms' => percentiles($compileTimes),
    'duplicate_samples' => count($duplicateTimes),
    'duplicate_ms' => percentiles($duplicateTimes),
    'duplicate_handler_executions' => $handlerCalls,
    'notes' => [
        'Definition compile measures schema validation, fragment resolution, condition expansion, and DTO creation on pre-parsed JSON; compile cache is excluded.',
        'Duplicate handling measures a policy-approved repeated command through ExecutionEngine after its first successful execution.',
        'Times are local process wall-clock timings and are not a production capacity claim.',
    ],
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
$outputPath = $argv[1] ?? null;
if (is_string($outputPath) && $outputPath !== '') {
    file_put_contents($outputPath, $json);
}
echo $json;

function percentiles(array $values): array
{
    sort($values, SORT_NUMERIC);
    $at = static fn(float $percentile): float => $values[(int) max(0, ceil(count($values) * $percentile) - 1)];
    return [
        'p50' => round($at(0.50), 6),
        'p95' => round($at(0.95), 6),
        'min' => round($values[0], 6),
        'max' => round($values[count($values) - 1], 6),
        'unit' => 'ms',
    ];
}
