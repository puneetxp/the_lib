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
$chars = fn (string $s) => preg_match_all('/./us', $s);

foreach ($spec['verify'] as $c) {
    check("verify: {$c['name']}", Password::verify($spec['password'], $c['stored']), [$c['ok'], $c['rehash']]);
}
foreach ($spec['bcrypt_input'] as $c) {
    check("bcrypt input: {$c['name']}", [strlen($c['password']), $chars($c['password']), Password::bcryptInput($c['password'])], [$c['bytes'], $c['chars'], $c['input']]);
}
foreach ($spec['verify_long'] as $c) {
    check("long: {$c['name']}", Password::verify($c['password'], $c['stored'])[0], $c['ok']);
}
foreach ($spec['new_password']['cases'] as $c) {
    check('new password: ' . $chars($c['password']) . ' chars -> ' . json_encode($c['error']), Password::newPasswordError($c['password']), $c['error']);
}
foreach ([$spec['password'], $spec['bcrypt_input'][4]['password']] as $pw) {
    $h = Password::hash($pw);
    check('new hash is bcrypt cost 10', substr($h, 0, 7), '$2y$10$');
    check('a new hash verifies, no re-hash', Password::verify($pw, $h), [true, false]);
    check('two hashes of one password differ (salted)', $h !== Password::hash($pw), true);
}
try {
    Password::hash(str_repeat('a', 129));
    check('over 128 characters is refused', 'accepted', 'refused');
} catch (InvalidArgumentException $e) {
    check('over 128 characters is refused', $e->getMessage(), $spec['new_password']['too_long']);
}
foreach ([$spec['new_password']['too_long'], $spec['new_password']['too_short']] as $m) {
    check("message says nothing about hashing: $m", (bool) preg_match('/72|byte|bcrypt|sha|hash/i', $m), false);
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
