<?php

namespace The;

/**
 * Password hashing shared by every adapter of the framework
 * (spec/auth_cases.json): bcrypt, cost 10. Hashes from the Deno, PHP and Go
 * adapters verify in each other ($2a$, $2b$ and $2y$ are the same algorithm).
 *
 * bcrypt reads only 72 bytes, so a longer password is first reduced to
 * base64(SHA3-256(password)), 44 characters (bcryptInput()). Every byte of a
 * long password counts, and the stored hash is still a plain bcrypt string.
 *
 * A new password must have 6 to 128 characters (newPasswordError()); the
 * messages say nothing about how passwords are stored.
 *
 * Older accounts hold an unsalted SHA3-256 hash (the_deno: base64, the_lib:
 * hex). They still log in, and verify() says to re-hash them.
 */
class Password
{
    public const COST = 10;
    public const MIN_CHARS = 6;
    public const MAX_CHARS = 128;
    private const BCRYPT_MAX_BYTES = 72;

    /** Unicode characters (code points), without needing mbstring. */
    private static function chars(string $s): int
    {
        return (int) preg_match_all('/./us', $s);
    }

    /** Why a new password can't be used, or null when it can. */
    public static function newPasswordError(string $password): ?string
    {
        $n = self::chars($password);
        if ($n < self::MIN_CHARS) {
            return 'Password must be at least ' . self::MIN_CHARS . ' characters';
        }
        return $n > self::MAX_CHARS ? 'Password is too long' : null;
    }

    /** What bcrypt is given: the password, or base64(SHA3-256) when over 72 bytes. */
    public static function bcryptInput(string $password): string
    {
        return strlen($password) <= self::BCRYPT_MAX_BYTES
            ? $password
            : base64_encode(hash('sha3-256', $password, true));
    }

    public static function hash(string $password): string
    {
        if (self::chars($password) > self::MAX_CHARS) {
            throw new \InvalidArgumentException('Password is too long');
        }
        return password_hash(self::bcryptInput($password), PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    /** @return array{0: bool, 1: bool} [matches, needs re-hashing] */
    public static function verify(string $password, ?string $stored): array
    {
        if ($stored === null || $stored === '') {
            return [false, false];
        }
        if (str_starts_with($stored, '$2')) {
            $ok = password_verify(self::bcryptInput($password), $stored);
            // Not password_needs_rehash(): it calls every $2a$/$2b$ hash (Go,
            // Deno) outdated. Only a different cost needs a new hash.
            return [$ok, $ok && !preg_match('/^\$2[aby]\$' . sprintf('%02d', self::COST) . '\$/', $stored)];
        }
        $raw = hash('sha3-256', $password, true);
        $ok = hash_equals(bin2hex($raw), $stored) || hash_equals(base64_encode($raw), $stored);
        return [$ok, $ok];
    }
}
