import { randomBytes } from 'crypto';

/**
 * Generates a unique username using a cryptographically secure random source.
 * Format: prefix_timestamp_random (hex)
 */
export function uniqueUsername(prefix: string = 'user'): string {
    const randomPart = randomBytes(4).toString('hex');
    return `${prefix}_${Date.now()}_${randomPart}`;
}