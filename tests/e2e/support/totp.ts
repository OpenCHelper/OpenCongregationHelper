import { createHmac } from 'node:crypto';

/**
 * The current code of an authenticator app for a Base32 secret (RFC 6238: 6 digits, 30 seconds, SHA-1).
 */
export function totp(secret: string, timestamp: number = Date.now()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const bits = secret
        .replace(/[\s=]/g, '')
        .toUpperCase()
        .split('')
        .map((character) => alphabet.indexOf(character).toString(2).padStart(5, '0'))
        .join('');
    const key = Buffer.from(bits.match(/.{8}/g)!.map((byte) => parseInt(byte, 2)));

    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(timestamp / 1000 / 30)));

    const hmac = createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0xf;
    const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return code.toString().padStart(6, '0');
}
