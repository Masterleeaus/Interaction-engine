# Commerce and Multi-Vertical Templates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 28 governed e-commerce and multi-vertical templates, including 22 executable wizard-backed templates and 6 explicit drafts.

**Architecture:** Extend the existing file-backed template and wizard registries. Ready templates each receive one real wizard and one WorkCore capability; draft templates remain discoverable but intentionally reference future wizard identifiers. Representative workflow tests verify governance and command lineage while the catalogue suite verifies every template mechanically.

**Tech Stack:** PHP 8.2, JSON wizard/template definitions, Node/TypeScript offline runtime, Laravel service provider integration, GitHub Actions.

## Global Constraints

- Preserve `UniversalWizardEngine` as the only workflow execution runtime.
- Preserve WorkCore as the operational mutation authority.
- Add exactly 28 templates: 22 ready and 6 draft.
- Combined catalogue must contain exactly 38 templates: 29 ready and 9 draft.
- Ready templates must have real entry wizards and matching declared capabilities.
- Draft templates must remain non-executable and incompatible even if their future host capability is advertised.
- All ready wizards require tenant, actor, device, and correlation context.
- Financial, stock, consent, discrepancy, defect, and material variation workflows fail closed when required evidence or approval is absent.
- Preserve encrypted offline replay and idempotency lineage.

---

### Task 1: Catalogue RED tests and draft fail-closed behaviour

**Files:**
- Modify: `tests/template_catalogue_run.php`
- Modify: `src/Template/TemplateCompatibilityChecker.php`
- Test: `tests/template_catalogue_run.php`

**Interfaces:**
- Consumes: `TemplateRegistry::all()`, `TemplateCompatibilityChecker::check()`.
- Produces: catalogue invariants for 38 templates and explicit draft incompatibility.

- [ ] **Step 1: Write failing catalogue tests**

Require 38 total templates, 29 ready, 9 draft, all approved identifiers, real wizards for ready templates, absent wizards for drafts, and a draft compatibility error.

- [ ] **Step 2: Run the catalogue suite and verify RED**

Run: `php tests/template_catalogue_run.php`
Expected: FAIL because the 28 new templates and draft compatibility rule do not exist.

- [ ] **Step 3: Make draft compatibility fail closed**

Update `TemplateCompatibilityChecker::check()` so `status !== 'ready'` adds `Template '<id>' is not executable while status is '<status>'.` and forces `compatible=false`.

- [ ] **Step 4: Run the focused draft test**

Run: `php tests/template_catalogue_run.php`
Expected: draft compatibility passes while catalogue counts and missing files remain RED.

- [ ] **Step 5: Commit**

```bash
git add tests/template_catalogue_run.php src/Template/TemplateCompatibilityChecker.php
git commit -m "test: define commerce and vertical catalogue contract"
```

### Task 2: Commerce ready wizards and templates

**Files:**
- Create: `templates/product-catalogue-item.json`
- Create: `templates/customer-order-capture.json`
- Create: `templates/order-fulfilment.json`
- Create: `templates/inventory-adjustment.json`
- Create: `templates/stocktake-variance.json`
- Create: `templates/return-request.json`
- Create: `templates/refund-approval.json`
- Create: `templates/order-issue-resolution.json`
- Create: `templates/supplier-replenishment.json`
- Create: `templates/abandoned-checkout-recovery.json`
- Create eight matching ready wizard JSON files under `wizards/`
- Test: `tests/commerce_vertical_workflows_run.php`

**Interfaces:**
- Consumes: existing wizard schema, governance completion rules, `UniversalWizardEngine`.
- Produces: eight executable commerce capabilities and two visible drafts.

- [ ] **Step 1: Write failing representative commerce tests**

Test refund approval above threshold, negative inventory adjustment evidence, stocktake material variance approval, and return-request command lineage.

- [ ] **Step 2: Run commerce tests and verify RED**

Run: `php tests/commerce_vertical_workflows_run.php`
Expected: FAIL with missing wizard/template identifiers.

- [ ] **Step 3: Add commerce templates and wizards**

Use semantic version `1.0.0`, offline support, trusted context requirements, and capabilities under `commerce.*` or `inventory.*`.

- [ ] **Step 4: Run commerce tests and catalogue suite**

Run: `php tests/commerce_vertical_workflows_run.php && php tests/template_catalogue_run.php`
Expected: commerce tests pass; catalogue remains RED until vertical templates exist.

- [ ] **Step 5: Commit**

```bash
git add templates wizards tests/commerce_vertical_workflows_run.php
git commit -m "feat: add governed commerce workflow templates"
```

### Task 3: Field, property, trades, healthcare, hospitality, and retail templates

**Files:**
- Create: 18 approved template JSON files under `templates/`
- Create: 14 matching ready wizard JSON files under `wizards/`
- Modify: `tests/commerce_vertical_workflows_run.php`

**Interfaces:**
- Consumes: the same generic wizard runtime and governance rule schema.
- Produces: 14 executable vertical capabilities and four visible drafts.

- [ ] **Step 1: Add failing representative vertical tests**

Test job variation approval, client consent declaration, property maintenance priority, practical-completion defects, event setup, and supplier discrepancy evidence.

- [ ] **Step 2: Run and verify RED**

Run: `php tests/commerce_vertical_workflows_run.php`
Expected: FAIL because vertical wizards are missing.

- [ ] **Step 3: Add the vertical template and wizard files**

Use vertical-specific metadata while preserving universal command and context lineage.

- [ ] **Step 4: Run workflow and catalogue suites**

Run: `php tests/commerce_vertical_workflows_run.php && php tests/template_catalogue_run.php`
Expected: both suites pass with 38 templates, 29 ready, and 9 draft.

- [ ] **Step 5: Commit**

```bash
git add templates wizards tests/commerce_vertical_workflows_run.php
git commit -m "feat: add multi-vertical workflow template pack"
```

### Task 4: Reports, documentation, verifier, and release packaging

**Files:**
- Modify: `reports/workcore-compatibility.json`
- Modify: `README.md`
- Modify: `bin/verify.php`
- Modify: `.github/workflows/verify.yml` if required
- Create: `docs/COMMERCE_MULTI_VERTICAL_TEMPLATE_PACK.md`

**Interfaces:**
- Consumes: complete 38-template catalogue.
- Produces: machine-readable compatibility evidence and release documentation.

- [ ] **Step 1: Add failing documentation/report assertions**

Require report coverage for all 38 templates, README discovery of commerce and vertical categories, and main verifier execution of the new workflow suite.

- [ ] **Step 2: Run catalogue suite and verify RED**

Run: `php tests/template_catalogue_run.php`
Expected: FAIL on report and documentation assertions.

- [ ] **Step 3: Update reports, documentation, and verifier**

Mark ready templates `engine_ready=true`, all new templates `connected_host_verified=false`, and describe the six draft blockers explicitly.

- [ ] **Step 4: Run complete verification**

Run: `php bin/verify.php`
Expected: 0 failures across core, catalogue, assurance, commerce/vertical, and TypeScript suites.

- [ ] **Step 5: Run lint and JSON validation**

Run all PHP files through `php -l` and all JSON resources through `JSON_THROW_ON_ERROR`.

- [ ] **Step 6: Build and verify distribution ZIP**

Create `/mnt/data/Interaction-engine-commerce-multi-vertical-templates-2026-08-03.zip`, run `unzip -t`, and write its SHA-256 checksum.

- [ ] **Step 7: Commit**

```bash
git add README.md bin/verify.php reports docs .github
git commit -m "docs: publish commerce and vertical template catalogue"
```

### Task 5: GitHub publication

**Files:**
- Update the existing branch and PR #4.

**Interfaces:**
- Consumes: verified local commits and distribution archive.
- Produces: reviewable GitHub source and CI evidence.

- [ ] **Step 1: Push the verified source tree to `feature/full-engine-template-upgrade`**
- [ ] **Step 2: Run GitHub Actions against the PR merge commit**
- [ ] **Step 3: Verify all CI steps and counts from logs**
- [ ] **Step 4: Update PR #4 title/body with template counts, governance boundaries, and verification evidence**
- [ ] **Step 5: Keep the PR draft until connected-host WorkCore capabilities are verified**
