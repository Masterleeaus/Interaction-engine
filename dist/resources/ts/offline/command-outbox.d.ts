import type { OfflineCommand, OfflineCommandInput, OutboxEnvelope, StorageAdapter } from './types';
export declare class EncryptedCommandOutbox {
    private readonly store;
    private readonly secret;
    constructor(store: StorageAdapter<OutboxEnvelope>, secret: string);
    enqueue(input: OfflineCommandInput): Promise<OutboxEnvelope>;
    pending(): Promise<OutboxEnvelope[]>;
    decrypt(id: string): Promise<OfflineCommand>;
    markSyncing(id: string): Promise<void>;
    markSynced(id: string): Promise<void>;
    markFailed(id: string, error: string): Promise<void>;
    markConflict(id: string, error?: string): Promise<void>;
    remove(id: string): Promise<void>;
    private requireEnvelope;
}
