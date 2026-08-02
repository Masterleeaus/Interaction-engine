"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.IndexedDbStore = void 0;
class IndexedDbStore {
    storeName;
    database;
    constructor(databaseName = 'titan-zero-interaction', storeName = 'records', version = 1) {
        this.storeName = storeName;
        this.database = this.open(databaseName, version);
    }
    async put(id, value) {
        await this.request('readwrite', (store) => store.put(value, id));
    }
    async get(id) {
        return this.request('readonly', (store) => store.get(id));
    }
    async all() {
        return this.request('readonly', (store) => store.getAll());
    }
    async delete(id) {
        await this.request('readwrite', (store) => store.delete(id));
    }
    open(databaseName, version) {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(databaseName, version);
            request.onupgradeneeded = () => {
                const database = request.result;
                if (!database.objectStoreNames.contains(this.storeName))
                    database.createObjectStore(this.storeName);
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error ?? new Error('IndexedDB open failed.'));
        });
    }
    async request(mode, operation) {
        const database = await this.database;
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(this.storeName, mode);
            const request = operation(transaction.objectStore(this.storeName));
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error ?? new Error('IndexedDB operation failed.'));
            transaction.onerror = () => reject(transaction.error ?? new Error('IndexedDB transaction failed.'));
        });
    }
}
exports.IndexedDbStore = IndexedDbStore;
