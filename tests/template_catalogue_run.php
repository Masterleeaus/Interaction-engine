<?php

declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'TitanZero\\Engines\\' => $root . '/src/Engines/',
        'TitanZero\\Interaction\\' => $root . '/src/',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $path = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
        return;
    }
});

$tests = [];
$test = static function (string $name, callable $fn) use (&$tests): void { $tests[$name] = $fn; };
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$load = static function () use ($root): array {
    $wizards = new TitanZero\Interaction\Wizard\WizardRegistry();
    $wizards->discover($root . '/wizards');
    $templates = new TitanZero\Interaction\Template\TemplateRegistry();
    $templates->discover($root . '/templates');
    return [$wizards, $templates];
};

$test('catalogue exposes seven ready and three draft templates', function () use ($load, $assert): void {
    [, $templates] = $load();
    $all = $templates->all();
    $assert(count($all) === 10, 'Expected exactly ten curated templates, got ' . count($all));
    $ready = array_filter($all, static fn($template): bool => $template->status === 'ready');
    $draft = array_filter($all, static fn($template): bool => $template->status === 'draft');
    $assert(count($ready) === 7, 'Expected seven ready templates, got ' . count($ready));
    $assert(count($draft) === 3, 'Expected three draft templates, got ' . count($draft));
});

$test('foundation and assurance template identities are stable', function () use ($load, $assert): void {
    [, $templates] = $load();
    $expected = [
        'customer-onboarding',
        'quote-builder',
        'job-creation',
        'job-completion',
        'invoice-creation',
        'incident-response',
        'inspection-corrective-action',
        'field-job-handover',
        'payment-reconciliation',
        'staff-onboarding',
    ];
    foreach ($expected as $id) {
        $assert($templates->has($id), "Missing curated template {$id}");
    }
});

$test('every ready template references a real wizard and matching capability', function () use ($load, $assert): void {
    [$wizards, $templates] = $load();
    foreach ($templates->all() as $template) {
        if ($template->status !== 'ready') {
            continue;
        }
        $assert($wizards->has($template->entryWizard), "Ready template {$template->id} references missing wizard {$template->entryWizard}");
        $wizard = $wizards->get($template->entryWizard);
        $assert(in_array($wizard->capability, $template->capabilities, true), "Template {$template->id} does not declare wizard capability {$wizard->capability}");
    }
});

$test('compatibility checker fails closed for missing host capabilities', function () use ($load, $assert): void {
    [$wizards, $templates] = $load();
    $checker = new TitanZero\Interaction\Template\TemplateCompatibilityChecker($wizards);
    $report = $checker->check($templates->get('incident-response'), []);
    $assert(!$report->compatible, 'Incident template must be incompatible without its WorkCore capability.');
    $assert(in_array('assurance.incidents.report', $report->missingCapabilities, true), 'Missing capability was not reported.');
});

$test('compatibility checker accepts a complete ready host mapping', function () use ($load, $assert): void {
    [$wizards, $templates] = $load();
    $checker = new TitanZero\Interaction\Template\TemplateCompatibilityChecker($wizards);
    $template = $templates->get('inspection-corrective-action');
    $report = $checker->check($template, ['assurance.inspections.complete']);
    $assert($report->compatible, 'Inspection template should be compatible when its wizard and host capability exist.');
    $assert($report->missingCapabilities === [], 'Compatible report must not contain missing capabilities.');
    $assert($report->missingEntryWizard === false, 'Compatible report must not report a missing wizard.');
});

$test('Laravel integration exposes templates through provider API and CLI surfaces', function () use ($root, $assert): void {
    $provider = (string) file_get_contents($root . '/src/Providers/InteractionServiceProvider.php');
    $routes = (string) file_get_contents($root . '/routes/api.php');
    $seed = (string) file_get_contents($root . '/src/Commands/SeedCommand.php');
    $assert(is_file($root . '/src/Http/Controllers/TemplateController.php'), 'Template API controller is missing.');
    $assert(is_file($root . '/src/Commands/ListTemplatesCommand.php'), 'Template CLI command is missing.');
    $assert(str_contains($provider, 'TemplateRegistry::class'), 'Template registry is not registered with Laravel.');
    $assert(str_contains($provider, 'TemplateCompatibilityChecker::class'), 'Compatibility checker is not registered with Laravel.');
    $assert(str_contains($provider, "'/../../templates'"), 'Bundled templates are not publishable.');
    $assert(str_contains($routes, "Route::get('/templates'"), 'Authenticated template catalogue API route is missing.');
    $assert(str_contains($seed, "'/templates'"), 'interaction:seed does not install templates.');
});

$test('release documentation and compatibility report stay machine-verifiable', function () use ($root, $assert): void {
    $reportPath = $root . '/reports/workcore-compatibility.json';
    $assert(is_file($reportPath), 'WorkCore compatibility report is missing.');
    $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
    $assert(count($report['templates'] ?? []) === 10, 'Compatibility report must cover all ten templates.');
    $indexed = [];
    foreach ($report['templates'] as $row) {
        $indexed[$row['id']] = $row;
    }
    $assert(($indexed['incident-response']['engine_ready'] ?? false) === true, 'Incident Response should be engine-ready.');
    $assert(($indexed['incident-response']['connected_host_verified'] ?? true) === false, 'Incident Response must not claim connected-host verification.');
    $readme = (string) file_get_contents($root . '/README.md');
    $assert(str_contains($readme, 'GET  /templates'), 'README does not document template discovery.');
    $assert(str_contains($readme, 'Incident Response'), 'README does not document assurance workflows.');
    $verify = (string) file_get_contents($root . '/bin/verify.php');
    $assert(str_contains($verify, 'template_catalogue_run.php'), 'Main verifier does not run the template catalogue suite.');
    $assert(str_contains($verify, 'assurance_workflows_run.php'), 'Main verifier does not run the assurance workflow suite.');
});

$test('template registry rejects duplicate identifiers', function () use ($assert): void {
    $registry = new TitanZero\Interaction\Template\TemplateRegistry();
    $definition = [
        'id' => 'duplicate-test',
        'version' => '1.0.0',
        'name' => 'Duplicate Test',
        'status' => 'draft',
        'category' => 'test',
        'entry_wizard' => 'future_wizard_v1',
    ];
    $registry->register($definition);
    try {
        $registry->register($definition);
        $assert(false, 'Duplicate template identifier should be rejected.');
    } catch (RuntimeException $error) {
        $assert(str_contains($error->getMessage(), 'already registered'), 'Duplicate rejection was not actionable.');
    }
});

$passed = 0;
$failed = 0;
foreach ($tests as $name => $fn) {
    try {
        $fn();
        echo "PASS {$name}\n";
        $passed++;
    } catch (Throwable $error) {
        echo "FAIL {$name}: {$error->getMessage()}\n";
        $failed++;
    }
}

echo "\n{$passed}/" . count($tests) . " template catalogue tests passed\n";
exit($failed === 0 ? 0 : 1);
