import { EncryptedCommandOutbox } from './command-outbox';
export interface SyncResult {
    synced: number;
    failed: number;
    conflicts: number;
}
export declare class OfflineSyncClient {
    private readonly outbox;
    private readonly endpoint;
    private readonly fetcher;
    constructor(outbox: EncryptedCommandOutbox, endpoint: string, fetcher?: typeof fetch);
    sync(headers?: Record<string, string>): Promise<SyncResult>;
}
