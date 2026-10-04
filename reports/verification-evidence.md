# CI verification evidence

- Workflow: [Verify Interaction Engine #41](https://github.com/Masterleeaus/Interaction-engine/actions/runs/37174839310)
- Tested PR head: 5d1f9f52ae3e7822d8de2e852ad964364f4aabc
- Evaluated merge ref: 9181b633447d33e532d3b1cb127d00f14dcadd5d
- Runtime: PHP 8.2.34, Node v24.21.0, GitHub-hosted Ubuntu 24.04.5
- Result: successful

## Test results

| Suite | Passed |
| --- | ---: |
| Standalone policy package | 7 / 7 |
| Core PHP verification | 34 / 34 |
| Template catalogue | 10 / 10 |
| Assurance workflows | 9 / 9 |
| Commerce and vertical workflows | 8 / 8 |
| TypeScript offline tests | 4 / 4 |
| Total individual tests | 72 / 72 |

The verifier also passed PHP syntax lint, maintained-file SHA-256 validation, JSON resource checks, and the contract/implementation pairing check for all 80 engines.

## Coverage

- Core PHP tests: 1,798 / 1,798 executable lines covered across 215 loaded PHP files.
- Standalone Policy Engine tests: 128 / 128 executable lines covered across six loaded PHP files.
- TypeScript: 88.67% line coverage, 74.24% branch coverage, and 82.93% function coverage.

The PHP percentages describe executable lines in files loaded by these test runs. They are not a claim that every PHP file in the repository was loaded or covered. The TypeScript figures cover the offline companion exercised by four tests.

No live OpenAI request, connected Laravel application, production database, or target mobile device was part of this run.
