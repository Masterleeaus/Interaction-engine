<?php

declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'TitanZero\\Engines\\' => $root . '/src/Engines/',
        'TitanZero\\Interaction\\' => [$root . '/packages/policy-engine/src/', $root . '/src/'],
    ];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            foreach ((array) $base as $directory) {
                $path = $directory . str_replace('\\', '/', $relative) . '.php';
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        }
    }
});

$tests = [];
$test = static function (string $name, callable $fn) use (&$tests): void { $tests[$name] = $fn; };
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) { throw new RuntimeException($message); }
};

$test('all PHP files pass syntax lint', function () use ($root, $assert): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && !preg_match('#[/\\\\](?:\\.git|vendor|node_modules|dist|coverage)[/\\\\]#', $file->getPathname())) {
            $cmd = 'php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
            exec($cmd, $out, $code);
            $assert($code === 0, $file->getPathname() . ': ' . implode("\n", $out));
            $out = [];
        }
    }
});

$test('source tree has no patch artifact filenames', function () use ($root, $assert): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && !preg_match('#[/\\\\](?:\\.git|vendor|node_modules|dist|coverage)[/\\\\]#', $file->getPathname())) {
            $name = $file->getFilename();
            $assert(!preg_match('/\((updated|example|add new bindings|updated with resolver)\)/i', $name), 'Patch artifact remains: ' . $file->getPathname());
        }
    }
});

$test('SHA-256 manifest matches maintained repository files', function () use ($root, $assert): void {
    $manifest = json_decode((string) file_get_contents($root . '/files.sha256.json'), true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($manifest) && $manifest !== [], 'SHA-256 manifest is empty or invalid.');
    foreach ($manifest as $relative => $expected) {
        $path = $root . '/' . $relative;
        $assert(is_file($path), "Manifest file is missing: {$relative}");
        $actual = hash_file('sha256', $path);
        $assert(is_string($actual) && hash_equals((string) $expected, $actual), "Manifest hash is stale: {$relative}");
    }
});


$test('registered operational commands and support services contain executable PHP classes', function () use ($root, $assert): void {
    $files = [
        'src/Commands/InstallCommand.php',
        'src/Commands/HealthCheckCommand.php',
        'src/Commands/SeedCommand.php',
        'src/Monitoring/HealthCheck.php',
        'src/Error/ErrorHandler.php',
    ];
    foreach ($files as $relative) {
        $contents = (string) file_get_contents($root . '/' . $relative);
        $assert(str_starts_with(ltrim($contents), '<?php'), "{$relative} is not executable PHP");
        $assert(preg_match('/\bclass\s+\w+/', $contents) === 1, "{$relative} does not define a class");
    }
    $migration = (string) file_get_contents($root . '/database/migrations/2026_08_03_000000_create_long_term_memory_table.php');
    $assert(str_contains($migration, 'Schema::create'), 'Long-term memory migration is still a placeholder');
    $assert(str_contains($migration, "'local_intelligence_memories'"), 'Long-term memory table name is missing');
});

$test('all 80 engine pairs exist and implement their contracts', function () use ($root, $assert): void {
    $contracts = glob($root . '/src/Engines/*/Contracts/*Interface.php') ?: [];
    $implementations = glob($root . '/src/Engines/*/Implementations/*.php') ?: [];
    $assert(count($contracts) === 80, 'Expected 80 contracts, got ' . count($contracts));
    $assert(count($implementations) === 80, 'Expected 80 implementations, got ' . count($implementations));

    foreach ($contracts as $contractFile) {
        preg_match('#/Engines/([^/]+)/Contracts/([^/]+)Interface\.php$#', $contractFile, $m);
        $domain = $m[1];
        $engine = $m[2];
        $contract = "TitanZero\\Engines\\{$domain}\\Contracts\\{$engine}Interface";
        $implementation = "TitanZero\\Engines\\{$domain}\\Implementations\\{$engine}";
        $assert(interface_exists($contract), "Missing interface {$contract}");
        $assert(class_exists($implementation), "Missing implementation {$implementation}");
        $assert(is_subclass_of($implementation, $contract), "{$implementation} does not implement {$contract}");
    }

    $inventory = (string) file_get_contents($root . '/docs/ENGINE_LIBRARY_80.md');
    preg_match_all('/^\| `([^`]+)` \| (Implemented|Partial) \|/m', $inventory, $matches);
    $listed = $matches[1] ?? [];
    sort($listed);
    $implementedNames = array_map(static fn(string $path): string => basename($path, 'Interface.php'), $contracts);
    sort($implementedNames);
    $assert(count($listed) === 80 && count(array_unique($listed)) === 80, 'Engine status inventory must list exactly 80 unique entries.');
    $assert($listed === $implementedNames, 'Engine inventory must map every implementation and no stale engine names.');
    $assert(!str_contains(strtolower($inventory), 'interface only'), 'A working contract/implementation pair should not be labeled interface-only.');
});


$test('all JSON resources are valid and the five foundation wizards load', function () use ($root, $assert): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'json' && !preg_match('#[/\\\\](?:\\.git|vendor|node_modules|dist|coverage)[/\\\\]#', $file->getPathname())) {
            try {
                json_decode((string) file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new RuntimeException($file->getPathname() . ': ' . $error->getMessage());
            }
        }
    }

    $registry = new TitanZero\Interaction\Wizard\WizardRegistry();
    $count = $registry->discover($root . '/wizards');
    $expected = ['new_customer_v1', 'create_quote_v1', 'create_job_v1', 'complete_job_v1', 'create_invoice_v1'];
    $assert($count >= count($expected), 'Expected at least five wizard definitions');
    foreach ($expected as $wizardId) {
        $assert($registry->has($wizardId), "Missing foundation wizard {$wizardId}");
        $assert($registry->get($wizardId)->stepCount() >= 3, "Wizard {$wizardId} is too shallow");
    }
});


$test('TypeScript compiler config uses maintained Node module resolution', function () use ($root, $assert): void {
    $config = json_decode((string) file_get_contents($root . '/tsconfig.json'), true, 512, JSON_THROW_ON_ERROR);
    $resolution = strtolower((string) ($config['compilerOptions']['moduleResolution'] ?? ''));
    $module = strtolower((string) ($config['compilerOptions']['module'] ?? ''));
    $assert($resolution === 'node16', 'TypeScript moduleResolution must be Node16, not the removed Node/Node10 alias.');
    $assert($module === 'node16', 'TypeScript module must match Node16 resolution.');
});

$test('WebCrypto inputs use concrete ArrayBuffer values for current TypeScript DOM types', function () use ($root, $assert): void {
    $source = (string) file_get_contents($root . '/resources/ts/offline/crypto.ts');
    $assert(str_contains($source, 'function toArrayBuffer(bytes: Uint8Array): ArrayBuffer'), 'WebCrypto byte conversion helper is missing.');
    $assert(str_contains($source, "iv: toArrayBuffer(iv)"), 'Encryption IV must use a concrete ArrayBuffer.');
    $assert(str_contains($source, "iv: toArrayBuffer(fromBase64(payload.iv))"), 'Decryption IV must use a concrete ArrayBuffer.');
    $assert(str_contains($source, "toArrayBuffer(fromBase64(payload.ciphertext))"), 'Ciphertext must use a concrete ArrayBuffer.');
    $assert(str_contains($source, "toArrayBuffer(encoder.encode(secret))"), 'Digest input must use a concrete ArrayBuffer.');
});

$test('interaction definitions satisfy the executable schema contract', function () use ($root, $assert): void {
    $validator = new TitanZero\Interaction\Compiler\SchemaValidator();
    $compiler = new TitanZero\Interaction\Compiler\InteractionDefinitionCompiler(
        $validator,
        new TitanZero\Interaction\Compiler\FragmentResolver($root . '/interactions/fragments'),
        new TitanZero\Interaction\Compiler\ConditionRegistry(),
    );
    foreach (glob($root . '/interactions/*.json') ?: [] as $file) {
        $definition = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $validator->validate($definition);
        $compiled = $compiler->compile($definition);
        $assert($compiled->id === $definition['id'] && $compiled->capability === $definition['capability'], 'Compiled definition lost its identity or capability.');
    }
    try {
        $validator->validate(['id' => 'invalid']);
        $assert(false, 'Invalid definition should be rejected');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('universal wizard validates and completes into an offline command', function () use ($assert): void {
    $registry = new TitanZero\Interaction\Wizard\WizardRegistry();
    $registry->register([
        'id' => 'test_quote', 'version' => '1.0.0', 'name' => 'Test Quote',
        'capability' => 'quotes.create',
        'steps' => [
            ['id' => 'customer', 'fields' => [['id' => 'customer_id', 'required' => true]]],
            ['id' => 'pricing', 'fields' => [['id' => 'total', 'type' => 'number', 'required' => true, 'min' => 0]]],
        ],
    ]);
    $outbox = new TitanZero\Interaction\Wizard\Offline\LocalCommandOutbox('test-secret');
    $engine = new TitanZero\Interaction\Wizard\UniversalWizardEngine(
        $registry,
        new TitanZero\Interaction\Wizard\Validation\WizardValidationEngine(),
        new TitanZero\Interaction\Wizard\Guidance\LocalGuidanceProvider(),
        new TitanZero\Interaction\Wizard\Command\CommandMapper(),
        $outbox,
    );
    $session = $engine->start('test_quote', ['tenant_id' => 't1', 'user_id' => 7, 'device_id' => 'd1']);
    $invalid = $engine->submitStep($session, []);
    $assert($invalid->errors !== [], 'Required field should fail');
    $session = $engine->submitStep($session, ['customer_id' => 42])->session;
    $complete = $engine->submitStep($session, ['total' => 550.0]);
    $assert($complete->complete, 'Wizard should complete');
    $assert(count($outbox->pending()) === 1, 'Command should be queued');
    $assert($outbox->verify($outbox->pending()[0]), 'Queued command signature should verify');
});


$test('online wizard completion dispatches its WorkCore command instead of losing it in memory', function () use ($assert): void {
    $registry = new TitanZero\Interaction\Wizard\WizardRegistry();
    $registry->register([
        'id' => 'online_job', 'name' => 'Online Job', 'capability' => 'jobs.create',
        'steps' => [['id' => 'job', 'fields' => [['id' => 'customer_id', 'required' => true]]]],
    ]);
    $bus = new class implements TitanZero\Interaction\Contracts\CommandBusInterface {
        public array $dispatched = [];
        public function registerHandler(string $capability, callable $handler): void {}
        public function hasHandler(string $capability): bool { return true; }
        public function dispatch(string $capability, array $payload): void { $this->dispatched[] = [$capability, $payload]; }
    };
    $outbox = new TitanZero\Interaction\Wizard\Offline\LocalCommandOutbox('test-secret');
    $engine = new TitanZero\Interaction\Wizard\UniversalWizardEngine(
        $registry,
        new TitanZero\Interaction\Wizard\Validation\WizardValidationEngine(),
        new TitanZero\Interaction\Wizard\Guidance\LocalGuidanceProvider(),
        new TitanZero\Interaction\Wizard\Command\CommandMapper(),
        $outbox,
        $bus,
    );
    $result = $engine->submitStep($engine->start('online_job'), ['customer_id' => 'c1']);
    $assert($result->complete, 'Wizard did not complete');
    $assert(count($bus->dispatched) === 1, 'Online command was not dispatched');
    $assert(($bus->dispatched[0][0] ?? null) === 'jobs.create', 'Wrong capability dispatched');
    $assert($outbox->pending() === [], 'Online command should not remain in the local outbox');
});

$test('decision tree chooses the strongest matching branch', function () use ($assert): void {
    $engine = new TitanZero\Interaction\LocalIntelligence\Decision\DecisionTreeEngine();
    $result = $engine->evaluate([
        'branches' => [
            ['id' => 'new', 'base_weight' => 0.5, 'conditions' => ['customer_exists' => false], 'conclusion' => 'create_customer'],
            ['id' => 'repeat', 'base_weight' => 0.8, 'conditions' => ['customer_exists' => true], 'conclusion' => 'prefill_quote'],
        ],
    ], ['customer_exists' => true]);
    $assert($result['conclusion'] === 'prefill_quote', 'Wrong branch selected');
    $assert($result['confidence'] >= 0.8, 'Confidence too low');
});

$test('local language engine extracts business intent and entities', function () use ($assert): void {
    $engine = new TitanZero\Interaction\LocalIntelligence\Language\LocalLanguageEngine();
    $result = $engine->understand('Book Jenny for her regular clean next Thursday morning.');
    $assert($result['intent'] === 'schedule_job', 'Expected schedule_job intent');
    $assert(($result['entities']['customer'] ?? null) === 'Jenny', 'Expected customer Jenny');
    $assert(isset($result['entities']['relative_date']), 'Expected relative date');
});

$test('behavioral memory predicts a repeated next action', function () use ($assert): void {
    $memory = new TitanZero\Interaction\LocalIntelligence\Memory\BehavioralMemory();
    foreach ([['complete_job','create_invoice'], ['complete_job','create_invoice'], ['complete_job','record_materials']] as $sequence) {
        foreach ($sequence as $action) { $memory->recordAction(1, $action, []); }
        $memory->resetSequence(1);
    }
    $prediction = $memory->predictNextAction(1, 'complete_job');
    $assert($prediction['action'] === 'create_invoice', 'Expected create_invoice prediction');
    $assert($prediction['confidence'] > 0.6, 'Expected majority confidence');
});

$test('local brain returns a structured offline recommendation', function () use ($assert): void {
    $brain = TitanZero\Interaction\LocalIntelligence\LocalBrain::createDefault();
    $result = $brain->process('Create a quote for Jenny for carpet cleaning', ['user_id' => 1]);
    $assert(isset($result['perception'], $result['decision'], $result['suggestions']), 'Local brain response incomplete');
    $assert(($result['perception']['intent'] ?? '') === 'create_quote', 'Expected quote intent');
    $assert(($result['mode'] ?? '') === 'offline', 'Expected offline mode');
});




$test('wizard sessions survive storage round trips and render for API clients', function () use ($assert): void {
    $definition = TitanZero\Interaction\Wizard\WizardDefinition::fromArray([
        'id' => 'round_trip',
        'name' => 'Round Trip',
        'capability' => 'test.run',
        'steps' => [
            ['id' => 'first', 'title' => 'First', 'fields' => [['id' => 'value', 'required' => true]]],
            ['id' => 'second', 'title' => 'Second', 'fields' => [['id' => 'confirm', 'required' => true]]],
        ],
    ]);
    $session = new TitanZero\Interaction\Wizard\WizardSession(
        id: 'session-1',
        definition: $definition,
        stepIndex: 1,
        data: ['value' => 'saved'],
        context: ['tenant_id' => 't1'],
        history: [['step_id' => 'first']],
    );
    $store = new TitanZero\Interaction\Wizard\Storage\InMemoryWizardSessionStore();
    $store->put($session);
    $loaded = $store->get('session-1');
    $assert($loaded instanceof TitanZero\Interaction\Wizard\WizardSession, 'Session was not restored');
    $assert($loaded->definition->id === 'round_trip', 'Definition was not restored');
    $assert($loaded->stepIndex === 1, 'Step index was not restored');
    $assert(($loaded->data['value'] ?? null) === 'saved', 'Session data was not restored');

    $rendered = (new TitanZero\Interaction\Wizard\Renderer\HybridRenderer())->render($loaded);
    $assert(($rendered['view_model']['session_id'] ?? null) === 'session-1', 'API view model missing session ID');
    $assert(($rendered['view_model']['step']['id'] ?? null) === 'second', 'API view model missing current step');
});

$test('foundation wizard capabilities are registered against the WorkCore boundary', function () use ($assert): void {
    $adapter = new class implements TitanZero\Interaction\Contracts\DomainAdapterInterface {
        public array $calls = [];
        public function createCustomer(array $data): mixed { $this->calls[] = ['crm.customer.create', $data]; return $data; }
        public function createQuote(array $data): mixed { $this->calls[] = ['quotes.create', $data]; return $data; }
        public function createJob(array $data): mixed { $this->calls[] = ['jobs.create', $data]; return $data; }
        public function completeJob(array $data): mixed { $this->calls[] = ['jobs.complete', $data]; return $data; }
        public function createInvoice(array $data): mixed { $this->calls[] = ['finance.invoice.create', $data]; return $data; }
        public function recordPayment(array $data): mixed { $this->calls[] = ['finance.payment.record', $data]; return $data; }
    };
    $registry = new TitanZero\Interaction\Registry\CapabilityRegistry($adapter);
    foreach (['crm.customer.create', 'quotes.create', 'jobs.create', 'jobs.complete', 'finance.invoice.create'] as $capability) {
        $assert($registry->has($capability), "Capability {$capability} is not registered");
    }
    ($registry->getHandler('jobs.complete'))(['job_id' => 'job-1']);
    $assert(($adapter->calls[0][0] ?? null) === 'jobs.complete', 'Complete job did not reach adapter');
});

$test('WorkCore adapter maps foundation wizard payloads without losing operational fields', function () use ($assert): void {
    $captured = [];
    $service = static function (string $entity) use (&$captured): callable {
        return static function (array $attributes) use (&$captured, $entity): array {
            $captured[$entity] = $attributes;
            return $attributes;
        };
    };
    $adapter = new TitanZero\Interaction\Domain\Adapters\WorkCoreAdapter([
        'services' => [
            'customer' => $service('customer'),
            'quote' => $service('quote'),
            'job' => $service('job'),
            'job_completion' => $service('job_completion'),
            'invoice' => $service('invoice'),
        ],
    ]);
    $adapter->createCustomer(['display_name' => 'Example Co', 'street' => '1 Main St', 'suburb' => 'Sydney', 'state' => 'NSW', 'postcode' => '2000', 'email' => 'x@example.test', 'phone' => '0400000000']);
    $adapter->createQuote(['customer_id' => 'c1', 'service_id' => 's1', 'quantity' => 2, 'site_address' => '1 Main St', 'subtotal' => 500, 'discount' => 50, 'total' => 450]);
    $adapter->createJob(['customer_id' => 'c1', 'service_id' => 's1', 'address' => '1 Main St', 'scheduled_start' => '2026-08-01T09:00:00+10:00', 'scheduled_end' => '2026-08-01T11:00:00+10:00', 'worker_ids' => ['w1'], 'price' => 450]);
    $adapter->completeJob(['job_id' => 'j1', 'completed_at' => '2026-08-01T11:00:00+10:00', 'actual_duration_minutes' => 120, 'damage_reported' => false]);
    $adapter->createInvoice(['customer_id' => 'c1', 'job_id' => 'j1', 'line_items' => [['description' => 'Cleaning', 'amount' => 450]], 'subtotal' => 450, 'tax' => 45, 'total' => 495, 'due_date' => '2026-08-15', 'payment_method' => 'payid']);

    $assert(($captured['customer']['name'] ?? null) === 'Example Co', 'Customer name mapping failed');
    $assert(($captured['customer']['address_line1'] ?? null) === '1 Main St', 'Customer address mapping failed');
    $assert(($captured['quote']['services'][0]['service_id'] ?? null) === 's1', 'Quote service mapping failed');
    $assert(($captured['quote']['site_address'] ?? null) === '1 Main St', 'Quote site mapping failed');
    $assert(($captured['job']['scheduled_start'] ?? null) === '2026-08-01T09:00:00+10:00', 'Job schedule mapping failed');
    $assert(($captured['job']['worker_ids'][0] ?? null) === 'w1', 'Job worker mapping failed');
    $assert(($captured['job_completion']['job_id'] ?? null) === 'j1', 'Job completion mapping failed');
    $assert(($captured['invoice']['total'] ?? null) === 495, 'Invoice total mapping failed');
    $assert(($captured['invoice']['payment_method'] ?? null) === 'payid', 'Invoice payment method mapping failed');
});

$test('critical engine implementations perform deterministic work', function () use ($assert): void {
    $reasoning = new TitanZero\Engines\Cognitive\Implementations\ReasoningEngine();
    $deduction = $reasoning->deduce([
        ['if' => ['customer_exists' => true], 'then' => ['can_quote' => true]],
        ['facts' => ['customer_exists' => true]],
    ]);
    $assert(($deduction['facts']['can_quote'] ?? false) === true, 'Reasoning deduction failed');

    $embedding = new TitanZero\Engines\AIInfrastructure\Implementations\EmbeddingEngine();
    $assert($embedding->embed('quote') !== $embedding->embed('invoice'), 'Embeddings should vary by text');

    $vectors = new TitanZero\Engines\AIInfrastructure\Implementations\VectorSearchEngine();
    $vectors->index('quote', [1.0, 0.0]);
    $vectors->index('invoice', [0.0, 1.0]);
    $assert(($vectors->search([0.9, 0.1], 1)[0]['id'] ?? null) === 'quote', 'Vector search failed');

    $similarity = new TitanZero\Engines\Learning\Implementations\SimilarityEngine();
    $most = $similarity->getMostSimilar('quote', ['quote request', 'weather report']);
    $assert(($most[0]['item'] ?? null) === 'quote request', 'String similarity ranking failed');

    $extractor = new TitanZero\Engines\Memory\Implementations\KnowledgeExtractionEngine();
    $facts = $extractor->extractFacts('A quote requires a customer. A job has a schedule.');
    $assert(count($facts) >= 2, 'Knowledge extraction failed');

    $responses = new TitanZero\Engines\HumanInteraction\Implementations\ResponseGenerationEngine();
    $rendered = $responses->generateWithTemplate('Hi {{customer.name}}: {{count}} {{items}}', [
        'customer' => ['name' => 'Jenny'], 'count' => 0, 'items' => ['quote', 'job'],
    ]);
    $assert($rendered === 'Hi Jenny: 0 ["quote","job"]', 'Response templates should resolve nested paths and preserve scalar/JSON values.');
});


$test('cognitive event envelope validates and round trips', function () use ($assert): void {
    $event = TitanZero\Interaction\Cognition\Events\CognitiveEvent::create(
        type: TitanZero\Interaction\Cognition\Events\CognitiveEventType::RecommendationCreated,
        tenantId: 'tenant-a',
        userId: 'user-1',
        deviceId: 'device-1',
        subjectType: 'job',
        subjectId: 'job-1',
        payload: ['proposed_action' => 'assign_worker'],
        confidence: 0.82,
        evidence: [['type' => 'availability', 'value' => true]],
        correlationId: 'corr-1',
    );
    $copy = TitanZero\Interaction\Cognition\Events\CognitiveEvent::fromArray($event->toArray());
    $assert($copy->eventId === $event->eventId, 'Event UUID did not round trip');
    $assert($copy->tenantId === 'tenant-a', 'Tenant scope was lost');
    $assert($copy->confidence === 0.82, 'Confidence was lost');
});

$test('cognitive event store is idempotent and tenant isolated', function () use ($assert): void {
    $store = new TitanZero\Interaction\Cognition\Events\InMemoryCognitiveEventStore();
    $event = TitanZero\Interaction\Cognition\Events\CognitiveEvent::create(
        type: TitanZero\Interaction\Cognition\Events\CognitiveEventType::ObservationRecorded,
        tenantId: 'tenant-a',
        payload: ['fact' => 'worker_available'],
        correlationId: 'corr-2',
        eventId: 'event-fixed',
    );
    $store->append($event);
    $store->append($event);
    $store->append(TitanZero\Interaction\Cognition\Events\CognitiveEvent::create(
        type: TitanZero\Interaction\Cognition\Events\CognitiveEventType::ObservationRecorded,
        tenantId: 'tenant-b',
        payload: ['fact' => 'other'],
        correlationId: 'corr-2',
    ));
    $assert(count($store->forCorrelation('tenant-a', 'corr-2')) === 1, 'Duplicate or cross-tenant event leaked');
    $assert(count($store->forCorrelation('tenant-b', 'corr-2')) === 1, 'Tenant B event missing');
});

$test('prediction links to an outcome and produces a score event', function () use ($assert): void {
    $store = new TitanZero\Interaction\Cognition\Events\InMemoryCognitiveEventStore();
    $decisions = new TitanZero\Interaction\Cognition\Decision\DecisionRecorder($store);
    $outcomes = new TitanZero\Interaction\Cognition\Outcome\OutcomeRecorder($store);
    $prediction = $decisions->recordPrediction(
        tenantId: 'tenant-a',
        proposedAction: 'create_invoice',
        confidence: 0.75,
        correlationId: 'corr-3',
        subjectType: 'job',
        subjectId: 'job-3',
    );
    $outcome = $outcomes->recordOutcome(
        tenantId: 'tenant-a',
        outcome: ['action' => 'create_invoice', 'success' => true],
        correlationId: 'corr-3',
        subjectType: 'job',
        subjectId: 'job-3',
    );
    $score = (new TitanZero\Interaction\Cognition\Outcome\OutcomeLinker($store))->linkAndScore('tenant-a', $prediction->eventId, $outcome->eventId);
    $assert(($score->payload['matched'] ?? false) === true, 'Prediction should match outcome');
    $assert(($score->payload['brier_score'] ?? 1.0) < 0.1, 'Expected a low Brier score');
});

$test('local brain recommendation is not counted as confirmed user behaviour', function () use ($assert): void {
    $brain = TitanZero\Interaction\LocalIntelligence\LocalBrain::createDefault();
    $brain->process('Create a quote for Jenny', ['tenant_id' => 'tenant-a', 'user_id' => 'user-7']);
    $prediction = $brain->memory()->predictNextAction('user-7', 'create_quote');
    $assert($prediction['action'] === null, 'Recommendation polluted confirmed behavioural memory');
    $brain->confirmAction('user-7', 'create_quote', ['tenant_id' => 'tenant-a']);
    $brain->confirmAction('user-7', 'create_invoice', ['tenant_id' => 'tenant-a']);
    $next = $brain->memory()->predictNextAction('user-7', 'create_quote');
    $assert($next['action'] === 'create_invoice', 'Confirmed action sequence was not learned');
});

$test('offline cognitive events replay in original sequence order', function () use ($assert): void {
    $store = new TitanZero\Interaction\Cognition\Events\InMemoryCognitiveEventStore();
    foreach ([3, 1, 2] as $sequence) {
        $store->append(TitanZero\Interaction\Cognition\Events\CognitiveEvent::create(
            type: TitanZero\Interaction\Cognition\Events\CognitiveEventType::ObservationRecorded,
            tenantId: 'tenant-a',
            payload: ['sequence' => $sequence],
            correlationId: 'offline-corr',
            sequence: $sequence,
        ));
    }
    $events = $store->forCorrelation('tenant-a', 'offline-corr');
    $assert(array_map(fn($e) => $e->sequence, $events) === [1, 2, 3], 'Offline replay order is incorrect');
});


$test('authority policy defaults to deny and protects human-only commands', function () use ($assert): void {
    $policy = new TitanZero\Interaction\Policy\PolicyEngine();
    $assert(!$policy->evaluate('unknown.capability', ['_context' => ['actor_type' => 'human']]), 'Unknown capability must fail closed');

    $policy->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
        capability: 'finance.payment.approve',
        authority: TitanZero\Interaction\Authority\AuthorityLevel::UserOnly,
        requiredRoles: ['owner'],
        freshAuthenticationSeconds: 300,
    ));

    $denied = $policy->decide('finance.payment.approve', ['_context' => [
        'actor_type' => 'agent', 'roles' => ['owner'], 'authenticated_at' => time(),
    ]]);
    $assert(!$denied->allowed, 'An agent must never perform a user-only action');

    $allowed = $policy->decide('finance.payment.approve', ['_context' => [
        'tenant_id' => 'tenant-a', 'actor_type' => 'human', 'user_id' => 'u1', 'roles' => ['owner'], 'authenticated_at' => time(),
    ]]);
    $assert($allowed->allowed, 'A freshly authenticated owner should be allowed');
});

$test('approval-required actions need scoped unexpired human approval', function () use ($assert): void {
    $signer = new TitanZero\Interaction\Authority\ApprovalSigner('test-approval-secret-12345-32bytes-minimum');
    $policy = new TitanZero\Interaction\Policy\PolicyEngine($signer);
    $policy->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
        capability: 'quotes.create',
        authority: TitanZero\Interaction\Authority\AuthorityLevel::ApprovalRequired,
        requiredRoles: ['manager'],
        approvalTtlSeconds: 600,
    ));
    $payload = ['total' => 2500, '_context' => ['actor_type' => 'agent', 'roles' => ['assistant']]];
    $assert(!$policy->decide('quotes.create', $payload)->allowed, 'Missing approval must be denied');
    $payload['_approval'] = $signer->issueForPayload('quotes.create', 't1', 'manager-1', ['manager'], $payload, 300);
    $payload['_context']['tenant_id'] = 't1';
    $assert($policy->decide('quotes.create', $payload)->allowed, 'Valid scoped approval should allow execution');
});

$test('delegated actions enforce scopes and numeric limits', function () use ($assert): void {
    $policy = new TitanZero\Interaction\Policy\PolicyEngine();
    $policy->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
        capability: 'finance.refund.create',
        authority: TitanZero\Interaction\Authority\AuthorityLevel::DelegatedAutonomous,
        delegatedScopes: ['refunds:create'],
        numericLimits: ['amount' => 100.0],
    ));
    $context = ['tenant_id' => 'tenant-a', 'actor_type' => 'agent', 'delegated_scopes' => ['refunds:create']];
    $assert($policy->decide('finance.refund.create', ['amount' => 80, '_context' => $context])->allowed, 'In-scope low-value action should be allowed');
    $assert(!$policy->decide('finance.refund.create', ['amount' => 150, '_context' => $context])->allowed, 'Limit breach must be denied');
    $assert(!$policy->decide('finance.refund.create', ['amount' => 'not-money', '_context' => $context])->allowed, 'Non-numeric values must not bypass numeric limits.');
    $assert(!$policy->decide('finance.refund.create', ['_context' => $context])->allowed, 'Missing amount must not bypass a configured numeric limit.');
});

$test('live model action proposals remain untrusted and the command gate blocks execution', function () use ($assert): void {
    $ai = new class implements TitanZero\Interaction\AI\AIServiceInterface {
        public string $response = '{"capability":"quotes.create","payload":{"customer_id":"jenny","total":450},"rationale":"The request names Jenny and a service."}';
        public function generate(string $prompt, array $options = []): string { return $this->response; }
    };
    $proposer = new TitanZero\Interaction\AI\OpenAIActionProposer($ai);
    $proposal = $proposer->propose('Create a carpet cleaning quote for Jenny for $450.', ['quotes.create']);
    $assert($proposal->capability === 'quotes.create', 'Proposal capability is wrong.');
    $assert(($proposal->payload['customer_id'] ?? null) === 'jenny', 'Proposal payload is missing the customer.');

    $policy = new TitanZero\Interaction\Policy\PolicyEngine(new TitanZero\Interaction\Authority\ApprovalSigner('test-proposal-policy-secret-32-bytes-minimum'));
    $policy->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
        'quotes.create', TitanZero\Interaction\Authority\AuthorityLevel::ApprovalRequired,
        requiredRoles: ['owner', 'manager'],
    ));
    $events = new class implements TitanZero\Interaction\Contracts\EventRecorderInterface {
        public array $entries = [];
        public function record(string $eventType, array $data): void { $this->entries[] = [$eventType, $data]; }
        public function getEvents(int $runId): array { return []; }
    };
    $executions = 0;
    $bus = new TitanZero\Interaction\Command\CommandBus($policy, $events);
    $bus->registerHandler('quotes.create', static function () use (&$executions): void { $executions++; });
    try {
        $bus->dispatch($proposal->capability, $proposal->payload + ['_context' => ['tenant_id' => 'tenant-a', 'actor_type' => 'agent']]);
    } catch (RuntimeException $error) {
        $assert(str_contains($error->getMessage(), 'Policy denied'), 'The command bus failed for an unexpected reason.');
    }
    $assert($executions === 0, 'The model proposal executed without approval.');
    $assert(count(array_filter($events->entries, static fn(array $entry): bool => $entry[0] === 'capability_denied')) === 1, 'The denial was not recorded.');

    foreach (['_approval', 'tenant_id', 'user_id', 'idempotency_key'] as $reserved) {
        $ai->response = json_encode(['capability' => 'quotes.create', 'payload' => [$reserved => 'forged']], JSON_THROW_ON_ERROR);
        try {
            $proposer->propose('Create a quote.', ['quotes.create']);
            $assert(false, "Model-supplied authority field {$reserved} should be rejected.");
        } catch (UnexpectedValueException) {
            $assert(true);
        }
    }
});

$test('execution, tool calls, workflows, and synchronization perform real guarded work', function () use ($assert): void {
    $policy = new TitanZero\Interaction\Policy\PolicyEngine();
    $policy->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
        'work.step', TitanZero\Interaction\Authority\AuthorityLevel::DelegatedAutonomous,
        delegatedScopes: ['work:run'],
    ));
    $executor = new TitanZero\Engines\Planning\Implementations\ExecutionEngine($policy);
    $calls = 0;
    $executor->registerHandler('work.step', static function (array $parameters) use (&$calls): array {
        $calls++;
        return ['value' => $parameters['value'] ?? null];
    });
    $parameters = ['value' => 'done', 'idempotency_key' => 'run-1', '_context' => ['tenant_id' => 'tenant-a', 'actor_type' => 'agent', 'delegated_scopes' => ['work:run']]];
    $first = $executor->execute('work.step', $parameters);
    $duplicate = $executor->execute('work.step', $parameters);
    $conflict = $executor->execute('work.step', array_replace($parameters, ['value' => 'changed']));
    $assert($first['status'] === 'success', 'Authorized task did not execute.');
    $assert($duplicate['status'] === 'duplicate', 'Repeated idempotency key was not deduplicated.');
    $assert($conflict['status'] === 'conflict', 'Reused idempotency key with changed data was not rejected.');
    $assert($calls === 1, 'Duplicate task ran more than once.');
    $crossTenant = $executor->execute('work.step', array_replace($parameters, [
        '_context' => array_replace($parameters['_context'], ['tenant_id' => 'tenant-b']),
    ]));
    $assert($crossTenant['status'] === 'success' && $calls === 2, 'Idempotency keys must be isolated by tenant.');

    $tools = new TitanZero\Engines\AIInfrastructure\Implementations\ToolCallingEngine($policy);
    $tools->registerTool('work.step', static fn(array $args): string => (string) ($args['value'] ?? ''));
    $assert($tools->call('work.step', ['value' => 'tool-ok', '_context' => $parameters['_context']]) === 'tool-ok', 'Authorized tool did not run.');
    try {
        $tools->call('work.step', ['value' => 'blocked', '_context' => ['actor_type' => 'agent']]);
        $assert(false, 'Tool call without its required delegated scope should be denied.');
    } catch (RuntimeException $error) {
        $assert(str_contains($error->getMessage(), 'denied'), 'Tool call failed for an unexpected reason.');
    }

    $workflow = new TitanZero\Engines\Planning\Implementations\WorkflowEngine($executor);
    $workflow->registerWorkflow('two-step', [
        ['action' => 'work.step', 'parameters' => ['value' => 'first']],
        ['action' => 'work.step', 'parameters' => ['value' => 'second']],
    ]);
    $workflowId = $workflow->startWorkflow('two-step', ['_context' => $parameters['_context'], 'idempotency_key' => 'workflow-run-1']);
    $status = $workflow->getStatus($workflowId);
    $assert(($status['status'] ?? null) === 'completed' && count($status['steps'] ?? []) === 2, 'Workflow steps were not executed and recorded.');

    $syncPolicy = new TitanZero\Interaction\Policy\PolicyEngine();
    $syncPolicy->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
        'sync.local.remote', TitanZero\Interaction\Authority\AuthorityLevel::DelegatedAutonomous,
        delegatedScopes: ['sync:local-to-remote'],
    ));
    $sync = new TitanZero\Engines\Planning\Implementations\SynchronizationEngine($syncPolicy);
    $written = [];
    $sync->registerSource('local', static fn(): array => [['id' => 1], ['id' => 2]]);
    $sync->registerTarget('remote', static function (array $items) use (&$written): void { $written = $items; });
    try {
        $sync->sync('local', 'remote');
        $assert(false, 'Synchronization without a trusted execution context must be denied.');
    } catch (RuntimeException $error) {
        $assert(str_contains($error->getMessage(), 'denied'), 'Synchronization failed for an unexpected reason.');
    }
    $assert($written === [], 'Denied synchronization reached the writer.');
    $sync->sync('local', 'remote', ['tenant_id' => 'tenant-a', 'actor_type' => 'agent', 'delegated_scopes' => ['sync:local-to-remote']]);
    $syncRecord = $sync->listSyncs()[1];
    $assert($syncRecord['status'] === 'completed' && $syncRecord['record_count'] === 2, 'Synchronization did not transfer and count records.');
    $assert(count($written) === 2 && strlen($syncRecord['sha256']) === 64, 'Synchronization output or checksum is missing.');
});

$test('retry, automation, business setup, learning, risk, prompt, and guardrail engines are operational', function () use ($assert): void {
    $retry = new TitanZero\Engines\Planning\Implementations\RetryEngine();
    $scheduled = [];
    $retry->setRetryPolicy(['base_delay' => 2, 'backoff' => 'exponential']);
    $retry->setScheduler(static function (string $task, int $attempt, string $availableAt) use (&$scheduled): void { $scheduled[] = [$task, $attempt, $availableAt]; });
    $retry->retryWithBackoff('failed-task');
    $retry->retryWithBackoff('failed-task');
    $assert($retry->getTaskRetryState('failed-task')['delay_seconds'] === 4, 'Exponential retry delay is wrong.');
    $assert(count($scheduled) === 2, 'Retry scheduler was not called.');

    $automationPolicy = new TitanZero\Interaction\Policy\PolicyEngine();
    $automationPolicy->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
        'daily-summary', TitanZero\Interaction\Authority\AuthorityLevel::DelegatedAutonomous,
        delegatedScopes: ['summary:generate'],
    ));
    $automationExecutor = new TitanZero\Engines\Planning\Implementations\ExecutionEngine($automationPolicy);
    $automation = new TitanZero\Engines\BusinessIntelligence\Implementations\AutomationEngine($automationExecutor);
    $runCount = 0;
    $automation->registerTaskHandler('daily-summary', static function () use (&$runCount): array { $runCount++; return ['sent' => true]; });
    $automation->automate('daily-summary', ['frequency' => 'daily', 'trusted_context' => ['tenant_id' => 'tenant-a', 'actor_type' => 'agent', 'delegated_scopes' => ['summary:generate']]]);
    $record = $automation->getAutomations()[0];
    $assert(!isset($record['schedule']['trusted_context']), 'Automation listings must not expose server authority context.');
    $automation->trigger($record['id']);
    $assert($runCount === 1 && $automation->getAutomations()[0]['status'] === 'completed', 'Automation handler did not run and record its outcome.');

    $deniedExecutor = new TitanZero\Engines\Planning\Implementations\ExecutionEngine(new TitanZero\Interaction\Policy\PolicyEngine());
    $deniedAutomation = new TitanZero\Engines\BusinessIntelligence\Implementations\AutomationEngine($deniedExecutor);
    $deniedRuns = 0;
    $deniedAutomation->registerTaskHandler('dangerous-task', static function () use (&$deniedRuns): void { $deniedRuns++; });
    $deniedAutomation->automate('dangerous-task', ['frequency' => 'hourly']);
    try {
        $deniedAutomation->trigger($deniedAutomation->getAutomations()[0]['id']);
        $assert(false, 'Automation with no registered capability policy must be denied.');
    } catch (RuntimeException) {
        $assert($deniedRuns === 0 && $deniedAutomation->getAutomations()[0]['status'] === 'rejected', 'Denied automation executed or recorded the wrong state.');
    }

    $builder = new TitanZero\Engines\BusinessIntelligence\Implementations\BusinessBuilderEngine();
    $builder->generate('cleaning');
    $builder->customize(['vertical' => 'cleaning', 'business_name' => 'Jenny Cleaning', 'service_area' => ['Preston', 'Reservoir']]);
    $builder->launch('cleaning');
    $assert(($builder->getLaunches()[0]['status'] ?? null) === 'ready_for_provisioning', 'Completed setup did not reach the handoff state.');

    $learning = new TitanZero\Engines\Learning\Implementations\LearningEngine();
    $learning->learn(['amount' => 10, 'outcome' => 'won'], 'quote-outcomes');
    $learning->learn(['amount' => 20, 'outcome' => 'won'], 'quote-outcomes');
    $learning->retrain('quote-outcomes');
    $summary = $learning->getLearnedModels()['quote-outcomes']['learned_summary'];
    $assert($summary['numeric_means']['amount'] === 15.0 && $summary['sample_count'] === 2, 'Learning summary was not derived from the observations.');

    $risk = new TitanZero\Engines\Executive\Implementations\RiskEngine();
    $assessment = $risk->assess(['financial_value' => 40000, 'new_customer' => true, 'required_evidence_missing' => true]);
    $assert($assessment['score'] === 65 && $assessment['severity'] === 'high', 'Risk assessment did not combine its factors.');
    $risk->mitigate('high_value_transaction', 'Require owner review');
    $assert($risk->getRisks()[0]['status'] === 'mitigation_recorded', 'Risk mitigation was not attached to the finding.');

    $prompt = new TitanZero\Engines\AIInfrastructure\Implementations\PromptEngine();
    $assert(str_contains($prompt->build(['template' => 'concise', 'system' => 'System', 'user' => 'Question']), 'Question'), 'Prompt template did not render its user slot.');
    $guardrails = new TitanZero\Engines\AIInfrastructure\Implementations\GuardrailEngine();
    $assert(!$guardrails->validate('Contact jenny@example.com', 'message'), 'Email guardrail did not reject personal data.');
    $assert(str_contains($guardrails->enforce('Contact jenny@example.com'), '[REDACTED]'), 'Guardrail did not redact personal data.');
});

$test('secondary policy engine fails closed and reports every failed rule', function () use ($assert): void {
    $policy = new TitanZero\Engines\Executive\Implementations\PolicyEngine();
    $assert(!$policy->evaluate('invoice.send', []), 'An empty policy set must deny by default.');
    $assert($policy->getViolations('invoice.send', []) === ['no_policy_registered'], 'Missing policy denial was not explained.');
    $policy->addPolicy('tenant-bound', static fn(string $action, array $context): bool => isset($context['tenant_id']));
    $policy->addPolicy('human-only', static fn(string $action, array $context): bool => ($context['actor_type'] ?? null) === 'human');
    $assert(!$policy->evaluate('invoice.send', ['tenant_id' => 'tenant-a']), 'A failed rule must deny the action.');
    $assert($policy->getViolations('invoice.send', ['tenant_id' => 'tenant-a']) === ['human-only'], 'The failed rule was not identified.');
});

$test('resource, concept, decision, and clarification engines preserve their domain invariants', function () use ($assert): void {
    $resources = new TitanZero\Engines\Planning\Implementations\ResourceAllocationEngine();
    $resources->allocate('van-3', 'job-9');
    $resources->deallocate('van-3', 'job-8');
    $assert(($resources->getResourceAllocation()['van-3'] ?? null) === 'job-9', 'A mismatched task must not release another task’s resource.');
    $resources->deallocate('van-3', 'job-9');
    $assert(!isset($resources->getResourceAllocation()['van-3']), 'The owning task could not release its resource.');

    $concepts = new TitanZero\Engines\Cognitive\Implementations\ConceptEngine();
    $concepts->createConcept('quote', ['kind' => 'workflow']);
    $concepts->createConcept('approval', ['kind' => 'control']);
    $concepts->relate('quote', 'approval', 'requires');
    $concepts->relate('quote', 'approval', 'requires');
    $assert(count($concepts->getRelationships('quote')) === 1, 'Duplicate concept relationships should be consolidated.');

    $decision = new TitanZero\Engines\Executive\Implementations\DecisionEngine();
    $ranked = $decision->evaluate([['id' => 'a', 'quality' => 3], ['id' => 'b', 'quality' => 8]], ['quality' => 2]);
    $assert(($ranked[0]['option']['id'] ?? null) === 'b' && $ranked[0]['score'] === 16.0, 'Weighted decision ranking did not use input criteria.');
    $alternatives = $decision->getAlternatives([['id' => 'a', 'score' => 1], ['id' => 'b', 'score' => 9]]);
    $assert(($alternatives[0]['id'] ?? null) === 'a', 'Alternatives should exclude the top-ranked option.');

    $clarification = new TitanZero\Engines\HumanInteraction\Implementations\ClarificationEngine();
    $message = $clarification->requestMissing(['customer.name', 'service'], ['customer' => ['name' => 'Jenny'], 'service' => '']);
    $assert(str_contains($message, 'service') && !str_contains($message, 'customer.name'), 'Clarification should report missing values and resolve nested paths.');
});

$test('memory facade routes forget operations and rejects unsupported memory types', function () use ($assert): void {
    $episodic = new class implements TitanZero\Engines\Memory\Contracts\EpisodicMemoryEngineInterface {
        public array $calls = [];
        public function store(array $event): void { $this->calls[] = ['episodic.store', $event]; }
        public function recall(array $query): array { $this->calls[] = ['episodic.recall', $query]; return ['event']; }
        public function consolidate(): void { $this->calls[] = ['episodic.consolidate']; }
        public function forgetOlderThan(int $days): void { $this->calls[] = ['episodic.forget', $days]; }
    };
    $semantic = new class implements TitanZero\Engines\Memory\Contracts\SemanticMemoryEngineInterface {
        public array $calls = [];
        public function store(array $fact): void { $this->calls[] = ['semantic.store', $fact]; }
        public function query(array $query): array { return []; }
        public function consolidate(): void {}
        public function getFacts(): array { return []; }
        public function forget(array $query): void { $this->calls[] = ['semantic.forget', $query]; }
    };
    $procedural = new class implements TitanZero\Engines\Memory\Contracts\ProceduralMemoryEngineInterface {
        public array $calls = [];
        public function store(array $skill): void {}
        public function recall(array $query): array { return []; }
        public function getSkill(string $name): ?array { return null; }
        public function listSkills(): array { return []; }
        public function consolidate(): void {}
        public function forget(array $query): void { $this->calls[] = ['procedural.forget', $query]; }
    };
    $memory = new TitanZero\Engines\Memory\Implementations\MemoryEngine($episodic, $semantic, $procedural);
    $memory->forget('episodic', ['older_than_days' => 30]);
    $memory->forget('semantic', ['keyword' => 'obsolete']);
    $memory->forget('procedural', ['name' => 'old routine']);
    $assert(count($episodic->calls) === 1 && count($semantic->calls) === 1 && count($procedural->calls) === 1, 'Facade did not route all supported forgetting operations.');
    try {
        $memory->recall('unknown', []);
        $assert(false, 'Unknown memory types must not silently return an empty list.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('recommendation and generalization use recorded examples and interactions', function () use ($assert): void {
    $recommendations = new TitanZero\Engines\Learning\Implementations\RecommendationEngine();
    $recommendations->setCatalog([
        'carpet' => ['name' => 'Carpet clean', 'category' => 'cleaning'],
        'window' => ['name' => 'Window clean', 'category' => 'cleaning'],
        'plumbing' => ['name' => 'Tap repair', 'category' => 'plumbing'],
    ]);
    $recommendations->recordInteraction(7, 'plumbing', 5);
    $ranked = $recommendations->recommend(7, ['preferences' => ['cleaning']]);
    $assert(($ranked[0]['id'] ?? null) === 'plumbing', 'Recorded user interactions should influence the recommendation ranking.');
    $assert(count($recommendations->getSimilarItems('carpet')) === 2, 'Similar-item search should use the configured catalog.');
    $assert(($recommendations->getTrending()[0]['id'] ?? null) === 'plumbing', 'Trending items should reflect recorded interactions.');

    $generalizer = new TitanZero\Engines\Learning\Implementations\GeneralizationEngine();
    $general = $generalizer->generalize([
        ['kind' => ['name' => 'quote'], 'amount' => 10],
        ['kind' => ['name' => 'quote'], 'amount' => 25],
    ]);
    $assert(($general['rules']['kind']['operator'] ?? null) === 'equals', 'Structurally equal records should not be treated as distinct values.');
    $assert($generalizer->applyGeneralization($general, ['kind' => ['name' => 'quote'], 'amount' => 18])['accepted'], 'Generalized rule should accept values within observed ranges.');
});

$passed = 0;
foreach ($tests as $name => $fn) {
    try {
        $fn();
        echo "PASS {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "FAIL {$name}: {$e->getMessage()}\n";
    }
}

echo "\n{$passed}/" . count($tests) . " tests passed\n";
exit($passed === count($tests) ? 0 : 1);
