"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.OfflineSyncClient = void 0;
class OfflineSyncClient {
    outbox;
    endpoint;
    fetcher;
    constructor(outbox, endpoint, fetcher = fetch) {
        this.outbox = outbox;
        this.endpoint = endpoint;
        this.fetcher = fetcher;
    }
    async sync(headers = {}) {
        const result = { synced: 0, failed: 0, conflicts: 0 };
        for (const envelope of await this.outbox.pending()) {
            try {
                await this.outbox.markSyncing(envelope.id);
                const command = await this.outbox.decrypt(envelope.id);
                const response = await this.fetcher(this.endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', ...headers },
                    body: JSON.stringify(command),
                });
                if (response.status === 409) {
                    result.conflicts += 1;
                    await this.outbox.markConflict(envelope.id, 'conflict');
                    continue;
                }
                if (!response.ok)
                    throw new Error(`Sync failed with HTTP ${response.status}.`);
                await this.outbox.markSynced(envelope.id);
                result.synced += 1;
            }
            catch (error) {
                result.failed += 1;
                await this.outbox.markFailed(envelope.id, error instanceof Error ? error.message : 'Unknown sync error');
            }
        }
        return result;
    }
}
exports.OfflineSyncClient = OfflineSyncClient;
