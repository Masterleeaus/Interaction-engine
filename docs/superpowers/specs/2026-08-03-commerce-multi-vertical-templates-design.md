# Commerce and Multi-Vertical Template Pack Design

**Date:** 2026-08-03
**Status:** Approved for implementation

## Objective

Extend the Interaction Engine catalogue with 28 reusable templates covering e-commerce and six operational vertical groups without creating separate engines or bypassing WorkCore mutation authority.

## Architecture

The existing `UniversalWizardEngine` remains the only execution runtime. Each ready template references a real, versioned wizard and declares a single WorkCore capability. Templates contain vertical metadata and governance policy, while the wizard owns data capture, conditional completion rules, offline behaviour, and command preparation.

Templates whose production execution depends on missing external messaging, payment, procurement, credit, recurring-scheduling, or regulated clinical host capabilities remain visible as `draft`. Draft templates are discoverable but are not executable or compatible with a host.

## Catalogue

### Commerce

Ready:

1. Product Catalogue Item
2. Customer Order Capture
3. Order Fulfilment
4. Inventory Adjustment
5. Stocktake and Variance
6. Return Request
7. Refund Approval
8. Order Issue Resolution

Draft:

9. Supplier Replenishment
10. Abandoned Checkout Recovery

### Field and Home Services

Ready:

11. Service Booking
12. Job Variation Approval

Draft:

13. Recurring Service Agreement

### Property and Facilities

Ready:

14. Property Onboarding
15. Maintenance Request
16. Property Turnover and Handover

### Trades and Construction

Ready:

17. Site Variation
18. Materials Request
19. Practical Completion and Defects

### Healthcare and Allied Health

Ready:

20. Client Intake and Consent

Draft:

21. Service Plan Review
22. Clinical or Service Incident Escalation

### Hospitality and Events

Ready:

23. Booking Intake
24. Event or Function Setup
25. Guest Issue Resolution

### Retail and Wholesale

Ready:

26. Store Stock Transfer
27. Supplier Receiving and Discrepancy

Draft:

28. Wholesale Order

This yields 22 ready templates and 6 draft templates. Combined with the existing catalogue, the repository will expose 38 templates: 29 ready and 9 draft.

## Governance

- All ready wizards require trusted `tenant_id`, `user_id`, `device_id`, and `correlation_id` context.
- Refunds require human approval for amounts above the configured workflow threshold.
- Negative inventory adjustments and material stock variances require evidence and approval.
- Job and site variations require customer or supervisor approval when cost or schedule changes are material.
- Consent workflows require explicit consent acceptance and a recorded consent timestamp.
- Supplier discrepancies and defects require evidence, owners, and due dates when action is required.
- Commands retain tenant, actor, device, correlation, causation, template, wizard, and idempotency lineage.
- WorkCore remains the only operational mutation authority.

## Compatibility

A ready template is engine-compatible only when:

1. Its entry wizard is registered.
2. The wizard capability is declared by the template.
3. The host advertises every required capability.

Draft templates must remain incompatible even if a host accidentally advertises their future capability. This prevents a draft from becoming executable through configuration alone.

## API and CLI

Existing template discovery surfaces continue to expose all templates. Status, category, vertical metadata, entry wizard, capability requirements, and compatibility results are sufficient for a host UI to filter ready versus draft templates.

## Testing

- RED catalogue tests require exactly 38 templates, 29 ready, and 9 draft.
- Every ready template must reference a real wizard and matching capability.
- Every draft template must reference a non-registered future wizard.
- Representative workflow tests verify refund approval, inventory evidence, consent declaration, variation approval, discrepancy evidence, and replay-safe command metadata.
- Existing core, assurance, TypeScript, lint, and JSON validation suites remain green.
