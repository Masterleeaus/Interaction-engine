# Titan Zero Interaction Engine — Cumulative Phase 10

A Laravel/Nwidart-compatible execution module that merges the Phase 8 Interaction Engine with the Phase 9 **80 Core Engines** library and adds the document-defined **Universal Wizard Engine** and **Local Intelligence** foundation.

## What is included

- **Universal wizard runtime**: definition registry, step validation, state, conditional navigation, guidance, renderers, command mapping and resumable sessions.
- **Seven executable business wizards**: New Customer, Create Quote, Create Job, Complete Job, Create Invoice, Incident Response and Inspection/Corrective Action.
- **Versioned template catalogue**: seven ready templates, three explicit drafts, semantic versions, lifecycle status, governance metadata and fail-closed WorkCore compatibility reporting.
- **WorkCore boundary**: capability registry and adapter mappings for customers, quotes, jobs, job completion, invoices and payments.
- **Local intelligence**: deterministic intent/entity extraction, decision trees, behavioural memory, temporal reasoning, prediction, adaptive weighting, sync deltas and hybrid reasoning.
- **Device offline companion**: TypeScript IndexedDB storage, AES-256-GCM encrypted command outbox and sync client.
- **80-engine library**: 80 contracts and 80 matching implementations across eight domains, registered as Laravel singletons.
- **Compatibility runtime**: the original `interactions/` compiler/runtime remains available while new work moves to the canonical `wizards/` runtime.

## Architecture

```text
Chat / Mobile / Tablet / Desktop / Voice / API
                         |
               Universal Wizard Engine
 Registry -> Validation -> State -> Guidance -> Renderer
                         |
            Capability + WorkCore Command
                  /                 \
          Online dispatch       Offline outbox
              WorkCore       IndexedDB + AES-GCM
                         |
                 Local Intelligence
       Perception / Memory / Reasoning / Planning / Learning
                         |
                  80 Core Engines
```

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for boundaries and data flow.

## Requirements

- PHP 8.2+
- Laravel 10, 11 or 12 compatible Illuminate components
- PHP Sodium extension
- Node.js 20+ and TypeScript when rebuilding the device companion
- A configured WorkCore service/model boundary for live writes

Cloud AI is optional. Local deterministic operation does not require an API key.

## Installation

Install this directory as a local Composer package/module, then run:

```bash
composer install
composer dump-autoload
php artisan vendor:publish --tag=interaction-config
php artisan vendor:publish --tag=interaction-definitions
php artisan migrate
```

For a Nwidart module deployment, place the package under the host application's module directory and enable `InteractionEngine` using the host module workflow.

### Environment

```dotenv
INTERACTION_OFFLINE_ENABLED=true
INTERACTION_LOCAL_INTELLIGENCE=true
INTERACTION_LOCAL_MIN_CONFIDENCE=0.65
INTERACTION_WIZARD_SESSION_TTL=86400
INTERACTION_OUTBOX_SECRET=${APP_KEY}
INTERACTION_AI_ENABLED=false
```

Enable cloud AI only after installing `openai-php/client` and configuring a key:

```dotenv
INTERACTION_AI_ENABLED=true
INTERACTION_AI_API_KEY=your-key
INTERACTION_AI_MODEL=gpt-4o-mini
```

## WorkCore configuration

The adapter accepts either callable services, service classes or model classes. Configure the host application's `config/interaction.php`:

```php
'adapter_config' => [
    'services' => [
        'customer' => App\WorkCore\Customers\CreateCustomer::class,
        'quote' => App\WorkCore\Quotes\CreateQuote::class,
        'job' => App\WorkCore\Jobs\CreateJob::class,
        'job_completion' => App\WorkCore\Jobs\CompleteJob::class,
        'invoice' => App\WorkCore\Finance\CreateInvoice::class,
        'payment' => App\WorkCore\Finance\RecordPayment::class,
    ],
],
```

A service class may expose `create()` or `handle()`. The job-completion service may expose `complete()`, `handle()` or `update()`.

## API surfaces

All routes use `api` and `auth:sanctum` middleware under `/api/interaction`.

```text
GET  /templates
GET  /templates/{templateId}
GET  /wizards
POST /wizards/{wizardId}/start
GET  /wizard-sessions/{sessionId}
POST /wizard-sessions/{sessionId}/steps
POST /local-intelligence/process
POST /offline-commands
```

The offline-command endpoint uses the command UUID as a 30-day idempotency key, validates tenant/device metadata and dispatches through the same policy-protected WorkCore command bus.

The complete API contract is in [`resources/openapi.yaml`](resources/openapi.yaml).

## Device offline companion

The TypeScript package is under `resources/ts/offline` and has no third-party runtime dependencies.

```bash
npm run build
npm test
```

Core classes:

- `IndexedDbStore`
- `WizardDraftStore`
- `EncryptedCommandOutbox`
- `OfflineSyncClient`
- `LocalLanguageEngine`

Every queued command carries tenant, user and device identifiers inside an AES-256-GCM encrypted envelope. Unsynchronised records are never removed automatically.

## Verification

```bash
php bin/verify.php
```

Or run each layer separately:

```bash
php tests/run.php
php tests/template_catalogue_run.php
php tests/assurance_workflows_run.php
npm test
```

The verification suite checks PHP syntax, canonical filenames, all 80 engine pairs, JSON definitions, schema validation, offline and online wizard execution, session restoration, local intelligence, WorkCore mappings and device-side encryption.

## Definition directories

- `templates/`: versioned, governed workflow packages and lifecycle metadata.
- `wizards/`: canonical schema-defined Universal Wizard definitions.
- `interactions/`: compatibility definitions for the original Interaction Runtime.

New business actions should be added to `wizards/`. Existing `interactions/` definitions can be migrated incrementally without breaking older routes.

## Security defaults

- Cloud AI is disabled by default.
- Offline commands require tenant and device identity.
- Device commands are encrypted with AES-256-GCM.
- PHP-local command envelopes use XChaCha20-Poly1305 and HMAC integrity.
- Online and resynchronised commands pass through capability policies.
- The API rejects cross-tenant offline commands when the authenticated user's tenant is known.

## Honest readiness boundary

This archive is an executable, tested **foundation module**, not proof of production deployment inside a particular MagicAI/WorkCore installation. The exact host models, permissions, authentication, queues and service classes must be mapped and integration-tested in the target Laravel application. Several of the 80 engines are deterministic baseline implementations rather than trained ML models or a local LLM.

## Phase 11 cognitive event ledger

Phase 11 adds the evidence chain required for outcome-based intelligence:

```text
observation → recommendation/prediction → user decision → command → outcome → prediction score
```

Cognitive events are immutable, tenant scoped and idempotent. Device events are encrypted before storage and replayed by correlation ID and sequence. Recommendations are never treated as confirmed user behaviour until a user action, command execution or real outcome confirms them.

See `CHANGELOG_PHASE11_COGNITIVE_EVENTS.md` for the migration and API details.

## Phase 14 authority controls
All executable capabilities now require an explicit `CapabilityPolicy`; unregistered capabilities fail closed. Human-only and approval-required commands are enforced at the command bus, including offline replay. Configure `INTERACTION_APPROVAL_SECRET` with a dedicated server-side secret before production use.


## Assurance template upgrade

Incident Response and Inspection/Corrective Action now have executable, offline-capable wizards with fail-closed evidence and approval governance. Authenticated tenant identity overrides request tenant data, and wizard sessions cannot be read or advanced by another tenant or actor.

Package-level readiness is separate from host activation. The assurance capabilities are intentionally not registered against an invented WorkCore implementation. Review [`docs/ASSURANCE_WORKFLOWS.md`](docs/ASSURANCE_WORKFLOWS.md) and [`reports/workcore-compatibility.json`](reports/workcore-compatibility.json) before connecting live persistence.

Template discovery is available through the authenticated API and `php artisan interaction:templates`.
