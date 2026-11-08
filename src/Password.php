<?php

namespace The;

/**
 * Password hashing shared by every adapter of the framework: bcrypt, cost 10.
 * Hashes from the Deno, PHP and Go adapters verify in each other ($2a$, $2b$
 * and $2y$ are the same algorithm).
 *
 * Older accounts hold an unsalted SHA3-256 hash (the_deno: base64, the_lib:
 * hex). They still log in, and verify() says to re-hash them, so they move to
 * bcrypt at their next login.
 */
class Password
{
    public const COST = 10;
    /** bcrypt reads only the first 72 bytes; longer passwords are refused. */
    public const MAX_BYTES = 72;

    public static function hash(string $password): string
    {
        if (strlen($password) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Password must be at most ' . self::MAX_BYTES . ' bytes');
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    /** @return array{0: bool, 1: bool} [matches, needs re-hashing] */
    public static function verify(string $password, ?string $stored): array
    {
        if ($stored === null || $stored === '') {
            return [false, false];
        }
        if (str_starts_with($stored, '$2')) {
            $ok = password_verify($password, $stored);
            // Not password_needs_rehash(): it calls every $2a$/$2b$ hash (Go,
            // Deno) outdated. Only a different cost needs a new hash.
            return [$ok, $ok && !preg_match('/^\$2[aby]\$' . sprintf('%02d', self::COST) . '\$/', $stored)];
        }
        $raw = hash('sha3-256', $password, true);
        $ok = hash_equals(bin2hex($raw), $stored) || hash_equals(base64_encode($raw), $stored);
        return [$ok, $ok];
    }
}
