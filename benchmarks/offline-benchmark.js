'use strict';

const { performance } = require('node:perf_hooks');
const { MemoryStore } = require('../dist/resources/ts/offline/memory-store.js');
const { EncryptedCommandOutbox } = require('../dist/resources/ts/offline/command-outbox.js');

const samples = Number(process.env.BENCHMARK_SAMPLES || 300);

function percentiles(values) {
  const sorted = [...values].sort((left, right) => left - right);
  const at = (percentile) => sorted[Math.max(0, Math.ceil(sorted.length * percentile) - 1)];
  return {
    p50: Number(at(0.5).toFixed(6)),
    p95: Number(at(0.95).toFixed(6)),
    min: Number(sorted[0].toFixed(6)),
    max: Number(sorted.at(-1).toFixed(6)),
    unit: 'ms',
  };
}

async function run() {
  if (!Number.isInteger(samples) || samples < 20) throw new Error('BENCHMARK_SAMPLES must be an integer of at least 20.');
  const store = new MemoryStore();
  const outbox = new EncryptedCommandOutbox(store, 'offline-benchmark-device-secret-32-bytes');
  const enqueueTimes = [];
  const decryptTimes = [];

  for (let index = 0; index < samples; index += 1) {
    const startEnqueue = performance.now();
    const envelope = await outbox.enqueue({
      capability: 'jobs.create',
      payload: { customer_id: `customer-${index}`, service_id: 'residential-cleaning', estimated_minutes: 120 },
      metadata: { tenant_id: 'benchmark-tenant', user_id: 7, device_id: 'benchmark-device' },
    });
    enqueueTimes.push(performance.now() - startEnqueue);

    const startDecrypt = performance.now();
    const command = await outbox.decrypt(envelope.id);
    decryptTimes.push(performance.now() - startDecrypt);
    if (command.capability !== 'jobs.create') throw new Error('Decrypted command did not match the benchmark fixture.');
  }

  const report = {
    suite: 'interaction-engine-performance-typescript',
    date_utc: new Date().toISOString(),
    node_version: process.version,
    platform: `${process.platform}-${process.arch}`,
    sample_count: samples,
    outbox_enqueue_ms: percentiles(enqueueTimes),
    outbox_decrypt_ms: percentiles(decryptTimes),
    notes: [
      'Uses the production EncryptedCommandOutbox with the in-memory StorageAdapter; IndexedDB and device hardware are not measured.',
      'Enqueue includes AES-256-GCM key derivation, encryption, envelope creation, and memory-store write. Decrypt includes key derivation and AES-GCM decryption.',
    ],
  };
  const json = `${JSON.stringify(report, null, 2)}\n`;
  const destination = process.argv[2];
  if (destination) require('node:fs').writeFileSync(destination, json);
  process.stdout.write(json);
}

run().catch((error) => {
  process.stderr.write(`${error instanceof Error ? error.stack : String(error)}\n`);
  process.exitCode = 1;
});
