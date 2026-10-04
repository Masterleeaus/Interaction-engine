# Engine library inventory: 80 contracts and implementations

Every listed contract has a matching PHP implementation. `Implemented` means the contract's operations have working, deterministic behavior within the stated boundary. `Partial` means the code is executable but still relies on heuristics, in-memory state, host-specific storage/schema, or a missing production adapter. Neither label means the engine has been independently validated for every deployment. There are no interface-only entries in this branch.

## Executive

| Engine | Status | What works | Boundary |
| --- | --- | --- | --- |
| `ExecutiveEngine` | Partial | Consumes plans, reasoning, predictions, interrupts and priority tasks. | In-memory lifecycle state; planning input and prediction model need a host workflow. |
| `StrategicPlanningEngine` | Partial | Tracks objectives, computes progress from objective records, and validates status updates. | Cache-backed state uses a shared key; tenant-specific store and durable outcome sources are host work. |
| `GoalEngine` | Partial | Stores goals, progress and next milestones. | Progress is manually supplied; cache key is not tenant scoped. |
| `PriorityEngine` | Implemented | Ranks items with urgency, impact, role context and explicit overrides. | A transparent weighted score, not a learned or calibrated business model. |
| `DecisionEngine` | Implemented | Computes weighted criteria scores, returns a winner and ranks alternatives. | Caller supplies comparable numeric criteria and weights. |
| `PolicyEngine` | Implemented | Evaluates registered rules, fails closed with no rules, and reports all failed rule names. | A small policy helper; capability authority belongs to the extracted Interaction Policy Engine. |
| `GovernanceEngine` | Partial | Writes and queries governance events and produces reports from logged events. | Requires the host `governance_logs` schema and tenant filtering. |
| `RiskEngine` | Partial | Combines explainable financial, customer, evidence, complaint, discount and safety factors. | Heuristic weights require calibration and validation against real outcomes. |
| `OpportunityEngine` | Partial | Finds activity/trend signals, prioritizes by value and tracks plans. | Small rule set; no CRM or market feed is bundled. |
| `EscalationEngine` | Partial | Records active escalations and resolutions. | Default severity policy is in-memory; no queue, on-call directory or timer worker is bundled. |

## Cognitive

| Engine | Status | What works | Boundary |
| --- | --- | --- | --- |
| `CognitiveOrchestrator` | Partial | Exposes the nine cognitive services through one typed object. | A service aggregate, not an autonomous reasoning loop. |
| `SemanticEngine` | Partial | Delegates vectorization and maps supported business phrases to intents. | Intent matching is rule based; vectors are feature hashes, not semantic model embeddings. |
| `ReasoningEngine` | Implemented | Applies forward-chained facts/rules, finds frequent attributes, ranks hypotheses and options. | Deterministic symbolic/statistical baseline; no general-purpose reasoning model. |
| `InferenceEngine` | Implemented | Resolves nested facts and predicts numeric or categorical values from supplied history. | No learned model or external data source. |
| `ReflectionEngine` | Partial | Summarizes events and recorded feedback into decision evaluations. | Needs a durable outcome/feedback source for production learning. |
| `ExplainabilityEngine` | Partial | Produces an explanation and view from the decision text and supplied context. | Does not retrieve a decision trace unless the host supplies it. |
| `AbstractionEngine` | Partial | Derives common and variable fields, generalizes examples and classifies attributes. | Structural heuristics only; no domain ontology. |
| `ConceptEngine` | Implemented | Stores concepts, validates relationships, avoids duplicate edges and returns adjacent relationships. | In-memory graph for the lifetime of the engine instance. |
| `AnalogyEngine` | Partial | Maps common keys and values between source and target examples. | Similarity is lexical/structural and needs domain review. |
| `CreativityEngine` | Partial | Generates recombinations and brainstorming prompts from the requested topic. | Template based, not generative AI. |

## Memory

| Engine | Status | What works | Boundary |
| --- | --- | --- | --- |
| `MemoryEngine` | Implemented | Routes store, recall, forget and consolidate to episodic, semantic and procedural stores; unknown types throw. | Uses the backing stores' host and schema limits. |
| `EpisodicMemoryEngine` | Partial | Stores events, recalls by time, consolidates duplicates and prunes old records. | Requires the host `episodic_memory` database table; tenant partitioning must be supplied by the host. |
| `SemanticMemoryEngine` | Partial | Stores/query facts and merges duplicate records with occurrence metadata. | Requires the host `semantic_memory` table; keyword search is not vector retrieval. |
| `ProceduralMemoryEngine` | Partial | Stores, searches, lists and deletes named skills. | Cache-backed state and search are not durable workflow execution. |
| `MemoryConsolidationEngine` | Partial | Summarizes event types, retains high-importance events and orders pending items. | In-memory queue; no background worker or persistent summary store. |
| `ForgettingEngine` | Partial | Validates decay settings and computes an explicit retention curve. | Reports a curve only; it does not purge another memory store itself. |
| `KnowledgeGraphEngine` | Partial | Stores nodes/edges, traverses relationships by breadth-first search and ranks nearby nodes. | Cache key is global and requires tenant-specific configuration before shared multi-tenant use. |
| `KnowledgeExtractionEngine` | Partial | Extracts supported names, dates and relation patterns from text. | Rule based; not a general entity/relation model. |
| `ContextEngine` | Partial | Builds, updates, retrieves and clears user context with a cache TTL. | Cache key uses user ID only; tenants with overlapping IDs need a tenant-aware host adapter. |
| `WorldModelEngine` | Partial | Loads customers and jobs and supports entity lookup/filtering. | Assumes host tables and currently uses a shared cache key; tenant/query scoping is host work. |

## Learning

| Engine | Status | What works | Boundary |
| --- | --- | --- | --- |
| `LearningEngine` | Partial | Stores bounded observations and retrains numeric means, category counts and sample summaries. | A descriptive statistics baseline, not a trainable general ML model. |
| `ReinforcementLearningEngine` | Partial | Maintains Q values and derives an action policy from observed rewards. | Small tabular implementation; no exploration strategy, persistence or offline evaluation. |
| `PatternRecognitionEngine` | Partial | Counts repeated records and groups items by supplied category/type/score features. | Feature-driven grouping, not unsupervised clustering. |
| `BehaviourLearningEngine` | Partial | Builds action frequencies and predicts the most common transition from history. | Local memory plus an optional host table; needs tenant retention and privacy controls. |
| `PreferenceLearningEngine` | Partial | Saves, retrieves and lists user preferences. | Requires the host `user_preferences` table and tenant-aware user identity. |
| `FeedbackEngine` | Partial | Collects feedback and computes counts, ratings, sentiment hints and frequent themes. | Simple aggregation and lexicon matching. |
| `PredictionEngine` | Partial | Predicts a supplied target from direct values, numeric means or categorical history. | Baseline statistics only; returns no prediction when evidence is absent. |
| `RecommendationEngine` | Partial | Ranks a supplied catalog using preference text and recorded interaction counts; finds similar and trending items. | In-memory catalog and lexical scoring; no collaborative model or persistence. |
| `SimilarityEngine` | Implemented | Computes cosine and Jaccard similarity and sorts candidate items. | Expects numeric vectors or comparable token sets from the caller. |
| `GeneralizationEngine` | Partial | Derives equality, range and membership rules from examples and evaluates held-out records. | Small rule learner; dataset quality and split strategy belong to the caller. |

## Planning

| Engine | Status | What works | Boundary |
| --- | --- | --- | --- |
| `PlanningEngine` | Partial | Creates validated goal plans from caller steps or a default checklist. | Default steps are a generic template; plan lifecycle is in-memory. |
| `WorkflowEngine` | Implemented | Runs registered steps through the execution engine and records each result and failure step. | Synchronous, in-memory workflow history; long-running orchestration needs a durable queue. |
| `TaskDecompositionEngine` | Partial | Splits supported task text or supplied steps, sets dependencies and assigns simple durations. | Text splitting is heuristic and does not estimate work from a learned model. |
| `CapabilityRoutingEngine` | Implemented | Resolves registered capabilities only after the Policy Engine allows them. | Handler registry is process/request scoped. |
| `ExecutionEngine` | Partial | Gates commands, records outcomes, detects changed-payload idempotency conflicts and avoids repeat calls in-process. | Idempotency ledger and history are in-memory; durable multi-worker deduplication needs a host store. |
| `SchedulingEngine` | Partial | Greedily allocates tasks to resources with remaining capacity and supports rescheduling. | No calendars, travel times, skill constraints or optimization solver. |
| `ResourceAllocationEngine` | Partial | Assigns resources to tasks and prevents a different task from releasing an assignment. | One task per resource in-memory; no capacity, locking or persistence. |
| `SynchronizationEngine` | Partial | Transfers JSON-serializable records between registered adapters after capability authorization and records checksums/status. | Adapter, conflict resolution, retries and durable cursor are host responsibilities. |
| `RecoveryEngine` | Partial | Records failures, tracks retries and marks recovered entries. | Retry is a recorded handoff signal; automatic replay belongs to the retry/queue adapter. |
| `RetryEngine` | Partial | Computes fixed, linear or exponential delay and passes work to a scheduler callback. | Does not itself provide a durable worker or job store. |

## Human interaction

| Engine | Status | What works | Boundary |
| --- | --- | --- | --- |
| `ConversationEngine` | Partial | Maintains conversation history and uses dialogue state to ask for missing workflow information. | In-memory, rule-based and not connected to speech recognition. |
| `IntentEngine` | Partial | Detects the supported action phrases and exposes confidence for those matches. | Rule-based phrase catalogue; see `LocalLanguageEngine` description. |
| `EmotionEngine` | Partial | Estimates a supported emotion from lexical indicators. | Heuristic and not suitable for sensitive decisions. |
| `SentimentEngine` | Partial | Computes a polarity score from positive/negative terms and returns the matching label. | Small lexicon; not a validated sentiment model. |
| `PersonalityEngine` | Partial | Infers a lightweight communication profile from supplied context and adapts response settings. | Heuristic profile; no psychological inference or persistent profile store. |
| `EmpathyEngine` | Partial | Selects response framing from lexical sentiment cues and exposes its score/strategies. | Template and lexicon based. |
| `ClarificationEngine` | Implemented | Identifies absent, empty and nested required fields and keeps an interaction history. | Generates generic question text; domain labels should be supplied by the caller. |
| `DialogueEngine` | Partial | Maintains a rule-based intent/entity state and asks for missing quote/job information. | It prepares proposals only; it does not execute actions. |
| `ResponseGenerationEngine` | Partial | Renders typed response templates and `{{field}}` data slots. | Template renderer, not a language model. |
| `TrustEngine` | Partial | Applies event weights to a trust score and explains its factors. | Heuristic in-memory scoring; requires host identity and abuse controls. |

## AI infrastructure

| Engine | Status | What works | Boundary |
| --- | --- | --- | --- |
| `PromptEngine` | Implemented | Resolves registered/built-in templates, serializes supplied roles and normalizes prompt text. | Does not call a model. |
| `PromptOptimizationEngine` | Partial | Scores prompt features and proposes deterministic shortening/clarity edits. | Rule based; no model-quality evaluation. |
| `ModelSelectionEngine` | Partial | Selects from a local capability table using declared speed, cost and vision requirements. | Catalogue is static and must be updated/verified by the host. |
| `EmbeddingEngine` | Partial | Produces normalized deterministic 128-element feature-hash vectors. | Not semantically meaningful model embeddings; use an embedding provider for semantic search. |
| `RetrievalEngine` | Partial | Ranks supplied text records using token overlap and returns top results. | Lexical retrieval only; corpus must be provided by the caller. |
| `VectorSearchEngine` | Partial | Indexes vectors and searches by cosine similarity. | In-memory index with no ANN index or persistence. |
| `ToolCallingEngine` | Implemented | Calls only registered tools after policy authorization. | Caller must provide trusted context and matching capability policies. |
| `FunctionCallingEngine` | Implemented | Calls only registered functions after policy authorization. | Caller must provide trusted context and matching capability policies. |
| `GuardrailEngine` | Partial | Detects configured regex rules, validates context and redacts matches. | Pattern filters do not replace authorization, output review or a complete DLP system. |
| `EvaluationEngine` | Partial | Computes lexical overlap and compares candidate outputs using declared metrics. | Not a semantic quality judge or safety evaluation suite. |

## Business intelligence

| Engine | Status | What works | Boundary |
| --- | --- | --- | --- |
| `CRMIntelligenceEngine` | Partial | Computes invoice value/job count, segments customers and estimates churn from last activity. | Assumes WorkCore-style tables; recency scoring is a heuristic and tenant filters need host mapping. |
| `OperationsEngine` | Partial | Computes job throughput and offers data-driven operational suggestions. | Host schema and domain-specific optimization are required. |
| `DispatchEngine` | Partial | Queues work, processes it through `ExecutionEngine` and records terminal results. | Queue and state are in-memory; production dispatch needs durable workers. |
| `FinancialInsightEngine` | Partial | Computes trailing revenue, current cash flow and a labeled net-margin proxy from mapped tables. | Requires host invoices/expenses schema; gross margin stays unavailable without cost-of-goods data. |
| `AnalyticsEngine` | Partial | Computes numeric summaries and queries event trends/dashboard aggregates. | Dashboard requires mapped event schema; trends are counts, not causal analysis. |
| `ComplianceEngine` | Partial | Checks supplied records against registered regulation rules and records violations. | Rule catalogue and legal interpretation must be maintained by the host. |
| `AuditEngine` | Partial | Persists audit entries and retrieves matching trails. | Requires host `audit_logs` schema and tenant scoping. |
| `MonitoringEngine` | Partial | Stores actual metric samples, reports unknown/operational/degraded status and detects thresholds. | In-memory measurements only; no probes, exporter or alert delivery. |
| `AutomationEngine` | Partial | Registers schedules and routes each manual trigger through `ExecutionEngine` and Policy Engine. | In-memory/manual trigger only; durable timers and trusted schedule context are host responsibilities. |
| `BusinessBuilderEngine` | Partial | Generates registered vertical templates, validates configuration and records provisioning readiness. | Produces a handoff record; it does not deploy a live business or provision external services. |

## Registration and known limits

The Laravel provider scopes the 77 ordinary engine bindings to the request/job lifecycle. `ExecutiveEngine`, `CognitiveOrchestrator` and `WorldModelEngine` each have one scoped concrete binding shared by their two interface aliases. Mutable histories and queues are therefore not shared as Laravel singletons.

Several cache/database engines still use host schema assumptions or non-tenant cache keys. They are marked `Partial`; the host must map schemas, enforce tenant filters and choose durable storage before using them with real tenant data. Statistical, lexical and rule-based behavior is explicitly bounded. A model-backed quality claim is made only for the optional proposal adapter, and its result remains subject to the Policy Engine.
