"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.EncryptedCognitiveEventOutbox = void 0;
const crypto_1 = require("./crypto");
class EncryptedCognitiveEventOutbox {
    store;
    secret;
    constructor(store, secret) {
        this.store = store;
        this.secret = secret;
    }
    async enqueue(input) {
        if (input.tenantId.trim() === '' || input.deviceId.trim() === '') {
            throw new Error('Tenant and device identifiers are required for cognitive events.');
        }
        if (input.correlationId.trim() === '')
            throw new Error('Correlation ID is required.');
        if (!Number.isInteger(input.sequence) || input.sequence < 0)
            throw new Error('Sequence must be a non-negative integer.');
        const event = {
            ...input,
            eventId: crypto.randomUUID(),
            occurredAt: new Date().toISOString(),
        };
        const encrypted = await (0, crypto_1.encryptJson)(event, this.secret);
        const envelope = {
            id: event.eventId,
            algorithm: 'AES-256-GCM',
            iv: encrypted.iv,
            ciphertext: encrypted.ciphertext,
            correlationId: event.correlationId,
            sequence: event.sequence,
            status: 'pending',
            attempts: 0,
            queuedAt: event.occurredAt,
        };
        await this.store.put(envelope.id, envelope);
        return envelope;
    }
    async pending() {
        return (await this.store.all())
            .filter((event) => event.status === 'pending' || event.status === 'failed')
            .sort((left, right) => {
            const correlation = left.correlationId.localeCompare(right.correlationId);
            if (correlation !== 0)
                return correlation;
            if (left.sequence !== right.sequence)
                return left.sequence - right.sequence;
            return left.queuedAt.localeCompare(right.queuedAt);
        });
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
        const updated = { ...envelope, status: 'synced' };
        delete updated.lastError;
        await this.store.put(id, updated);
    }
    async markFailed(id, error) {
        const envelope = await this.requireEnvelope(id);
        await this.store.put(id, { ...envelope, status: 'failed', lastError: error });
    }
    async requireEnvelope(id) {
        const envelope = await this.store.get(id);
        if (envelope === undefined)
            throw new Error(`Cognitive event '${id}' was not found.`);
        return envelope;
    }
}
exports.EncryptedCognitiveEventOutbox = EncryptedCognitiveEventOutbox;
