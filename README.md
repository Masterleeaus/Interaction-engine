# Titan Zero Interaction Engine

> **A governed interaction runtime that turns conversation, context and intent into adaptive workflows and authorised business actions — online or offline.**

Titan Zero Interaction Engine is the interaction and workflow execution layer for intelligent business systems. It connects chat, voice, mobile, desktop and API experiences to structured workflows, local intelligence, business capabilities and policy-controlled execution.

The engine is designed around a simple boundary: **understanding what a user wants is not the same as having authority to act.** Interaction, reasoning, recommendation, approval and execution remain distinct stages so intelligent interfaces can become more capable without silently becoming more powerful.

## Core architecture

```text
Chat / Voice / Mobile / Tablet / Desktop / API
                         |
                         v
                Interaction Runtime
                         |
                         v
              Universal Wizard Engine
      Registry / Validation / State / Guidance / UI
                         |
                         v
                  Local Intelligence
   Intent / Entities / Memory / Prediction / Reasoning
                         |
                         v
             Capability + Authority Layer
      Policy / Approval / Evidence / Command Mapping
                         |
                 +-------+-------+
                 |               |
                 v               v
          Online execution   Offline outbox
          Business systems   Encrypted device state
                 |               |
                 +-------+-------+
                         v
                Outcomes + Evidence
```

## Universal Wizard Engine

The Universal Wizard Engine converts schema-defined business processes into resumable, governed interactions. It provides definition and template registries, step validation, persistent session state, conditional navigation, contextual guidance, renderer contracts, command mapping, offline continuation and capability-aware execution.

A workflow can move between conversational and structured interaction without losing state, policy context or execution boundaries.

## Adaptive workflow catalogue

The versioned workflow catalogue spans field services, commerce and inventory, property and facilities, trades and construction, healthcare and allied health, hospitality and events, retail and wholesale, and assurance/corrective-action workflows.

Templates carry lifecycle metadata, governance rules, compatibility requirements and readiness state. Workflows whose required host capabilities are unavailable remain explicit drafts rather than pretending to be executable.

## Local Intelligence

The engine includes a local intelligence layer capable of operating without a cloud model. Its deterministic and hybrid foundations include intent and entity extraction, decision trees, behavioural memory, temporal reasoning, prediction, adaptive weighting, planning primitives, synchronisation deltas and hybrid local/cloud reasoning paths.

Cloud intelligence is optional. Supported workflows can continue locally when external model access is unavailable or undesirable.

## Cognitive event ledger

The engine records the evidence chain between intelligence and real outcomes:

```text
observation → recommendation / prediction → user decision → command → outcome → prediction score
```

Cognitive events are immutable, tenant-scoped and idempotent. Recommendations are not treated as confirmed behaviour until a real user action, executed command or observed outcome provides evidence.

## Authority and execution controls

Every executable capability requires an explicit `CapabilityPolicy`. Unregistered capabilities fail closed. Policies can distinguish automatic operations, approval-required operations and human-only operations.

Key controls include explicit capability registration, tenant and actor validation, device identity, evidence requirements, correlation and idempotency lineage, policy-protected dispatch and cross-tenant rejection. The same controls apply to online commands and replayed offline commands.

## Offline-first device companion

The TypeScript device companion allows supported workflows to continue without continuous network access. Core components include `IndexedDbStore`, `WizardDraftStore`, `EncryptedCommandOutbox`, `OfflineSyncClient` and `LocalLanguageEngine`.

Queued commands preserve tenant, user and device context inside AES-256-GCM encrypted envelopes. Unsynchronised records remain until successfully reconciled, and offline replay passes through the same capability policies as online execution.

## 80-engine capability library

The runtime includes 80 engine contracts with matching implementations across eight capability domains. These reusable deterministic building blocks sit beneath higher-level workflows and intelligence and are registered through Laravel's service container.

## Business-system boundary

Interaction Engine does not bypass host business logic. A capability registry and adapter layer map approved interaction commands onto host services for operations such as customers, quotes, jobs, job completion, invoices and payments.

The host business system remains the operational mutation authority.

## Security model

Security defaults are conservative:

- cloud AI disabled by default;
- tenant and device identity required for offline commands;
- AES-256-GCM device encryption;
- XChaCha20-Poly1305 local PHP command envelopes;
- explicit policy registration for executable capabilities;
- shared authority controls for online and replayed commands;
- authenticated tenant context overriding untrusted request tenant data;
- cross-tenant session and command rejection;
- idempotency protection against duplicate replay.

## API

Authenticated interaction APIs are exposed under `/api/interaction`:

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

The complete contract is defined in `resources/openapi.yaml`.

## Repository structure

```text
Interaction Engine
├── interactions/        Compatibility interaction runtime
├── wizards/             Canonical executable workflow definitions
├── templates/           Versioned governed workflow catalogue
├── resources/ts/offline Device-side offline runtime
├── resources/openapi    API contract
├── tests/               Runtime and workflow verification
├── docs/                Architecture, integration and historical evidence
└── reports/             Capability and compatibility evidence
```

Historical build reports, import records and source provenance are retained under `docs/history/` so development evidence remains available without obscuring the active product structure.

## Technology

- PHP 8.2+
- Laravel-compatible Illuminate components
- Nwidart module integration
- TypeScript
- IndexedDB
- PHP Sodium
- AES-256-GCM
- XChaCha20-Poly1305
- OpenAPI
- JSON/schema-driven workflow definitions

## Installation

```bash
composer install
composer dump-autoload
php artisan vendor:publish --tag=interaction-config
php artisan vendor:publish --tag=interaction-definitions
php artisan migrate
```

### Core configuration

```dotenv
INTERACTION_OFFLINE_ENABLED=true
INTERACTION_LOCAL_INTELLIGENCE=true
INTERACTION_LOCAL_MIN_CONFIDENCE=0.65
INTERACTION_WIZARD_SESSION_TTL=86400
INTERACTION_OUTBOX_SECRET=${APP_KEY}
INTERACTION_AI_ENABLED=false
```

## Verification

```bash
php bin/verify.php
```

Individual layers can also be exercised independently:

```bash
php tests/run.php
php tests/template_catalogue_run.php
php tests/assurance_workflows_run.php
php tests/commerce_vertical_workflows_run.php
npm test
```

Verification covers PHP syntax, canonical engine pairs, workflow definitions, schema validation, online and offline execution, session restoration, local intelligence, host mappings and device-side encryption.

## Design principles

**Interaction is continuous.** Conversation, UI state and workflow state belong to the same operating context.

**Intelligence is not authority.** A recommendation or prediction never grants itself permission to execute.

**Offline is a runtime state, not a failure mode.** Supported workflows retain state and queue governed actions until connectivity returns.

**Evidence precedes learning.** Recommendations become behavioural evidence only after actual decisions or outcomes confirm them.

**Host systems remain authoritative.** The engine integrates with operational capabilities rather than silently replacing their business rules.

**Cloud intelligence is optional.** Local deterministic capability remains useful without external model access.

## Status

Titan Zero Interaction Engine is an executable, tested foundation for adaptive, governed business interaction. Host-specific models, authentication, queues, permissions and service mappings must be integration-tested for each deployment, and deterministic baseline engines should not be confused with trained ML models.
