<?php

namespace The;

/**
 * CORS, the same switch in every adapter:
 *
 *   CORS          off (default): no other site may call the API with cookies.
 *                 on: other sites may.
 *   CORS_ORIGINS  with CORS=on, only these origins (comma separated), e.g.
 *                 https://app.example.com. Empty: any origin (development).
 *
 * Both are read from a constant (cors / CORS, cors_origins / CORS_ORIGINS) or
 * else the environment. Same-origin setups (frontend and /api behind one
 * host) need neither.
 */
class Cors
{
    private static function setting(string $name): string
    {
        foreach ([strtolower($name), strtoupper($name)] as $c) {
            if (defined($c)) {
                $v = constant($c);
                return is_array($v) ? implode(',', $v) : (is_bool($v) ? ($v ? 'on' : 'off') : (string) $v);
            }
        }
        return (string) (getenv(strtoupper($name)) ?: '');
    }

    public static function enabled(): bool
    {
        return in_array(strtolower(trim(self::setting('cors'))), ['on', 'true', '1', 'yes'], true);
    }

    public static function origins(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', self::setting('cors_origins'))), fn ($o) => $o !== ''));
    }

    /**
     * Headers for $origin: [] when CORS is off or the origin isn't allowed.
     * $enabled / $allowed override the settings (tests).
     */
    public static function headers(?string $origin, ?bool $enabled = null, ?array $allowed = null): array
    {
        $enabled ??= self::enabled();
        $allowed ??= self::origins();
        if (!$enabled || $origin === null || $origin === '' || ($allowed && !in_array($origin, $allowed, true))) {
            return [];
        }
        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Cookie, X-Session-Id',
            'Access-Control-Expose-Headers' => 'X-Session-Id',
            'Access-Control-Max-Age' => '86400',
        ];
    }

    /** Send the CORS headers; answer a preflight (OPTIONS) and stop. */
    public static function apply(): void
    {
        header('Vary: Origin', false);
        foreach (self::headers($_SERVER['HTTP_ORIGIN'] ?? null) as $name => $value) {
            header("$name: $value");
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
