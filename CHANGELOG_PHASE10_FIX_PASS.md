# Fix Pass Changelog — InteractionEngine Phase 10 (Local Intelligence)

**Date**: July 29, 2026
**Starting point**: `TitanZero_InteractionEngine_Cumulative_Phase10_LocalIntelligence.zip`, previously scanned (see `InteractionEngine_Phase10_MissingCode_Scan.md`)
**Validation**: `php -l` on all 307 PHP files, full cross-reference of all 315 `use` imports plus every inline fully-qualified `::class` reference added during this pass, and the bundled test suite actually executed after every change (`php tests/run.php` — 16/16 passing throughout, not just at the end).

This archive started from a meaningfully better place than Phase 8/9 — no fragment files, no fabricated test claims, all 301 files already passed `php -l`. This changelog covers the six real gaps the scan found.

---

## 1. LocalIntelligence persistence — the big one

The scan's headline finding: `src/LocalIntelligence/` (`BehavioralMemory`, `TemporalIntelligence`, `PredictiveCompletionEngine`, `AdaptiveReweightingEngine`) held all state in plain PHP arrays on a request-scoped singleton, so nothing "learned" survived past the current HTTP request — despite a purpose-built `local_intelligence_memories` migration and `LocalIntelligenceMemory` Eloquent model already existing in the archive, unreferenced by anything.

**Fix**: built the missing connective layer.

- `src/LocalIntelligence/Storage/LocalIntelligenceMemoryStoreInterface.php` — a generic `get`/`all`/`put` contract scoped by tenant/user/device/type/key, matching the migration's schema.
- `src/LocalIntelligence/Storage/EloquentLocalIntelligenceMemoryStore.php` — the real implementation, backed by the `LocalIntelligenceMemory` model. Caught and fixed a real bug while writing it: Laravel's `where('column', null)` compiles to `column = NULL`, which never matches in SQL — the null-scope case needs `whereNull()` explicitly.
- `src/LocalIntelligence/Storage/NullLocalIntelligenceMemoryStore.php` — no-op default, so nothing breaks where no store is configured.
- `BehavioralMemory`, `TemporalIntelligence`, `PredictiveCompletionEngine`, `AdaptiveReweightingEngine` — each now writes through to the store on every record/observe call, and hydrates from the store once per request on first read for a given key (tracked via an internal "already hydrated" set, so it's one query per key per request, not one per call).
- `LocalBrain::createDefault()` — **left completely untouched**. It's what `tests/run.php` uses (no database in that harness), and every engine it constructs still defaults to `NullLocalIntelligenceMemoryStore`, so its behavior is unchanged.
- `LocalBrain::createWithPersistence()` — new factory for real use, wired into the service provider.

**A design correction made along the way**: my first draft baked `$userId` into `TemporalIntelligence`/`PredictiveCompletionEngine`/`AdaptiveReweightingEngine`'s constructors, matching how I'd initially wired the provider. That's a latent bug under Octane or any long-running-worker deployment — a singleton constructed once with one user's ID baked in would silently mix that user's temporal/prediction data into every other user's requests handled by the same worker afterward. Refactored all three to accept `$userId` per-call instead, exactly matching the pattern `BehavioralMemory` already used correctly.

## 2. Regression: `InteractionRuntime::start()` lost its null check

`InteractionRegistry::get()` returns `?InteractionDefinition`, but `start()` dereferenced `$definition->version` unconditionally. Restored the null check (present in the Phase 9 fix, dropped somewhere in this archive's regeneration):

```php
$definition = $this->registry->get($interactionId);
if ($definition === null) {
    throw new \RuntimeException("Interaction '{$interactionId}' not found.");
}
```

## 3. Wizard offline outbox was unreachable

The bug wasn't in `UniversalWizardEngine` — its nullable-`CommandBus` design (`null` → queue to `LocalCommandOutbox`, real bus → dispatch immediately) is correct and is exactly what the bundled tests exercise. The bug was in the **service provider**, which registered it with plain `$this->app->singleton(UniversalWizardEngine::class)`, letting Laravel always inject a real `CommandBus` (bound elsewhere in the same provider) regardless of actual connectivity — making the offline branch dead code in production.

**Fix**: register it with a factory that checks `OfflineDetector::isOffline()` (the same signal `InteractionRuntime` already uses correctly) and passes `null` or a real bus accordingly:

```php
$this->app->singleton(UniversalWizardEngine::class, function ($app): UniversalWizardEngine {
    $offline = config('interaction.offline.enabled', true) && $app->make(OfflineDetector::class)->isOffline();
    return new UniversalWizardEngine(..., $offline ? null : $app->make(CommandBusInterface::class));
});
```

This preserves the exact class design the tests validate (constructing with vs. without a bus) while fixing how the provider decides which to inject.

## 4. Executive/Cognitive/World split-state — same bug as Phase 9, different mechanism, still broken

This archive's attempt at consolidating the duplicate `ExecutiveEngine`/`CognitiveOrchestrator`/`WorldModel` concepts used a subclass bridge pattern (`class ExecutiveEngine extends \TitanZero\Engines\Executive\Implementations\ExecutiveEngine implements LegacyContract {}`) — a reasonable idea. But the provider still bound both interfaces independently via separate `singleton()` calls, so Laravel constructed two separate objects (one legacy-namespace subclass instance, one parent-class instance) with two separate internal states.

**Fix**: same approach as the Phase 9 fix — bind the real `TitanZero\Engines\...` concrete class once, then alias both the new-namespace interface and the legacy interface to that single instance:

```php
$this->app->singleton(\TitanZero\Engines\Executive\Implementations\ExecutiveEngine::class);
$this->app->singleton(\TitanZero\Engines\Executive\Contracts\ExecutiveEngineInterface::class,
    fn ($app) => $app->make(\TitanZero\Engines\Executive\Implementations\ExecutiveEngine::class));
$this->app->singleton(ExecutiveEngineInterface::class,
    fn ($app) => $app->make(\TitanZero\Engines\Executive\Implementations\ExecutiveEngine::class));
```
(repeated for `CognitiveOrchestrator` and `WorldModelEngine`)

The three bridge subclass files (`src/Executive/ExecutiveEngine.php`, `src/Cognitive/Orchestrator/CognitiveOrchestrator.php`, `src/World/WorldModel.php`) are now redundant — nothing constructs them once the real class satisfies both interfaces directly — and were deleted, along with their now-unused imports in the provider. Also removed the three engine names (`ExecutiveEngine`, `CognitiveOrchestrator`, `WorldModelEngine`) from `registerEngineLibrary()`'s generic domain loop, since binding them there too would recreate the exact problem being fixed.

## 5. 33 hollow engine methods fixed

The scan found 33 of 288 engine methods still returning hardcoded values regardless of input — down from 71 in the Phase 9 archive, but unevenly distributed (some engines, like `VectorSearchEngine`, had been genuinely rewritten; others, like `SentimentEngine`, were byte-identical to the original broken version).

Verified the interfaces for all 19 affected files are byte-identical to the Phase 9 archive's, then reused the real implementations already built and tested during the Phase 9 fix pass: `EvaluationEngine`, `ModelSelectionEngine`, `PromptOptimizationEngine`, `AnalyticsEngine`, `CRMIntelligenceEngine`, `OperationsEngine`, `CreativityEngine`, `ExplainabilityEngine`, `ReflectionEngine`, `GovernanceEngine`, `EmotionEngine`, `EmpathyEngine`, `PersonalityEngine`, `ResponseGenerationEngine`, `SentimentEngine`, `TrustEngine`, `SchedulingEngine`, plus `SemanticEngine::embed()` and `IntentEngine::getConfidence()` (targeted method-level fixes rather than whole-file replacements, since those two files already had other real methods).

One of these — `SemanticEngine::embed()` — now delegates to `EmbeddingEngineInterface` rather than duplicating logic. This archive's own `EmbeddingEngine` had already been independently rewritten with a real (and reasonably sophisticated) token-level hashing embedding, so the delegation picks that up automatically.

All 33 originally-flagged methods are now genuinely input-derived. Re-running the same detection heuristic after this pass: **0 remaining.**

## 6. Six of seven missing migrations added

The Phase 9 scan (which this archive was partly built from) found 7 tables referenced by `DB::table()` calls with no migration anywhere. This archive had already added one (`local_intelligence_memories`, for an unrelated purpose — see §1). Verified the remaining 6 engines' table/column usage is unchanged from what the Phase 9 migrations already handle, then added them with fresh timestamps so they sort after this archive's existing migrations:

| Table | Engine |
|---|---|
| `episodic_memory` | `EpisodicMemoryEngine` |
| `semantic_memory` | `SemanticMemoryEngine` |
| `audit_logs` | `AuditEngine` |
| `governance_logs` | `GovernanceEngine` |
| `user_actions` | `BehaviourLearningEngine` |
| `user_preferences` | `PreferenceLearningEngine` |

`BehaviourLearningEngine` in particular is worth noting: it already had defensive `try/catch` around its `DB::table('user_actions')` calls, silently falling back to request-local-only memory when the table doesn't exist — genuinely good defensive coding, but it meant the persistence it was clearly designed for was silently never happening. It needed nothing but the migration.

---

## Verification

Re-ran the full check after every change, not just at the end:

- `php -l` across all 307 PHP files: clean throughout
- `use`-statement and inline `::class` cross-reference: all resolve
- `php tests/run.php`: **16/16 passing** after every single fix in this changelog, including the persistence layer, the provider rewiring, and the bridge-class removal
- Hollow-method scan: 33 → 0

## What this doesn't cover

Same caveat as the Phase 8 and Phase 9 fix passes: no live Laravel app or real database was available in this environment, so the migrations and Eloquent queries are verified by syntax and manual schema cross-reference, not by actually running them against a database. Before merging into Titan Zero: `composer install`, run migrations for real, and exercise `LocalBrain::process()` across two separate requests for the same user to confirm the persistence fix in §1 actually produces the cross-request learning it's designed for.
