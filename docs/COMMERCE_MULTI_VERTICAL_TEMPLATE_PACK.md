# Commerce and Multi-Vertical Template Pack

This pack adds 28 governed templates to the Titan Zero Interaction Engine. It reuses the universal wizard runtime and does not create competing commerce, property, care, hospitality, construction, or inventory engines.

## Catalogue totals

| State | Added | Repository total |
|---|---:|---:|
| Ready and executable | 22 | 29 |
| Draft and non-executable | 6 | 9 |
| Total | 28 | 38 |

## E-commerce and inventory

| Template | Status | Capability |
|---|---|---|
| Product Catalogue Item | Ready | `commerce.products.upsert` |
| Customer Order Capture | Ready | `commerce.orders.create` |
| Order Fulfilment | Ready | `commerce.orders.fulfil` |
| Inventory Adjustment | Ready | `inventory.adjustments.record` |
| Stocktake and Variance | Ready | `inventory.stocktakes.complete` |
| Return Request | Ready | `commerce.returns.request` |
| Refund Approval | Ready | `commerce.refunds.approve` |
| Order Issue Resolution | Ready | `commerce.orders.resolve_issue` |
| Supplier Replenishment | Draft | `procurement.replenishment.request` |
| Abandoned Checkout Recovery | Draft | `commerce.checkout.recover` |

Supplier Replenishment remains draft until a canonical procurement and supplier-order boundary exists. Abandoned Checkout Recovery remains draft until messaging consent, channel policy and communication delivery are connected.

## Field and home services

| Template | Status | Capability |
|---|---|---|
| Service Booking | Ready | `field_services.bookings.create` |
| Job Variation Approval | Ready | `field_services.job_variations.approve` |
| Recurring Service Agreement | Draft | `field_services.recurring_agreements.create` |

Recurring agreements require coordinated recurring billing and scheduling rather than a template-only implementation.

## Property and facilities

| Template | Status | Capability |
|---|---|---|
| Property Onboarding | Ready | `property.assets.onboard` |
| Maintenance Request | Ready | `property.maintenance.request` |
| Property Turnover and Handover | Ready | `property.turnovers.complete` |

Emergency maintenance requires evidence and an escalation owner. Turnover damage requires evidence, an action owner and a due date.

## Trades and construction

| Template | Status | Capability |
|---|---|---|
| Site Variation | Ready | `construction.site_variations.approve` |
| Materials Request | Ready | `construction.materials.request` |
| Practical Completion and Defects | Ready | `construction.practical_completion.record` |

Cost-bearing variations require evidence and approval. Defects require structured evidence, rectification ownership and due dates.

## Healthcare and allied health

| Template | Status | Capability |
|---|---|---|
| Client Intake and Consent | Ready | `care.clients.intake` |
| Service Plan Review | Draft | `care.service_plans.review` |
| Clinical or Service Incident Escalation | Draft | `care.incidents.escalate` |

Client Intake records explicit accepted consent, consent time and privacy-notice version. Service plan and clinical incident templates remain draft until regulated records, consent, notification and jurisdiction-specific host policies are connected.

## Hospitality and events

| Template | Status | Capability |
|---|---|---|
| Booking Intake | Ready | `hospitality.bookings.create` |
| Event or Function Setup | Ready | `hospitality.events.prepare` |
| Guest Issue Resolution | Ready | `hospitality.guest_issues.resolve` |

Incomplete safety checks require a recorded issue and owner. Compensation above the workflow threshold requires approval.

## Retail and wholesale

| Template | Status | Capability |
|---|---|---|
| Store Stock Transfer | Ready | `inventory.transfers.create` |
| Supplier Receiving and Discrepancy | Ready | `inventory.receipts.record` |
| Wholesale Order | Draft | `wholesale.orders.create` |

Urgent stock transfers require approval. Supplier discrepancies require evidence, ownership and a due date. Wholesale Order remains draft until credit limits, customer-specific pricing, tax and payment terms are handled by the host.

## Governance guarantees

Every ready wizard requires:

- `tenant_id`
- `user_id`
- `device_id`
- `correlation_id`

Commands preserve wizard, template, tenant, actor, device, correlation, causation and idempotency lineage. Financial, inventory, consent, discrepancy, defect and variation workflows fail closed when their evidence or approval rules are not satisfied.

Draft templates are deliberately blocked by `TemplateCompatibilityChecker` even if a future host capability is present. Changing a draft to ready requires a real entry wizard, package tests, explicit review and host compatibility work.

## Verification

Run:

```bash
php tests/commerce_vertical_workflows_run.php
php tests/template_catalogue_run.php
php bin/verify.php
```

Package-level readiness does not imply connected-host readiness. All new capabilities remain `connected_host_verified=false` in `reports/workcore-compatibility.json` until tested against canonical WorkCore handlers.
