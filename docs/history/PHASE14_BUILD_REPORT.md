# Titan Zero Interaction Engine — Phase 14 Build Report

## Scope
Phase 14 adds enforceable authority boundaries before further autonomous intelligence is introduced.

## Implemented
- AuthorityLevel, CapabilityPolicy, AuthorityDecision and ApprovalSigner.
- Default-deny PolicyEngine with typed decisions.
- Signed approval verification with capability, tenant, role and expiry checks.
- User-only fresh-authentication enforcement.
- Delegated scopes and numeric execution limits.
- Production capability policies registered in InteractionServiceProvider.
- Offline command identity and authority context preservation.
- Cognitive audit evidence for rejected authority decisions.

## Default capability policy
- crm.customer.create: delegated autonomous with crm:customer:create scope.
- quotes.create: owner/manager approval required.
- jobs.create: owner/manager/dispatcher approval required.
- jobs.complete: delegated autonomous with jobs:complete scope.
- finance.invoice.create: owner/manager/finance approval required.
- finance.payment.record: user-only, owner/finance role, authentication within 300 seconds.

## Host integration requirements
- Set INTERACTION_APPROVAL_SECRET to a dedicated secret of at least 16 characters.
- Map host user roles into command `_context.roles`.
- Issue signed approval grants only after host authorisation and fresh identity verification.
- Never expose approval signing secrets or device encryption keys to browser code.
