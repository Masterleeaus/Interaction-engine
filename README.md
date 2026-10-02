![Titan Interaction Runtime — GOVERNED WORKFLOWS · ONLINE + OFFLINE](docs/images/portfolio-banner.svg)

<div align="center">

# Titan Zero Interaction Engine

**A governed interaction runtime for adaptive workflows and authorised business actions, online or offline.**

</div>

> **Status: module foundation with recorded standalone verification; host deployment readiness remains environment-specific.** The repository includes a cumulative build report and verification scripts. Its report explicitly lists host tenancy, permissions, queues, database compatibility, PWA integration, provider integration, and production-load testing as remaining destination-system checks.

## What it provides

The Interaction Engine connects chat, voice, mobile, desktop, and API experiences to structured workflows, local intelligence, business capabilities, and policy-controlled execution. Understanding user intent does not grant authority: recommendation, approval, and execution remain separate stages.

## Core capabilities

- Schema-driven Universal Wizard Engine with resumable sessions and conditional flows
- Versioned workflow and template catalogues with readiness metadata
- Local and hybrid intelligence components
- Tenant-scoped cognitive events and evidence lineage
- Capability policies, actor context, idempotency, and fail-closed execution
- TypeScript offline companion using IndexedDB and encrypted command envelopes
- OpenAPI contract in `resources/openapi.yaml`

## Architecture

```text
Chat / Voice / Mobile / Desktop / API
                  |
                  v
        Interaction Runtime
                  |
                  v
          Wizard + Templates
                  |
                  v
        Local Intelligence
                  |
                  v
       Policy + Capability Layer
                  |
          +-------+-------+
          |               |
          v               v
    Online host       Offline outbox
          |               |
          +-------+-------+
                  v
          Outcomes + Evidence
```

## Technology

- PHP 8.2+
- Laravel-compatible Illuminate components
- TypeScript, IndexedDB
- PHP Sodium and AES-GCM
- OpenAPI and JSON/schema-driven definitions

## Installation

The module is intended for a compatible Laravel host. Verify the host version, dependency constraints, migrations, service provider registration, auth/tenant context, queue/cache setup, and route exposure before deployment.

```bash
composer install
composer test
npm install
npm test
```

The repository defines Composer and npm test scripts. A prior cumulative build report records standalone syntax, engine-pair, schema, workflow, offline encryption, online command-dispatch, and TypeScript checks. Those historical results are not a substitute for rerunning tests on the current commit or validating a destination host.

## Integration requirements

A destination system must validate:

- host-specific model and service mappings;
- tenant and permission semantics;
- authentication, API exposure, queues, cache, scheduler, and database migration compatibility;
- browser/PWA integration and cloud provider configuration;
- concurrency, load, and production failure behaviour.

The host business system remains the authority for operational mutations.

## Repository map

- `interactions/`, `wizards/`, `templates/` — interaction and workflow definitions
- `resources/ts/` — device-side offline companion
- `resources/openapi.yaml` — API contract
- `tests/` — runtime and workflow checks
- `docs/`, `reports/` — architecture and verification evidence

## Security principles

- Cloud AI is disabled by default.
- Unregistered capabilities fail closed.
- Tenant and actor context are required for consequential execution.
- Offline replay uses the same policy checks as online execution.
- Unsynchronised records remain queued until reconciliation succeeds.

## License

The Composer package declares a proprietary license. Obtain the appropriate rights before external distribution or reuse.

## Banner

No verified wide banner asset was found in this repository; the centered typographic title is used until one is added.
