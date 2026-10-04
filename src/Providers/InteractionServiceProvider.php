<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Providers;

use Illuminate\Support\ServiceProvider;
use TitanZero\Interaction\Wizard\Storage\WizardSessionStoreInterface;
use TitanZero\Interaction\Wizard\Storage\CacheWizardSessionStore;
use TitanZero\Interaction\Wizard\Renderer\HybridRenderer;
use TitanZero\Interaction\Registry\InteractionRegistry;
use TitanZero\Interaction\Contracts\WorldModelInterface;
use TitanZero\Interaction\Contracts\ExecutiveEngineInterface;
use TitanZero\Interaction\Contracts\CognitiveOrchestratorInterface;
use TitanZero\Interaction\Compiler\SchemaValidator;
use TitanZero\Interaction\Compiler\InteractionCompiler;
use TitanZero\Interaction\Compiler\FragmentResolver;
use TitanZero\Interaction\Compiler\ConditionRegistry;
use TitanZero\Interaction\AI\AIServiceInterface;
use TitanZero\Interaction\AI\NullAIService;
use TitanZero\Interaction\AI\OpenAIService;
use TitanZero\Interaction\AI\OpenAIActionProposer;
use TitanZero\Interaction\AutoComplete\AutoCompleteEngine;
use TitanZero\Interaction\Command\CommandBus;
use TitanZero\Interaction\Cognition\Events\CognitiveEventStoreInterface;
use TitanZero\Interaction\Cognition\Events\EloquentCognitiveEventStore;
use TitanZero\Interaction\Cognition\Decision\DecisionRecorder;
use TitanZero\Interaction\Cognition\Observation\ObservationRecorder;
use TitanZero\Interaction\Cognition\Outcome\OutcomeRecorder;
use TitanZero\Interaction\Cognition\Outcome\OutcomeLinker;
use TitanZero\Interaction\Commands\CompileInteractionCommand;
use TitanZero\Interaction\Commands\HealthCheckCommand;
use TitanZero\Interaction\Commands\InstallCommand;
use TitanZero\Interaction\Commands\ListTemplatesCommand;
use TitanZero\Interaction\Commands\SeedCommand;
use TitanZero\Interaction\Commands\SyncInteractionCommand;
use TitanZero\Interaction\Context\ContextBuilder;
use TitanZero\Interaction\Context\ContextGraph;
use TitanZero\Interaction\Context\Providers\CustomerContextProvider;
use TitanZero\Interaction\Context\Providers\PolicyContextProvider;
use TitanZero\Interaction\Contracts\CommandBusInterface;
use TitanZero\Interaction\Contracts\ConflictResolverInterface;
use TitanZero\Interaction\Contracts\DomainAdapterInterface;
use TitanZero\Interaction\Contracts\EventRecorderInterface;
use TitanZero\Interaction\Contracts\GeneratorInterface;
use TitanZero\Interaction\Contracts\HealerInterface;
use TitanZero\Interaction\Contracts\LearnerInterface;
use TitanZero\Interaction\Contracts\NavigationEngineInterface;
use TitanZero\Interaction\Contracts\OfflineQueueInterface;
use TitanZero\Interaction\Contracts\PolicyEngineInterface;
use TitanZero\Interaction\Contracts\PredictorInterface;
use TitanZero\Interaction\Contracts\QuestionResolverInterface;
use TitanZero\Interaction\Contracts\RendererInterface;
use TitanZero\Interaction\Contracts\StateManagerInterface;
use TitanZero\Interaction\Contracts\ValidationEngineInterface;
use TitanZero\Interaction\Domain\Adapters\WorkCoreAdapter;
use TitanZero\Interaction\Event\EventRecorder;
use TitanZero\Interaction\Generator\GeneratorEngine;
use TitanZero\Interaction\Healer\HealerEngine;
use TitanZero\Interaction\Knowledge\KnowledgeRegistry;
use TitanZero\Interaction\Knowledge\Sources\CustomerKnowledgeSource;
use TitanZero\Interaction\Knowledge\Sources\ServiceKnowledgeSource;
use TitanZero\Interaction\Learner\LearnerEngine;
use TitanZero\Interaction\LocalIntelligence\LocalBrain;
use TitanZero\Interaction\LocalIntelligence\Storage\LocalIntelligenceMemoryStoreInterface;
use TitanZero\Interaction\LocalIntelligence\Storage\EloquentLocalIntelligenceMemoryStore;
use TitanZero\Interaction\Navigation\NavigationEngine;
use TitanZero\Interaction\Offline\ConflictResolver;
use TitanZero\Interaction\Offline\OfflineDetector;
use TitanZero\Interaction\Offline\OfflineQueue;
use TitanZero\Interaction\Pattern\PatternRegistry;
use TitanZero\Interaction\Policy\PolicyEngine;
use TitanZero\Interaction\Authority\ApprovalSigner;
use TitanZero\Interaction\Authority\AuthorityLevel;
use TitanZero\Interaction\Authority\CapabilityPolicy;
use TitanZero\Interaction\Predictor\PredictorEngine;
use TitanZero\Interaction\Registry\CapabilityRegistry;
use TitanZero\Interaction\Renderer\BladeRenderer;
use TitanZero\Interaction\Repositories\EloquentInteractionAnswerRepository;
use TitanZero\Interaction\Repositories\EloquentInteractionRunRepository;
use TitanZero\Interaction\Repositories\InteractionAnswerRepositoryInterface;
use TitanZero\Interaction\Repositories\InteractionRunRepositoryInterface;
use TitanZero\Interaction\Resolver\QuestionResolver;
use TitanZero\Interaction\State\StateManager;
use TitanZero\Interaction\Validation\ValidationEngine;
use TitanZero\Interaction\Wizard\Command\CommandMapper;
use TitanZero\Interaction\Wizard\Guidance\LocalGuidanceProvider;
use TitanZero\Interaction\Wizard\Context\WizardExecutionContextFactory;
use TitanZero\Interaction\Wizard\Security\WizardSessionAccessPolicy;
use TitanZero\Interaction\Wizard\Offline\LocalCommandOutbox;
use TitanZero\Interaction\Wizard\UniversalWizardEngine;
use TitanZero\Interaction\Wizard\Validation\WizardValidationEngine;
use TitanZero\Interaction\Wizard\WizardRegistry;
use TitanZero\Interaction\Template\TemplateCompatibilityChecker;
use TitanZero\Interaction\Template\TemplateRegistry;

class InteractionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/interaction.php', 'interaction');

        $this->app->bind(DomainAdapterInterface::class, function (): DomainAdapterInterface {
            $class = config('interaction.adapter', WorkCoreAdapter::class);
            return new $class(config('interaction.adapter_config', []));
        });
        $this->app->singleton(ApprovalSigner::class, fn(): ApprovalSigner => new ApprovalSigner((string) config('interaction.authority.approval_secret')));
        $this->app->singleton(PolicyEngineInterface::class, fn($app): PolicyEngineInterface => new PolicyEngine($app->make(ApprovalSigner::class)));
        $this->app->singleton(EventRecorderInterface::class, EventRecorder::class);
        $this->app->singleton(CognitiveEventStoreInterface::class, EloquentCognitiveEventStore::class);
        $this->app->singleton(DecisionRecorder::class);
        $this->app->singleton(ObservationRecorder::class);
        $this->app->singleton(OutcomeRecorder::class);
        $this->app->singleton(OutcomeLinker::class);
        $this->app->singleton(CommandBusInterface::class, CommandBus::class);
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->singleton(SchemaValidator::class);
        $this->app->singleton(FragmentResolver::class);
        $this->app->singleton(ConditionRegistry::class);
        $this->app->singleton(InteractionCompiler::class);
        $this->app->singleton(InteractionRegistry::class);
        // ExecutiveEngine/CognitiveOrchestrator/WorldModel each satisfy
        // two interfaces (a legacy TitanZero\Interaction\Contracts one and
        // the new TitanZero\Engines\...\Contracts one from the 80-engine
        // library). Binding each interface independently via singleton()
        // would make Laravel construct two separate objects with two
        // separate internal states (e.g. two different $interruptions
        // queues on "the" executive engine) — bind the real concrete class
        // once and alias every interface to that one instance.
        $this->app->scoped(\TitanZero\Engines\Executive\Implementations\ExecutiveEngine::class);
        $this->app->scoped(
            \TitanZero\Engines\Executive\Contracts\ExecutiveEngineInterface::class,
            fn ($app) => $app->make(\TitanZero\Engines\Executive\Implementations\ExecutiveEngine::class)
        );
        $this->app->scoped(
            ExecutiveEngineInterface::class,
            fn ($app) => $app->make(\TitanZero\Engines\Executive\Implementations\ExecutiveEngine::class)
        );

        $this->app->scoped(\TitanZero\Engines\Cognitive\Implementations\CognitiveOrchestrator::class);
        $this->app->scoped(
            \TitanZero\Engines\Cognitive\Contracts\CognitiveOrchestratorInterface::class,
            fn ($app) => $app->make(\TitanZero\Engines\Cognitive\Implementations\CognitiveOrchestrator::class)
        );
        $this->app->scoped(
            CognitiveOrchestratorInterface::class,
            fn ($app) => $app->make(\TitanZero\Engines\Cognitive\Implementations\CognitiveOrchestrator::class)
        );

        $this->app->scoped(\TitanZero\Engines\Memory\Implementations\WorldModelEngine::class);
        $this->app->scoped(
            \TitanZero\Engines\Memory\Contracts\WorldModelEngineInterface::class,
            fn ($app) => $app->make(\TitanZero\Engines\Memory\Implementations\WorldModelEngine::class)
        );
        $this->app->scoped(
            WorldModelInterface::class,
            fn ($app) => $app->make(\TitanZero\Engines\Memory\Implementations\WorldModelEngine::class)
        );

        $this->app->bind(InteractionRunRepositoryInterface::class, EloquentInteractionRunRepository::class);
        $this->app->bind(InteractionAnswerRepositoryInterface::class, EloquentInteractionAnswerRepository::class);
        $this->app->singleton(StateManagerInterface::class, StateManager::class);
        $this->app->singleton(NavigationEngineInterface::class, NavigationEngine::class);
        $this->app->singleton(ValidationEngineInterface::class, ValidationEngine::class);
        $this->app->singleton(RendererInterface::class, fn(): RendererInterface => new BladeRenderer((string) config('interaction.view', 'interaction::run')));

        $this->app->singleton(KnowledgeRegistry::class, function (): KnowledgeRegistry {
            $registry = new KnowledgeRegistry();
            $registry->registerSource(new CustomerKnowledgeSource());
            $registry->registerSource(new ServiceKnowledgeSource());
            return $registry;
        });
        $this->app->singleton(ContextBuilder::class, function (): ContextBuilder {
            $builder = new ContextBuilder();
            $builder->registerProvider(new CustomerContextProvider());
            $builder->registerProvider(new PolicyContextProvider());
            return $builder;
        });
        $this->app->singleton(QuestionResolverInterface::class, QuestionResolver::class);

        $this->app->singleton(AIServiceInterface::class, function (): AIServiceInterface {
            if (!config('interaction.ai.enabled', false)) {
                return new NullAIService();
            }
            if (!class_exists('OpenAI')) {
                throw new \RuntimeException('Cloud AI is enabled but openai-php/client is not installed.');
            }
            $apiKey = (string) config('interaction.ai.api_key');
            if ($apiKey === '') {
                throw new \RuntimeException('Cloud AI is enabled but INTERACTION_AI_API_KEY is empty.');
            }
            $client = \OpenAI::factory()->withApiKey($apiKey)->make();
            return new OpenAIService($client, (string) config('interaction.ai.model', 'gpt-4o-mini'));
        });
        $this->app->singleton(OpenAIActionProposer::class, fn($app): OpenAIActionProposer => new OpenAIActionProposer(
            $app->make(AIServiceInterface::class),
            (string) config('interaction.ai.model', 'gpt-4o-mini'),
        ));

        $this->app->singleton(OfflineQueueInterface::class, OfflineQueue::class);
        $this->app->singleton(ConflictResolverInterface::class, ConflictResolver::class);
        $this->app->singleton(OfflineDetector::class, fn(): OfflineDetector => new OfflineDetector((string) config('interaction.offline.health_check_url')));

        $this->app->singleton(PredictorInterface::class, PredictorEngine::class);
        $this->app->singleton(LearnerInterface::class, LearnerEngine::class);
        $this->app->singleton(GeneratorInterface::class, GeneratorEngine::class);
        $this->app->singleton(HealerInterface::class, HealerEngine::class);
        $this->app->singleton(PatternRegistry::class);
        $this->app->singleton(ContextGraph::class);
        $this->app->singleton(AutoCompleteEngine::class);

        $this->app->singleton(LocalIntelligenceMemoryStoreInterface::class, EloquentLocalIntelligenceMemoryStore::class);
        $this->app->singleton(LocalBrain::class, function ($app): LocalBrain {
            // LocalBrain::createDefault() (in-memory only, no persistence)
            // is still used by the bundled tests/run.php harness, which
            // has no database. This is the real, persistence-backed path
            // used at runtime — see LocalBrain::createWithPersistence()
            // and the LocalIntelligence/Storage/ classes for what this
            // fixes and why.
            $store = $app->make(LocalIntelligenceMemoryStoreInterface::class);
            $user = $app->bound('request') ? $app->make('request')->user() : null;
            $tenantId = (string) (
                data_get($user, 'tenant_id')
                ?? data_get($user, 'team_id')
                ?? config('interaction.wizard.default_tenant_id', 'default')
            );
            return LocalBrain::createWithPersistence($store, $tenantId, $app->make(CognitiveEventStoreInterface::class));
        });
        $this->app->singleton(WizardRegistry::class, function (): WizardRegistry {
            $registry = new WizardRegistry();
            $registry->discover((string) config('interaction.wizard.definitions_path'));
            return $registry;
        });
        $this->app->singleton(TemplateRegistry::class, function (): TemplateRegistry {
            $registry = new TemplateRegistry();
            $registry->discover((string) config('interaction.template.definitions_path', base_path('templates')));
            return $registry;
        });
        $this->app->singleton(TemplateCompatibilityChecker::class, fn($app): TemplateCompatibilityChecker => new TemplateCompatibilityChecker(
            $app->make(WizardRegistry::class),
        ));
        $this->app->singleton(LocalCommandOutbox::class, fn(): LocalCommandOutbox => new LocalCommandOutbox((string) config('interaction.wizard.outbox_secret')));
        $this->app->singleton(WizardValidationEngine::class);
        $this->app->singleton(LocalGuidanceProvider::class);
        $this->app->singleton(WizardExecutionContextFactory::class);
        $this->app->singleton(WizardSessionAccessPolicy::class);
        $this->app->singleton(CommandMapper::class);
        $this->app->singleton(UniversalWizardEngine::class, function ($app): UniversalWizardEngine {
            $offline = config('interaction.offline.enabled', true) && $app->make(OfflineDetector::class)->isOffline();
            return new UniversalWizardEngine(
                $app->make(WizardRegistry::class),
                $app->make(WizardValidationEngine::class),
                $app->make(LocalGuidanceProvider::class),
                $app->make(CommandMapper::class),
                $app->make(LocalCommandOutbox::class),
                $offline ? null : $app->make(CommandBusInterface::class),
                $app->make(CognitiveEventStoreInterface::class),
            );
        });
        $this->app->singleton(HybridRenderer::class);
        $this->app->singleton(WizardSessionStoreInterface::class, function ($app): WizardSessionStoreInterface {
            return new CacheWizardSessionStore(
                $app['cache.store'],
                $app->make(WizardRegistry::class),
                (int) config('interaction.wizard.session_ttl', 86400),
            );
        });

        $this->registerEngineLibrary();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'interaction');
        $this->loadMigrationsFrom(__DIR__ . '/../Migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->publishes([
            __DIR__ . '/../../config/interaction.php' => config_path('interaction.php'),
        ], 'interaction-config');
        $this->publishes([
            __DIR__ . '/../../wizards' => base_path('wizards'),
            __DIR__ . '/../../interactions' => base_path('interactions'),
            __DIR__ . '/../../templates' => base_path('templates'),
        ], 'interaction-definitions');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CompileInteractionCommand::class,
                HealthCheckCommand::class,
                InstallCommand::class,
                ListTemplatesCommand::class,
                SeedCommand::class,
                SyncInteractionCommand::class,
            ]);
        }

        $this->registerPolicies();
        $this->registerCommandHandlers();
    }

    private function registerPolicies(): void
    {
        $policy = $this->app->make(PolicyEngineInterface::class);
        $policies = [
            new CapabilityPolicy('crm.customer.create', AuthorityLevel::DelegatedAutonomous, delegatedScopes: ['crm:customer:create']),
            new CapabilityPolicy('quotes.create', AuthorityLevel::ApprovalRequired, requiredRoles: ['owner', 'manager'], approvalTtlSeconds: 900),
            new CapabilityPolicy('jobs.create', AuthorityLevel::ApprovalRequired, requiredRoles: ['owner', 'manager', 'dispatcher'], approvalTtlSeconds: 900),
            new CapabilityPolicy('jobs.complete', AuthorityLevel::DelegatedAutonomous, delegatedScopes: ['jobs:complete']),
            new CapabilityPolicy('finance.invoice.create', AuthorityLevel::ApprovalRequired, requiredRoles: ['owner', 'manager', 'finance'], approvalTtlSeconds: 900),
            new CapabilityPolicy('finance.payment.record', AuthorityLevel::UserOnly, requiredRoles: ['owner', 'finance'], freshAuthenticationSeconds: 300),
        ];
        foreach ($policies as $capabilityPolicy) {
            $policy->registerCapabilityPolicy($capabilityPolicy);
        }

        $policy->registerPolicy('jobs.create', static fn(array $payload): array => empty($payload['customer_id'])
            ? ['allowed' => false, 'reason' => 'Customer ID is required to create a job.']
            : ['allowed' => true]);
        $policy->registerPolicy('jobs.complete', static fn(array $payload): array => empty($payload['job_id'])
            ? ['allowed' => false, 'reason' => 'Job ID is required to complete a job.']
            : ['allowed' => true]);
    }

    private function registerCommandHandlers(): void
    {
        $bus = $this->app->make(CommandBusInterface::class);
        $registry = $this->app->make(CapabilityRegistry::class);
        foreach (['crm.customer.create', 'quotes.create', 'jobs.create', 'jobs.complete', 'finance.invoice.create', 'finance.payment.record'] as $capability) {
            $bus->registerHandler($capability, static fn(array $payload): mixed => ($registry->getHandler($capability))($payload));
        }
    }

    private function registerEngineLibrary(): void
    {
        $domains = [
            'Executive' => ['StrategicPlanningEngine','GoalEngine','PriorityEngine','DecisionEngine','PolicyEngine','GovernanceEngine','RiskEngine','OpportunityEngine','EscalationEngine'],
            'Cognitive' => ['SemanticEngine','ReasoningEngine','InferenceEngine','ReflectionEngine','ExplainabilityEngine','AbstractionEngine','ConceptEngine','AnalogyEngine','CreativityEngine'],
            'Memory' => ['MemoryEngine','EpisodicMemoryEngine','SemanticMemoryEngine','ProceduralMemoryEngine','MemoryConsolidationEngine','ForgettingEngine','KnowledgeGraphEngine','KnowledgeExtractionEngine','ContextEngine'],
            'Learning' => ['LearningEngine','ReinforcementLearningEngine','PatternRecognitionEngine','BehaviourLearningEngine','PreferenceLearningEngine','FeedbackEngine','PredictionEngine','RecommendationEngine','SimilarityEngine','GeneralizationEngine'],
            'Planning' => ['PlanningEngine','WorkflowEngine','TaskDecompositionEngine','CapabilityRoutingEngine','ExecutionEngine','SchedulingEngine','ResourceAllocationEngine','SynchronizationEngine','RecoveryEngine','RetryEngine'],
            'HumanInteraction' => ['ConversationEngine','IntentEngine','EmotionEngine','SentimentEngine','PersonalityEngine','EmpathyEngine','ClarificationEngine','DialogueEngine','ResponseGenerationEngine','TrustEngine'],
            'AIInfrastructure' => ['PromptEngine','PromptOptimizationEngine','ModelSelectionEngine','EmbeddingEngine','RetrievalEngine','VectorSearchEngine','ToolCallingEngine','FunctionCallingEngine','GuardrailEngine','EvaluationEngine'],
            'BusinessIntelligence' => ['CRMIntelligenceEngine','OperationsEngine','DispatchEngine','FinancialInsightEngine','AnalyticsEngine','ComplianceEngine','AuditEngine','MonitoringEngine','AutomationEngine','BusinessBuilderEngine'],
        ];
        foreach ($domains as $domain => $engines) {
            foreach ($engines as $engine) {
                $contract = "TitanZero\\Engines\\{$domain}\\Contracts\\{$engine}Interface";
                $implementation = "TitanZero\\Engines\\{$domain}\\Implementations\\{$engine}";
                // Engine-local histories, retry state and dispatch queues must
                // live only for the current request/job lifecycle. Persistent
                // learning and memory belong in their explicit cache/database stores.
                $this->app->scoped($contract, $implementation);
            }
        }
    }
}
