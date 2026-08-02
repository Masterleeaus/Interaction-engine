# Phase 14 — Authority and Human-Only Controls

## Added
- Fail-closed capability policy registry.
- Six authority levels: observe-only, recommend-only, prepare-only, approval-required, delegated-autonomous, and user-only.
- Cryptographically signed, tenant-scoped, capability-scoped, expiring approval grants.
- Fresh-authentication checks for user-only commands.
- Required-role, delegated-scope, and numeric-limit enforcement.
- Command-bus authority decisions recorded into the cognitive event stream.
- Offline replay context reconstruction for tenant, device, user, actor type, roles, scopes, authentication time, and approval evidence.

## Security corrections
- Removed the former allow-by-default behaviour for unknown capabilities.
- Removed reliance on client-controlled `manager_approved` booleans.
- Device replay can no longer inherit human authority implicitly.
- Payment recording is configured as a user-only, freshly authenticated action.
