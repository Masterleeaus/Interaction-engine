import type { StorageAdapter, WizardDraft } from './types';
export declare class WizardDraftStore {
    private readonly store;
    constructor(store: StorageAdapter<WizardDraft>);
    save(draft: WizardDraft): Promise<void>;
    load(id: string): Promise<WizardDraft | undefined>;
    pending(): Promise<WizardDraft[]>;
    remove(id: string): Promise<void>;
}
