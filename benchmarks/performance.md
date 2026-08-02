# Performance Benchmark Protocol

No fabricated benchmark numbers are shipped with this module.

Measure the following in the target Laravel application using production-like data and the real cache/database drivers:

1. Interaction definition compile latency, cold and warm cache.
2. Wizard start, step validation and completion latency.
3. LocalBrain processing latency for 1,000 representative commands.
4. IndexedDB command encryption/decryption latency on target phones and tablets.
5. Offline sync throughput, duplicate command handling and conflict rate.
6. WorkCore adapter write latency and database query count.
7. Memory use while resolving the complete 80-engine container graph.

Record p50, p95 and p99 latency, peak memory and error rate. Keep the raw command, environment, commit/archive checksum and dataset description beside every result.
