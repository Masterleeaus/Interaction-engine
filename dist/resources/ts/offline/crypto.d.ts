export interface EncryptedPayload {
    iv: string;
    ciphertext: string;
}
export declare function encryptJson(value: unknown, secret: string): Promise<EncryptedPayload>;
export declare function decryptJson<T>(payload: EncryptedPayload, secret: string): Promise<T>;
