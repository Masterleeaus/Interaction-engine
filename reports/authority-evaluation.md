# 100-case authority evaluation

- CI run: [Verify Interaction Engine #41](https://github.com/Masterleeaus/Interaction-engine/actions/runs/37174839310)
- Evaluated: 2026-10-04 03:43:38 UTC
- Tested PR head: 5d1f9f52ae3e7822e389699094abab29096dd3c4
- GitHub merge ref evaluated by CI: 9181b633447d33e532d3b1cb127d00f14dcadd5d
- Runtime: PHP 8.2.34 on the ubuntu-24.04 hosted runner

The deterministic suite covers approval-required, user-only, delegated, prepare-only, and unregistered capabilities.

| Result | Count |
| --- | ---: |
| Scenarios | 100 |
| Expected and actual allows | 30 / 30 |
| Expected and actual denies | 70 / 70 |
| Valid actions wrongly blocked | 0 / 30 |
| Unauthorized attempts blocked | 70 / 70 |
| Unauthorized actions allowed through the gate | 0 / 70 |
| Scenario mismatches | 0 / 100 |

The gate-bypassed control sends the 70 unauthorized cases to a no-op sink. It does not invoke real capability handlers or change business data. This is a deterministic policy evaluation, not a host integration, identity-provider, persistence, or production security test.

The separate 31-case evaluator, which exercises additional approval and policy-callback cases, is recorded in [eval-results/authority-policy-latest.md](../eval-results/authority-policy-latest.md).
