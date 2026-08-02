import type { StorageAdapter } from './types';
export declare class IndexedDbStore<T> implements StorageAdapter<T> {
    private readonly storeName;
    private readonly database;
    constructor(databaseName?: string, storeName?: string, version?: number);
    put(id: string, value: T): Promise<void>;
    get(id: string): Promise<T | undefined>;
    all(): Promise<T[]>;
    delete(id: string): Promise<void>;
    private open;
    private request;
}
