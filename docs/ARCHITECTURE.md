# Titan Zero Interaction and Local Intelligence Architecture

## 1. Architectural decision

Phase 9 contains every Phase 8 path and adds the 80-engine library, so Phase 9 is the cumulative filesystem base. The merge does not stack duplicate files. Updated patch artifacts were folded into canonical paths and then removed.

The package preserves the original Interaction Runtime for backward compatibility and adds a new canonical Universal Wizard Runtime for new business actions.

## 2. Runtime layers

### Interface layer

Chat, mobile, tablet, desktop, voice and API clients consume the same wizard definition and session state. `HybridRenderer` returns both conversational guidance and a structured view model.

### Universal Wizard layer

- `WizardRegistry`: discovers JSON/YAML definitions.
- `WizardDefinition`: immutable validated definition object.
- `WizardSession`: current step, accumulated data, context and event history.
- `WizardValidationEngine`: deterministic local field validation.
- `LocalGuidanceProvider`: concise offline guidance.
- `UniversalWizardEngine`: step processing, conditional skipping, completion and dispatch.
- `CacheWizardSessionStore`: server-side resumable session persistence.

### Command boundary

`CommandMapper` generates a UUID-bearing capability command with wizard, tenant, user, device and timestamp metadata.

Online completion dispatches through `CommandBusInterface`, where policy checks run before the `CapabilityRegistry` invokes `WorkCoreAdapter`.

Offline devices use the TypeScript `EncryptedCommandOutbox` and later submit to the idempotent `/offline-commands` endpoint.

### WorkCore boundary

`DomainAdapterInterface` prevents the wizard and cognitive systems from depending on concrete WorkCore models. The host may provide callable services, service classes or model classes.

Supported foundation capabilities:

- `crm.customer.create`
- `quotes.create`
- `jobs.create`
- `jobs.complete`
- `finance.invoice.create`
- `finance.payment.record`

## 3. Local Intelligence

`LocalBrain` combines independent deterministic modules:

1. **Perception** — local intent and entity extraction.
2. **Memory** — behavioural transition memory.
3. **Reasoning** — decision-tree and hybrid confidence evaluation.
4. **Planning** — predictive completion and temporal suggestions.
5. **Learning** — local reweighting and privacy-limited sync deltas.

The current embedding implementation is a deterministic hash vector suitable for stable local indexing and tests. It is not represented as equivalent to a trained semantic embedding model.

## 4. Device-first storage and sync

The TypeScript companion provides a generic storage boundary with `IndexedDbStore` as the browser implementation.

```text
Wizard draft -> IndexedDB
Command -> AES-256-GCM envelope -> IndexedDB outbox
Connectivity restored -> OfflineSyncClient -> authenticated sync endpoint
Server -> tenant check -> idempotency check -> policy -> WorkCore adapter
```

A successful sync marks the envelope as synced. Failed or conflicting commands remain stored for retry or user resolution.

## 5. 80-engine library

Eight domains contain ten contracts and ten implementations each:

- Executive
- Cognitive
- Memory
- Learning
- Planning
- Human Interaction
- AI Infrastructure
- Business Intelligence

All matching pairs are registered as Laravel singletons. Critical reasoning, retrieval, vector search, prediction, recommendation, planning, compliance and knowledge functions have deterministic implementations. Other engines remain replaceable baseline strategies behind stable contracts.

## 6. Definition compatibility

`interactions/` is the compatibility schema used by `InteractionCompiler` and the original Blade runtime.

`wizards/` is the new universal schema and should receive all new business actions. The compatibility runtime can be retired after its definitions and callers are migrated.

## 7. Trust boundaries

- The browser owns encrypted local drafts and outbox records.
- The API authenticates the user and checks tenant identity.
- The command bus owns policy enforcement.
- The WorkCore adapter owns domain-model translation.
- Cloud AI is an optional guidance source, never a required execution dependency.
