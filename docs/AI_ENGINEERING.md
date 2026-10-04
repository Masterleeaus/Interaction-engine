# AI engineering map

Titan Zero Interaction Engine is an executable workflow and governance foundation with deterministic local intelligence and an optional cloud-model adapter. It is not presented as a trained model, a local LLM, or an autonomous agent that can grant itself authority.

## The problem this repository addresses

Business interfaces often stop at intent recognition: they can suggest an action, but the path from suggestion to a durable business mutation is left implicit. This repository makes that path explicit and replayable, including tenant and actor context, approval, evidence, idempotency and offline recovery.

The central design rule is:

```text
understanding -> recommendation -> human decision / approval -> governed command -> host outcome
```

An intent or prediction never becomes permission by itself.

## Runtime path

1. `LocalBrain::process()` calls the PHP `LocalLanguageEngine` for deterministic intent and entity extraction.
2. `HybridReasoner` applies registered rules and lowers confidence when required context is missing. `DecisionTreeEngine` provides a separate schema-driven branch evaluator.
3. `BehavioralMemory`, `TemporalIntelligence` and `PredictiveCompletionEngine` provide suggestions from confirmed local history. `confirmAction()` is the point at which an observed user action can become behavioural evidence.
4. `UniversalWizardEngine` validates a structured workflow and `CommandMapper` creates a capability command with tenant, actor, device, correlation and idempotency metadata.
5. `PolicyEngine` evaluates the command against a `CapabilityPolicy`. It can enforce human-only actions, signed approval, delegated scopes, numeric limits, fresh authentication and tenant boundaries.
6. `CommandBus` dispatches only registered capabilities to the host-facing adapter. `WorkCoreAdapter` translates the approved command; the host business system remains the mutation authority.
7. Offline devices persist encrypted drafts and commands through the TypeScript IndexedDB companion. `/api/interaction/offline-commands` rechecks tenant, idempotency and policy during replay.
8. Cognitive events connect observations, recommendations, user decisions, commands and outcomes so later learning is based on evidence rather than unconfirmed suggestions.

## Implemented capability map

| Capability | Implementation | What is actually implemented |
| --- | --- | --- |
| Local language understanding | `src/LocalIntelligence/Language/LocalLanguageEngine.php`, `resources/ts/offline/local-language.ts` | Pattern matching for business intents plus lightweight extraction of customers, services, dates, times, addresses and amounts. |
| Local reasoning | `src/LocalIntelligence/Reasoning/HybridReasoner.php`, `src/LocalIntelligence/Decision/DecisionTreeEngine.php` | Rule-adjusted confidence, evidence, alternatives and clarification decisions; no claim of statistical calibration. |
| Memory and prediction | `src/LocalIntelligence/Memory/BehavioralMemory.php`, `TemporalIntelligence.php`, `PredictiveCompletionEngine.php` | Confirmed action transitions, temporal suggestions and persisted local-memory hooks. Recommendations do not mutate host state. |
| Retrieval and vectors | `src/Engines/AIInfrastructure/Implementations/{EmbeddingEngine,RetrievalEngine,VectorSearchEngine}.php` | Hash-based fixed-size vectors, token-overlap retrieval and in-memory cosine search. These are deterministic indexing baselines, not trained semantic embeddings. |
| Evaluation and prompt support | `EvaluationEngine.php`, `PromptEngine.php`, `PromptOptimizationEngine.php` | Word-overlap scoring and rule-based prompt cleanup/scoring. These are transparent heuristics, not model-quality benchmarks. |
| Tool/function dispatch | `ToolCallingEngine.php`, `FunctionCallingEngine.php`, `CapabilityRegistry.php` | Explicit name-to-callable registries. There is no hidden model loop or unrestricted code execution. |
| Optional cloud AI | `src/AI/OpenAIService.php`, `src/Resolver/Resolvers/AIResolver.php` | Optional OpenAI-backed question resolution when enabled and configured. `NullAIService` fails closed when cloud AI is disabled. Cloud output does not bypass policy or command dispatch. |
| Authority and safety | `src/Policy/PolicyEngine.php`, `src/Authority/*`, `src/Command/CommandBus.php` | Default-deny capability decisions, approval signatures, role/scope/limit checks, tenant checks and policy callbacks before execution. |
| Evidence lineage | `src/Cognition/*`, `src/Events/*`, `src/Http/Controllers/CognitiveEventController.php` | Tenant-scoped cognitive events and outcome links for audit and later learning. |
| Agent-oriented building blocks | `src/Engines/{Planning,Executive,Cognitive,Memory,Learning}/*` | Contract-driven deterministic planning, routing, decision, memory and learning primitives. The 80-engine library is a replaceable baseline library, not 80 trained agents or models. |

## Engineering tradeoffs

- Local heuristics keep supported interactions usable without network access and are easy to inspect, test and replay, but they do not understand arbitrary language like a general-purpose model.
- The cloud adapter can improve open-ended question resolution, but it is opt-in, requires a host credential and is deliberately outside the authority boundary.
- Host adapters preserve the destination system's domain rules instead of duplicating them here; this makes the package portable, but connected-host readiness cannot be proven from this repository alone.
- Offline replay is conservative: unsynchronised records remain available for retry or resolution, and replayed commands are subject to the same policy checks as online commands.
- The 80-engine library favours stable contracts and deterministic defaults. Several implementations are intentionally baseline strategies behind interfaces, not production claims about model quality or scale.

## Evidence and reproducible checks

From the current repository tree:

- `tests/run.php` covers core runtime, policy, local-intelligence, offline and host-mapping behaviour.
- `tests/template_catalogue_run.php`, `tests/assurance_workflows_run.php` and `tests/commerce_vertical_workflows_run.php` cover the executable/draft catalogue boundary and workflow governance.
- `tests/ts/offline.test.js` covers encrypted outbox round trips, local language extraction, sync conflicts and cognitive-event replay.
- `scripts/authority-policy-eval.php` runs 31 fixed policy scenarios; the committed report is `eval-results/authority-policy-latest.md`.
- `.github/workflows/verify.yml` runs `php bin/verify.php`, PHP syntax checks and JSON validation after installing the declared Node.js/TypeScript toolchain.

Run the package checks with:

```bash
composer install
npm ci
php bin/verify.php
```

The repository currently records package-level evidence only. `reports/workcore-compatibility.json` intentionally reports zero connected-host-verified templates; deployment-specific models, permissions, migrations, queues, browser integration, provider integration and load behaviour remain host integration work.


