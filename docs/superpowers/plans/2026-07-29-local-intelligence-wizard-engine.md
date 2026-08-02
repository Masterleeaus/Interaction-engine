# Titan Zero Local Intelligence and Universal Wizard Engine Implementation Plan

> **For agentic workers:** Execute each task with tests before implementation and verify the final archive from a clean extraction.

**Goal:** Merge Phase 8 and Phase 9 into one valid Laravel module, wire the 80-engine library, and add the document-defined universal wizard and offline local-intelligence foundations.

**Architecture:** Phase 9 is the cumulative base because every Phase 8 path exists in it. Patch artifacts are folded into canonical files, the existing interaction runtime remains the Laravel-facing workflow layer, and new framework-independent Wizard and LocalIntelligence namespaces provide deterministic offline operation. The 80 engine contracts remain stable while empty implementations are upgraded.

**Tech Stack:** PHP 8.2+, Laravel service provider integration, JSON/YAML wizard definitions, sodium/HMAC command integrity, deterministic in-memory local intelligence, custom zero-dependency verification suite.

## Global Constraints

- Preserve `TitanZero\\Interaction\\` and `TitanZero\\Engines\\` namespaces.
- Do not require cloud AI for offline operation.
- Keep WorkCore behind `DomainAdapterInterface` and command/capability boundaries.
- All generated commands carry local UUID, tenant, user, device, timestamp, and integrity metadata.
- No raw private data is uploaded by sync helpers.
- The package must pass PHP syntax lint and the included verification suite.

---

### Task 1: Canonical cumulative merge
- Fold all `(updated)`, `(example)`, and patch-note files into canonical paths.
- Repair syntax and ensure PSR-4 paths contain valid source files only.
- Verify Phase 8 has no unique files omitted by Phase 9.

### Task 2: Universal wizard runtime
- Add schema objects, registry, validation, session state, guidance, renderers, command mapping, and offline outbox.
- Add five initial business wizard definitions.
- Test required fields, conditional navigation, completion, and command integrity.

### Task 3: Offline local intelligence
- Add decision trees, language intent/entity extraction, behavioral memory, temporal analysis, hybrid reasoning, predictive completion, adaptive reweighting, sync delta handling, and a LocalBrain orchestrator.
- Test deterministic offline behavior and confidence boundaries.

### Task 4: Engine library hardening
- Preserve all 80 contracts and implementations.
- Replace empty critical methods with deterministic implementations.
- Verify every implementation implements its matching interface.

### Task 5: Laravel wiring
- Consolidate one service provider with configuration, routes, views, migrations, core contracts, repositories, runtime, wizard, local brain, and all 80 engine bindings.
- Keep cloud AI optional and fail closed when not configured.

### Task 6: Verification and delivery
- Run the package test suite, PHP lint, archive inventory, and clean extraction test.
- Generate build report and SHA-256 checksum.
