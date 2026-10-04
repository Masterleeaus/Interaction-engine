<?php

declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'TitanZero\\Engines\\' => $root . '/src/Engines/',
        'TitanZero\\Interaction\\' => $root . '/src/',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $path = $base . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
            return;
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
        if ($file->isFile() && $file->getExtension() === 'php') {
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
        if ($file->isFile()) {
            $name = $file->getFilename();
            $assert(!preg_match('/\((updated|example|add new bindings|updated with resolver)\)/i', $name), 'Patch artifact remains: ' . $file->getPathname());
        }
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
});



$test('80-engine audit documents no-op methods and rejects new placeholders', function () use ($root, $assert): void {
    $expectedNoOps = [
        'src/Engines/BusinessIntelligence/Implementations/AutomationEngine.php::trigger',
        'src/Engines/BusinessIntelligence/Implementations/BusinessBuilderEngine.php::launch',
        'src/Engines/Learning/Implementations/LearningEngine.php::retrain',
        'src/Engines/Memory/Implementations/EpisodicMemoryEngine.php::consolidate',
        'src/Engines/Memory/Implementations/SemanticMemoryEngine.php::consolidate',
        'src/Engines/Planning/Implementations/RetryEngine.php::retryWithBackoff',
    ];

    $emptyPublicMethods = static function (string $source): array {
        $tokens = token_get_all($source);
        $empty = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_PUBLIC) {
                continue;
            }

            $cursor = $i + 1;
            while ($cursor < $count && is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], [T_WHITESPACE, T_STATIC], true)) {
                $cursor++;
            }
            if ($cursor >= $count || !is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_FUNCTION) {
                continue;
            }

            $cursor++;
            while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
                $cursor++;
            }
            if ($cursor < $count && $tokens[$cursor] === '&') {
                $cursor++;
            }
            while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
                $cursor++;
            }
            if ($cursor >= $count || !is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_STRING) {
                continue;
            }
            $name = $tokens[$cursor][1];

            while ($cursor < $count && $tokens[$cursor] !== '{') {
                $cursor++;
            }
            if ($cursor >= $count) {
                continue;
            }

            $depth = 1;
            $body = [];
            for ($cursor++; $cursor < $count && $depth > 0; $cursor++) {
                $token = $tokens[$cursor];
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                }
                if ($depth > 0) {
                    $body[] = $token;
                }
            }

            $hasCode = false;
            foreach ($body as $token) {
                if (is_array($token)) {
                    if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        $hasCode = true;
                        break;
                    }
                } elseif (trim($token) !== '') {
                    $hasCode = true;
                    break;
                }
            }
            if (!$hasCode && $name !== '__construct') {
                $empty[] = $name;
            }
            $i = $cursor;
        }

        return $empty;
    };

    $actualNoOps = [];
    $markerFiles = [];
    foreach (glob($root . '/src/Engines/*/Implementations/*.php') ?: [] as $file) {
        $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
        $source = (string) file_get_contents($file);
        if (preg_match('/\b(?:TODO|FIXME|not implemented|placeholder|stub)\b/i', $source) === 1) {
            $markerFiles[] = $relative;
        }
        foreach ($emptyPublicMethods($source) as $method) {
            $actualNoOps[] = $relative . '::' . $method;
        }
    }

    sort($actualNoOps);
    sort($expectedNoOps);
    sort($markerFiles);
    $assert($actualNoOps === $expectedNoOps, 'Unexpected no-op engine methods: ' . implode(', ', array_diff($actualNoOps, $expectedNoOps)));
    $assert($markerFiles === [], 'Placeholder markers found in engine implementations: ' . implode(', ', $markerFiles));
});

$test('all JSON resources are valid and the five foundation wizards load', function () use ($root, $assert): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'json') {
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
    foreach (glob($root . '/interactions/*.json') ?: [] as $file) {
        $definition = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $validator->validate($definition);
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
        'actor_type' => 'human', 'user_id' => 'u1', 'roles' => ['owner'], 'authenticated_at' => time(),
    ]]);
    $assert($allowed->allowed, 'A freshly authenticated owner should be allowed');
});

$test('approval-required actions need scoped unexpired human approval', function () use ($assert): void {
    $signer = new TitanZero\Interaction\Authority\ApprovalSigner('test-approval-secret-12345');
    $policy = new TitanZero\Interaction\Policy\PolicyEngine($signer);
    $policy->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
        capability: 'quotes.create',
        authority: TitanZero\Interaction\Authority\AuthorityLevel::ApprovalRequired,
        requiredRoles: ['manager'],
        approvalTtlSeconds: 600,
    ));
    $payload = ['total' => 2500, '_context' => ['actor_type' => 'agent', 'roles' => ['assistant']]];
    $assert(!$policy->decide('quotes.create', $payload)->allowed, 'Missing approval must be denied');
    $payload['_approval'] = $signer->issue('quotes.create', 't1', 'manager-1', ['manager'], 300);
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
    $context = ['actor_type' => 'agent', 'delegated_scopes' => ['refunds:create']];
    $assert($policy->decide('finance.refund.create', ['amount' => 80, '_context' => $context])->allowed, 'In-scope low-value action should be allowed');
    $assert(!$policy->decide('finance.refund.create', ['amount' => 150, '_context' => $context])->allowed, 'Limit breach must be denied');
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
