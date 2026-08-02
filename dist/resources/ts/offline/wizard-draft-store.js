"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.WizardDraftStore = void 0;
class WizardDraftStore {
    store;
    constructor(store) {
        this.store = store;
    }
    async save(draft) {
        const now = new Date().toISOString();
        await this.store.put(draft.id, { ...draft, updatedAt: now });
    }
    async load(id) {
        return this.store.get(id);
    }
    async pending() {
        return (await this.store.all()).filter((draft) => draft.syncStatus !== 'synced');
    }
    async remove(id) {
        await this.store.delete(id);
    }
}
exports.WizardDraftStore = WizardDraftStore;
