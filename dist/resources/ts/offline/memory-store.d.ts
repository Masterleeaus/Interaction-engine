import type { StorageAdapter } from './types';
export declare class MemoryStore<T> implements StorageAdapter<T> {
    private readonly values;
    put(id: string, value: T): Promise<void>;
    get(id: string): Promise<T | undefined>;
    all(): Promise<T[]>;
    delete(id: string): Promise<void>;
}
