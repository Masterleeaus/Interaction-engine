# Assurance Workflows

The Interaction Engine includes two executable assurance workflows. They are **engine-ready** and fully covered by package tests, but they remain **inactive for live WorkCore mutation** until the host registers and verifies the required capabilities.

## Incident Response

- Template: `incident-response` version `1.0.0`
- Entry wizard: `incident_response_v1`
- Prepared capability: `assurance.incidents.report`
- Channels: chat, mobile, tablet and voice
- Offline: supported through the encrypted command outbox

The workflow records site and job context, incident classification, risk, people or environmental impact, immediate controls, evidence, witnesses, escalation, approval and declaration.

High and critical incidents fail closed unless they include evidence and explicit approval metadata:

```text
approval_id
approved_by
approved_at
```

Environmental-impact incidents also require an impact description. The engine prepares a command only after all schema and governance checks pass.

## Inspection and Corrective Action

- Template: `inspection-corrective-action` version `1.0.0`
- Entry wizard: `inspection_corrective_action_v1`
- Prepared capability: `assurance.inspections.complete`
- Channels: chat, mobile, tablet, desktop and voice
- Offline: supported through the encrypted command outbox

The workflow records inspection scope, checklist counts, findings, evidence, corrective actions, owner, due date, verification method, approval and declaration.

Failed findings require structured findings, evidence and corrective actions. Critical findings additionally require an owner, due date and explicit approval before completion.

## Authority boundary

The Interaction Engine does not write incident, inspection, finding or corrective-action tables directly. It validates the interaction, prepares an auditable command envelope and either:

1. dispatches it through the WorkCore command boundary when a verified online handler exists; or
2. stores it in the encrypted outbox for later replay.

Every assurance command carries:

```text
tenant_id
user_id
device_id
wizard_run_id
correlation_id
causation_id
idempotency_key
template_id
template_version
```

The authenticated user supplies tenant and actor authority. Request data cannot override an authenticated tenant. Wizard-session reads and step submissions require both the original tenant and actor to match.

## Replay and idempotency

The idempotency key is deterministic for the tenant, wizard run, capability and definition version. Online and offline execution therefore share the same command identity. Governance failures roll the final step back and never enqueue or dispatch a command.

Unsynchronised evidence references remain attached to the queued command. A host adapter must ensure the referenced files have completed their own durable upload before accepting the command.

## WorkCore host requirements

A host may activate the workflows only after it provides:

- `assurance.incidents.report`
- `assurance.inspections.complete`
- tenant and actor permission checks
- approval verification appropriate to the risk level
- idempotent command handlers
- audit and outbox propagation
- persistence for incidents, inspections, findings and corrective actions
- connected-host tests for success, rejection, retry, duplicate replay and cross-tenant access

Current host status is recorded in `reports/workcore-compatibility.json`. `engine_ready: true` is not evidence of a connected WorkCore deployment; only `connected_host_verified: true` permits production activation.

## Discovery

Authenticated API:

```text
GET /api/interaction/templates
GET /api/interaction/templates/{templateId}
```

CLI:

```bash
php artisan interaction:templates
php artisan interaction:templates --status=ready
php artisan interaction:templates --json
```
