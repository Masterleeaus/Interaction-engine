import type { CognitiveEventEnvelope, OfflineCognitiveEvent, OfflineCognitiveEventInput, StorageAdapter } from './types';
export declare class EncryptedCognitiveEventOutbox {
    private readonly store;
    private readonly secret;
    constructor(store: StorageAdapter<CognitiveEventEnvelope>, secret: string);
    enqueue(input: OfflineCognitiveEventInput): Promise<CognitiveEventEnvelope>;
    pending(): Promise<CognitiveEventEnvelope[]>;
    decrypt(id: string): Promise<OfflineCognitiveEvent>;
    markSyncing(id: string): Promise<void>;
    markSynced(id: string): Promise<void>;
    markFailed(id: string, error: string): Promise<void>;
    private requireEnvelope;
}
