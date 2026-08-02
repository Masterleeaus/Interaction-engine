"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.encryptJson = encryptJson;
exports.decryptJson = decryptJson;
const encoder = new TextEncoder();
const decoder = new TextDecoder();
async function encryptJson(value, secret) {
    const key = await deriveKey(secret);
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const plaintext = encoder.encode(JSON.stringify(value));
    const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: toArrayBuffer(iv) }, key, toArrayBuffer(plaintext));
    return { iv: toBase64(iv), ciphertext: toBase64(new Uint8Array(ciphertext)) };
}
async function decryptJson(payload, secret) {
    const key = await deriveKey(secret);
    const plaintext = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: toArrayBuffer(fromBase64(payload.iv)) }, key, toArrayBuffer(fromBase64(payload.ciphertext)));
    return JSON.parse(decoder.decode(plaintext));
}
async function deriveKey(secret) {
    if (secret.length < 8) {
        throw new Error('Device outbox secret must contain at least eight characters.');
    }
    const digest = await crypto.subtle.digest('SHA-256', toArrayBuffer(encoder.encode(secret)));
    return crypto.subtle.importKey('raw', digest, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
}
function toBase64(bytes) {
    let binary = '';
    for (const byte of bytes)
        binary += String.fromCharCode(byte);
    return btoa(binary);
}
function fromBase64(value) {
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);
    for (let index = 0; index < binary.length; index += 1)
        bytes[index] = binary.charCodeAt(index);
    return bytes;
}
function toArrayBuffer(bytes) {
    const copy = new Uint8Array(bytes.byteLength);
    copy.set(bytes);
    return copy.buffer;
}
