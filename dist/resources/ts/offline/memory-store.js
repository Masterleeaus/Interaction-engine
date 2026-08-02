"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.MemoryStore = void 0;
class MemoryStore {
    values = new Map();
    async put(id, value) {
        this.values.set(id, structuredClone(value));
    }
    async get(id) {
        const value = this.values.get(id);
        return value === undefined ? undefined : structuredClone(value);
    }
    async all() {
        return Array.from(this.values.values(), (value) => structuredClone(value));
    }
    async delete(id) {
        this.values.delete(id);
    }
}
exports.MemoryStore = MemoryStore;
