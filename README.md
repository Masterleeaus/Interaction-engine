# Titan Zero Interaction Engine

> **A governed interaction runtime that turns conversation, context and intent into adaptive workflows and authorised business actions — online or offline.**

Titan Zero Interaction Engine is the interaction and workflow execution layer for intelligent business systems. It connects chat, voice, mobile, desktop and API experiences to structured workflows, local intelligence, business capabilities and policy-controlled execution.

The engine is designed around a simple boundary: **understanding what a user wants is not the same as having authority to act.** Interaction, reasoning, recommendation, approval and execution remain distinct stages so intelligent interfaces can become more capable without silently becoming more powerful.

## What it does

The engine combines five major layers:

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

The Universal Wizard Engine converts schema-defined business processes into resumable, governed interactions.

It provides:

- definition and template registries;
- step validation;
- persistent session state;
- conditional navigation;
- contextual guidance;
- renderer contracts;
- command mapping;
- resumable sessions;
- offline continuation;
- capability-aware execution.

A workflow can therefore move between conversational and structured interaction without losing its state, policy context or execution boundary.

## Adaptive workflow catalogue

The repository contains a versioned catalogue of executable workflow templates spanning multiple operational domains, including:

- field services;
- commerce and inventory;
- property and facilities;
- trades and construction;
- healthcare and allied health;
- hospitality and events;
- retail and wholesale;
- assurance and corrective-action workflows.

Templates carry lifecycle metadata, governance rules, compatibility requirements and readiness state. Workflows whose required host capabilities are unavailable remain explicit drafts rather than pretending to be executable.

Representative governed workflows include high-value refund approval, inventory write-offs, stocktake variances, customer-approved job variations, property maintenance, practical completion, defect rectification, intake consent, event readiness, store transfers and supplier receiving discrepancies.

## Local Intelligence

Interaction Engine includes a local intelligence layer capable of operating without a cloud model.

Its deterministic and hybrid foundations include:

- intent extraction;
- entity extraction;
- decision trees;
- behavioural memory;
- temporal reasoning;
- prediction;
- adaptive weighting;
- planning primitives;
- synchronisation deltas;
- hybrid local/cloud reasoning paths.

Cloud intelligence is optional. The system can continue interpreting and progressing supported workflows locally when external model access is unavailable or undesirable.

## Cognitive event ledger

The engine records the evidence chain between intelligence and real outcomes:

```text
observation
    ↓
recommendation / prediction
    ↓
user decision
    ↓
command
    ↓
outcome
    ↓
prediction score
```

Cognitive events are immutable, tenant-scoped and idempotent. Recommendations are not treated as confirmed behaviour until a real user action, executed command or observed outcome provides evidence.

This gives the system a foundation for learning from outcomes without confusing model inference with fact.

## Authority and execution controls

Every executable capability requires an explicit `CapabilityPolicy`.

Unregistered capabilities fail closed. Policies can distinguish between operations that may execute automatically, operations requiring approval and operations reserved for humans.

The same authority controls apply to online commands and replayed offline commands.

Key controls include:

- explicit capability registration;
- human-only operations;
- approval-required commands;
- tenant and actor validation;
- device identity;
- evidence requirements;
- correlation and idempotency lineage;
- policy-protected command dispatch;
- cross-tenant rejection.

The result is progressive automation without treating autonomy as an all-or-nothing switch.

## Offline-first device companion

The TypeScript device companion allows supported workflows to continue without continuous network access.

Core components include:

- `IndexedDbStore`
- `WizardDraftStore`
- `EncryptedCommandOutbox`
- `OfflineSyncClient`
- `LocalLanguageEngine`

Queued commands preserve tenant, user and device context inside AES-256-GCM encrypted envelopes. Unsynchronised records are retained until successfully reconciled rather than being silently discarded.

Offline replay passes through the same capability policies used by online execution.

## 80-engine capability library

The runtime includes 80 engine contracts with matching implementations across eight capability domains.

These engines provide reusable deterministic building blocks beneath higher-level workflows and intelligence. They are registered through Laravel's service container and can be composed without forcing every business interaction through a remote language model.

## Business-system boundary

Interaction Engine does not bypass the host application's business logic.

A capability registry and adapter layer map approved interaction commands onto host services for operations such as:

- customers;
- quotes;
- jobs;
- job completion;
- invoices;
- payments.

The host business system remains the operational mutation authority. Interaction Engine determines how a workflow is understood, progressed and governed; the configured business capability determines how an approved operation is performed.

## Security model

Security defaults are intentionally conservative:

- cloud AI is disabled by default;
- offline commands require tenant and device identity;
- device commands use AES-256-GCM encryption;
- local PHP command envelopes use XChaCha20-Poly1305 with integrity protection;
- executable capabilities require policy registration;
- online and replayed commands share authority controls;
- authenticated tenant context overrides untrusted request tenant data;
- cross-tenant session and command access is rejected;
- idempotency protects replayed commands from duplicate execution.

## API

Authenticated interaction APIs are exposed under `/api/interaction`.

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

## Architecture

The repository is structured around distinct concerns rather than one monolithic interaction controller:

```text
Interaction Engine
├── interactions/        Compatibility interaction runtime
├── wizards/             Canonical executable workflow definitions
├── templates/           Versioned governed workflow catalogue
├── resources/ts/offline Device-side offline runtime
├── resources/openapi    API contract
├── tests/               Runtime and workflow verification
├── docs/                Architecture and integration documentation
└── reports/             Capability and compatibility evidence
```

New operational workflows are defined through the canonical wizard runtime while the compatibility interaction runtime allows older definitions to migrate incrementally.

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

Laravel 10, 11 and 12 compatible components are supported by the module architecture.

## Installation

```bash
composer install
composer dump-autoload
php artisan vendor:publish --tag=interaction-config
php artisan vendor:publish --tag=interaction-definitions
php artisan migrate
```

For Nwidart deployments, install the package under the host application's module structure and enable `InteractionEngine` using the host module workflow.

### Core configuration

```dotenv
INTERACTION_OFFLINE_ENABLED=true
INTERACTION_LOCAL_INTELLIGENCE=true
INTERACTION_LOCAL_MIN_CONFIDENCE=0.65
INTERACTION_WIZARD_SESSION_TTL=86400
INTERACTION_OUTBOX_SECRET=${APP_KEY}
INTERACTION_AI_ENABLED=false
```

Cloud reasoning can be enabled separately when required; it is not a prerequisite for local deterministic operation.

## Verification

Run the repository verification suite:

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
