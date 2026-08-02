# Assurance Template Workflows Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Recover the canonical source tree and promote Incident Response and Inspection/Corrective Action from draft catalogue entries into executable, tenant-safe, offline-capable, approval-governed workflows.

**Architecture:** Preserve the Interaction Engine as the interaction and command-preparation layer. New wizards collect structured evidence and prepare WorkCore capabilities; they never mutate operational tables. Critical/high-risk submissions require explicit human approval metadata, preserve correlation and causation context, and remain replay-safe through the existing encrypted outbox.

**Tech Stack:** PHP 8.2+, Laravel package conventions, JSON wizard definitions, deterministic PHP verification scripts, TypeScript offline queue tests, GitHub Actions.

## Global Constraints

- WorkCore remains the sole operational mutation authority.
- Missing capabilities, tenant context, actor context, evidence, or required approval fail closed.
- Offline submissions preserve evidence references and idempotency context and are never silently discarded.
- Templates are marked `ready` only when their entry wizard, capability mapping, validation, and connected-host compatibility checks pass.
- No new runtime dependency is added unless the existing package cannot satisfy a requirement.

---

### Task 1: Recover and verify the source tree

**Files:**
- Create: `.github/workflows/reconstruct-source.yml`
- Preserve: `docs/superpowers/plans/2026-08-03-assurance-template-workflows.md`
- Remove after successful reconstruction: `.bootstrap/`, `.bootstrap-lite/`

**Interfaces:**
- Consumes: sorted base64 bootstrap chunks from `.bootstrap-lite/chunk-*`
- Produces: reviewable repository source with `composer.json`, `src/`, `wizards/`, `tests/`, and verification scripts

- [ ] Add a pull-request workflow that concatenates chunks in lexical order, base64-decodes the payload, validates the archive, and extracts the package root.
- [ ] Run `php bin/verify.php`, the template-catalogue suite when present, `npm test`, JSON parsing, and PHP syntax lint before committing reconstructed source.
- [ ] Remove transport chunks only after every verification gate passes.
- [ ] Commit the reconstructed tree to `feature/full-engine-template-upgrade`.

### Task 2: Add failing assurance workflow tests

**Files:**
- Create: `tests/assurance_workflows_run.php`
- Modify: `bin/verify.php`

**Interfaces:**
- Consumes: `WizardRegistry`, `UniversalWizardEngine`, template catalogue, command mapper, authority policy
- Produces: deterministic RED tests for both workflows and negative security cases

- [ ] Assert `incident_response_v1` and `inspection_corrective_action_v1` are discoverable.
- [ ] Assert both workflows reject missing tenant and actor context.
- [ ] Assert critical incidents reject completion without human approval metadata.
- [ ] Assert inspection findings require evidence and produce corrective-action commands for failed items.
- [ ] Assert offline completion queues one encrypted, replay-safe command with tenant, actor, device, correlation, causation, wizard-run, and idempotency context.
- [ ] Run the test and verify failure is caused by missing workflows and policy logic.

### Task 3: Implement reusable assurance governance

**Files:**
- Create: `src/Wizard/Governance/GovernedCompletionPolicy.php`
- Create: `src/Wizard/Governance/GovernanceViolation.php`
- Modify: `src/Wizard/UniversalWizardEngine.php`
- Modify: `src/Wizard/WizardDefinition.php`

**Interfaces:**
- Consumes: wizard governance metadata, session context, collected answers
- Produces: `assertMayComplete(WizardDefinition $definition, array $data, array $context): void`

- [ ] Write focused failing tests for missing tenant, actor, critical approval, and evidence.
- [ ] Add optional `governance` metadata to wizard definitions.
- [ ] Enforce mandatory context and completion rules immediately before command preparation.
- [ ] Preserve existing wizards by treating absent governance metadata as the existing behaviour.
- [ ] Run assurance and canonical suites until green.

### Task 4: Implement Incident Response wizard

**Files:**
- Create: `wizards/incident_response.json`
- Create or update: `templates/incident-response.json`

**Interfaces:**
- Produces capability: `assurance.incidents.report`
- Required context: `tenant_id`, `user_id`, `device_id`, `correlation_id`
- Required critical-risk approval fields: `approval_id`, `approved_by`, `approved_at`

- [ ] Add identity, location, incident classification, people/environment impact, immediate controls, evidence, escalation, approval, and declaration steps.
- [ ] Make approval conditional on `risk_level` being `critical` or `high` through governance rules rather than UI-only conditions.
- [ ] Include evidence attachment IDs, witness details, injury/environmental impact, regulator-notification flags, and immediate-control records.
- [ ] Promote the template to `ready` only after the workflow tests pass.

### Task 5: Implement Inspection and Corrective Action wizard

**Files:**
- Create: `wizards/inspection_corrective_action.json`
- Create or update: `templates/inspection-corrective-action.json`

**Interfaces:**
- Produces capability: `assurance.inspections.complete`
- Required context: `tenant_id`, `user_id`, `device_id`, `correlation_id`
- Produces structured findings and corrective-action requests in one governed payload

- [ ] Add inspection scope, checklist results, evidence, findings, risk rating, corrective actions, owners, due dates, verification method, approval, and declaration steps.
- [ ] Require evidence for failed/critical findings and reject unresolved critical findings without an assigned owner and due date.
- [ ] Mark the template `ready` only after capability compatibility and negative tests pass.

### Task 6: Strengthen command lineage and replay safety

**Files:**
- Modify: `src/Wizard/Command/CommandMapper.php`
- Modify: `src/Wizard/UniversalWizardEngine.php`
- Modify: offline tests as required

**Interfaces:**
- Produces `_context` keys: `tenant_id`, `user_id`, `device_id`, `wizard_run_id`, `correlation_id`, `causation_id`, `idempotency_key`, `template_id`, `template_version`

- [ ] Add a deterministic idempotency key derived from tenant, wizard run, capability, and definition version.
- [ ] Preserve caller-provided causation IDs and generate a safe fallback from correlation ID.
- [ ] Verify online dispatch and offline replay produce equivalent command envelopes.
- [ ] Verify cross-tenant or actor-less completion fails before dispatch or enqueue.

### Task 7: Documentation, compatibility report, and release verification

**Files:**
- Create: `docs/ASSURANCE_WORKFLOWS.md`
- Create: `reports/workcore-compatibility.json`
- Modify: `README.md`
- Modify: CI workflow

**Interfaces:**
- Produces machine-readable status for each template, entry wizard, required capabilities, authority level, offline support, and connected-host verification state

- [ ] Document payload contracts, authority boundaries, offline behaviour, approval rules, and WorkCore adapter requirements.
- [ ] Generate a compatibility report that fails closed when a required host capability is absent.
- [ ] Run canonical PHP verification, assurance suite, template catalogue suite, TypeScript offline suite, JSON validation, and full PHP lint.
- [ ] Commit changes, update the draft pull request, and close Issue #1 only after connected-host verification is genuinely complete; otherwise leave it open with an evidence comment.
