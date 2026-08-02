"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.EncryptedCommandOutbox = void 0;
const crypto_1 = require("./crypto");
class EncryptedCommandOutbox {
    store;
    secret;
    constructor(store, secret) {
        this.store = store;
        this.secret = secret;
    }
    async enqueue(input) {
        if (input.capability.trim() === '')
            throw new Error('Command capability is required.');
        if (input.metadata.tenant_id === '' || input.metadata.device_id === '') {
            throw new Error('Tenant and device identifiers are required for offline commands.');
        }
        const command = {
            ...input,
            id: crypto.randomUUID(),
            createdAt: new Date().toISOString(),
        };
        const encrypted = await (0, crypto_1.encryptJson)(command, this.secret);
        const envelope = {
            id: command.id,
            algorithm: 'AES-256-GCM',
            iv: encrypted.iv,
            ciphertext: encrypted.ciphertext,
            status: 'pending',
            attempts: 0,
            queuedAt: command.createdAt,
        };
        await this.store.put(envelope.id, envelope);
        return envelope;
    }
    async pending() {
        return (await this.store.all())
            .filter((item) => item.status === 'pending' || item.status === 'failed')
            .sort((left, right) => left.queuedAt.localeCompare(right.queuedAt));
    }
    async decrypt(id) {
        const envelope = await this.requireEnvelope(id);
        return (0, crypto_1.decryptJson)(envelope, this.secret);
    }
    async markSyncing(id) {
        const envelope = await this.requireEnvelope(id);
        await this.store.put(id, { ...envelope, status: 'syncing', attempts: envelope.attempts + 1 });
    }
    async markSynced(id) {
        const envelope = await this.requireEnvelope(id);
        const updated = {
            ...envelope,
            status: 'synced',
            syncedAt: new Date().toISOString(),
        };
        delete updated.lastError;
        await this.store.put(id, updated);
    }
    async markFailed(id, error) {
        const envelope = await this.requireEnvelope(id);
        await this.store.put(id, { ...envelope, status: 'failed', lastError: error });
    }
    async markConflict(id, error = 'conflict') {
        const envelope = await this.requireEnvelope(id);
        await this.store.put(id, { ...envelope, status: 'conflict', lastError: error });
    }
    async remove(id) {
        await this.store.delete(id);
    }
    async requireEnvelope(id) {
        const envelope = await this.store.get(id);
        if (envelope === undefined)
            throw new Error(`Outbox command '${id}' was not found.`);
        return envelope;
    }
}
exports.EncryptedCommandOutbox = EncryptedCommandOutbox;
