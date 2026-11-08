<?php
// Runs spec/auth_cases.json: password hashing and the CORS switch, the same
// cases as the_deno and the_go.   php tests/auth_spec_test.php

require __DIR__ . '/../src/Password.php';
require __DIR__ . '/../src/Cors.php';

use The\{Password, Cors};

$spec = json_decode(file_get_contents(__DIR__ . '/../spec/auth_cases.json'), true, flags: JSON_THROW_ON_ERROR);
$failed = 0;
function check(string $name, $got, $want): void
{
    global $failed;
    if ($got === $want) {
        echo "ok   $name\n";
    } else {
        $failed++;
        echo "FAIL $name\n     got:  " . json_encode($got) . "\n     want: " . json_encode($want) . "\n";
    }
}

foreach ($spec['verify'] as $c) {
    check("verify: {$c['name']}", Password::verify($spec['password'], $c['stored']), [$c['ok'], $c['rehash']]);
}
$h = Password::hash($spec['password']);
check('hash is bcrypt cost 10', substr($h, 0, 7), '$2y$10$');
check('a new hash verifies, no re-hash', Password::verify($spec['password'], $h), [true, false]);
check('two hashes of one password differ (salted)', $h !== Password::hash($spec['password']), true);
try {
    Password::hash($spec['too_long']);
    check('over 72 bytes is refused', 'accepted', 'refused');
} catch (InvalidArgumentException $e) {
    check('over 72 bytes is refused', str_contains($e->getMessage(), '72 bytes'), true);
}

foreach ($spec['cors'] as $c) {
    putenv('CORS=' . $c['cors']);
    putenv('CORS_ORIGINS=' . $c['origins']);
    $hdr = Cors::headers($c['origin'] === '' ? null : $c['origin']);
    check("cors: {$c['name']}", $hdr['Access-Control-Allow-Origin'] ?? null, $c['allow']);
    if ($c['allow'] !== null) {
        check("cors: {$c['name']} allows cookies", $hdr['Access-Control-Allow-Credentials'] ?? null, 'true');
    }
}

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
