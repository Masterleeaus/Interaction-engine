# Authority Policy Evaluation

- Evaluated: 2026-10-04T02:35:32Z
- Evaluator commit: bdbe73cc445b900d29126e081bd5f2341e98ee6f
- Scenarios: 31 (seed 20261004)
- Scenario SHA-256: a1e863c857aa05e70f720224c3f1bb0065f2f1c699fe27e438ce4a64a58ff340
- PHP: 8.2.34
- Baseline: **24/24** blocked cases would pass through an allow-all bypass.

| Metric | Result |
| --- | ---: |
| Unauthorized actions allowed | 0 / 24 |
| Valid actions wrongly denied | 0 / 7 |
| Wrong-tenant approvals allowed | 0 / 1 |
| Scenario mismatches | 0 / 31 |

The baseline is a deliberately simple allow-all bypass control, not a competing product. This evaluation calls the repository PolicyEngine directly; it does not test a host adapter, persistence, or downstream execution.

## Scenario outcomes

| Scenario | Category | Expected | Actual | Result |
| --- | --- | ---: | ---: | --- |
| human_owner_fresh_allowed | user-only | allow | allow | PASS |
| human_any_required_role_allowed | user-only | allow | allow | PASS |
| valid_scoped_approval_allowed | approval | allow | allow | PASS |
| delegated_agent_at_limit_allowed | delegation | allow | allow | PASS |
| delegated_agent_with_all_scopes_allowed | delegation | allow | allow | PASS |
| human_can_perform_delegated_capability | delegation | allow | allow | PASS |
| additional_policy_explicit_allow | policy-callback | allow | allow | PASS |
| unknown_capability_denied | fail-closed | deny | deny | PASS |
| observe_only_denied | authority-level | deny | deny | PASS |
| recommend_only_denied | authority-level | deny | deny | PASS |
| prepare_only_denied | authority-level | deny | deny | PASS |
| agent_cannot_use_user_only_action | user-only | deny | deny | PASS |
| human_without_user_id_denied | user-only | deny | deny | PASS |
| human_without_required_role_denied | user-only | deny | deny | PASS |
| stale_human_authentication_denied | user-only | deny | deny | PASS |
| approval_without_signer_denied | approval | deny | deny | PASS |
| approval_missing_denied | approval | deny | deny | PASS |
| tampered_approval_signature_denied | approval | deny | deny | PASS |
| approval_for_other_capability_denied | approval | deny | deny | PASS |
| cross_tenant_approval_denied | tenant-boundary | deny | deny | PASS |
| expired_approval_denied | approval | deny | deny | PASS |
| approval_over_policy_ttl_denied | approval | deny | deny | PASS |
| approval_without_required_approver_role_denied | approval | deny | deny | PASS |
| approval_without_approver_identity_denied | approval | deny | deny | PASS |
| delegated_agent_missing_scope_denied | delegation | deny | deny | PASS |
| delegated_agent_wrong_scope_denied | delegation | deny | deny | PASS |
| delegated_amount_over_limit_denied | delegation | deny | deny | PASS |
| delegated_amount_just_over_limit_denied | delegation | deny | deny | PASS |
| custom_policy_reason_denies | policy-callback | deny | deny | PASS |
| custom_policy_false_denies | policy-callback | deny | deny | PASS |
| custom_policy_structured_denial | policy-callback | deny | deny | PASS |
