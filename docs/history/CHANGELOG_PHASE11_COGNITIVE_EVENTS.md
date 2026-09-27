# Phase 11 — Cognitive Event and Outcome Foundation

## Added

- Canonical tenant-scoped cognitive event envelope with UUID, correlation, sequence, provenance, evidence, confidence, policy, privacy and model metadata.
- Immutable event types covering observations, recommendations, corrections, approvals, commands, outcomes, prediction scoring and model updates.
- Eloquent and in-memory idempotent event stores.
- Observation, decision and outcome recorders.
- Prediction-to-outcome linker using Brier scoring.
- Tenant-isolated correlation timelines.
- Device-side AES-256-GCM cognitive event outbox with deterministic replay ordering.
- API endpoints for outcome submission, correction recording and correlation timelines.
- Wizard and command path event emission.

## Corrected

`LocalBrain::process()` no longer writes its own recommendation into confirmed behavioural history. Only `confirmAction()`, command execution, corrections or observed outcomes may become learning evidence.

## Database

Run the new migration:

```bash
php artisan migrate
```

Creates `interaction_cognitive_events` with tenant-first compound keys and correlation, subject, event-type and user indexes.

## Verification

```bash
composer test
npm test
```
