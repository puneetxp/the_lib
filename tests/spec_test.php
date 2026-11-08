<?php
// Runs spec/query_cases.json, the query builder cases every adapter of the
// framework must pass (same file as the_deno's spec/query_cases.json).
// No database needed: toSQL()/withJoinSQL() don't run SQL.
//   php tests/spec_test.php

require __DIR__ . '/../src/Req.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/Model.php';

use The\DB;
use The\Model;

$spec = json_decode(file_get_contents(__DIR__ . '/../spec/query_cases.json'), true, flags: JSON_THROW_ON_ERROR);

// One model class per spec model: Spec_<name>.
foreach ($spec['models'] as $name => $m) {
    $relations = [];
    foreach ($m['relations'] as $rel => $r) {
        $relations[$rel] = ['table' => $r['table'], 'name' => $r['name'], 'key' => $r['key'], 'callback' => 'Spec_' . $r['model']];
    }
    eval('class Spec_' . $name . ' extends \The\Model {
        public $model = ' . var_export($m['columns'], true) . ';
        public $name = ' . var_export($name, true) . ';
        protected $table = ' . var_export($m['table'], true) . ';
        protected $relations = ' . var_export($relations, true) . ';
    }');
}

function replay(Model $model, array $chain): Model
{
    foreach ($chain as $step) {
        $op = array_shift($step);
        switch ($op) {
            case 'where':
                $model->where($step[0], $step[1] ?? false);
                break;
            case 'whereHas':
            case 'whereNotHas':
                [$rel, $cond] = $step + [1 => null];
                $nested = is_array($cond) && array_is_list($cond) && $cond !== [];
                $model->$op($rel, $nested ? fn ($r) => replay($r, $cond) : $cond);
                break;
            case 'orderBy':
                $model->orderBy(...$step);
                break;
            case 'limit':
                $model->limit(...$step);
                break;
            case 'withJoin':
                break; // last step; sqlOf() handles it
            default:
                throw new RuntimeException("spec uses unknown step \"$op\"");
        }
    }
    return $model;
}

function sqlOf(array $c): array
{
    $last = end($c['chain']);
    $class = 'Spec_' . $c['model'];
    $model = replay(new $class(), $c['chain']);
    return ($last[0] ?? null) === 'withJoin' ? $model->withJoinSQL($last[1]) : $model->toSQL();
}

$failed = 0;
$check = function (string $name, $got, $want) use (&$failed) {
    if ($got === $want) {
        echo "ok   $name\n";
        return;
    }
    $failed++;
    echo "FAIL $name\n     got:  " . json_encode($got) . "\n     want: " . json_encode($want) . "\n";
};

foreach ($spec['cases'] as $c) {
    $got = sqlOf($c);
    $check("spec: {$c['name']}", [trim(preg_replace('/\s+/', ' ', $got['sql'])), $got['params']], [$c['sql'], $c['params']]);
    $check("spec: {$c['name']} (same SQL on a second run)", sqlOf($c)['sql'], $got['sql']);
}

foreach ($spec['errors'] as $e) {
    try {
        sqlOf($e);
        $check("spec error: {$e['name']}", 'no error', $e['message']);
    } catch (Throwable $t) {
        $check("spec error: {$e['name']}", str_contains($t->getMessage(), $e['message']), true);
    }
}

$nested = array_values(array_filter($spec['cases'], fn ($c) => str_starts_with($c['name'], 'nested whereHas')))[0];
$check(
    'PostgreSQL form: backticks become double quotes, ? stays for PDO',
    DB::toPostgres($nested['sql']),
    'SELECT * FROM books WHERE EXISTS (SELECT 1 FROM book_stocks AS "book_stocks__1" WHERE "book_stocks__1"."book_id" = "books"."id" AND ("publish" IN (?) AND EXISTS (SELECT 1 FROM products AS "products__2" WHERE "products__2"."id" = "book_stocks__1"."product_id" AND ("enable" IN (?)))))'
);
$check('toPostgres leaves strings and comments', DB::toPostgres("SELECT '`a`' , \"`b`\" -- `c`\n, `d`"), "SELECT '`a`' , \"`b`\" -- `c`\n, \"d\"");

$m = Spec_product::where(['enable' => 1])->orderBy('id')->limit(1);
$m->toSQL();
$check('toSQL clears the chain', trim($m->toSQL()['sql']), 'SELECT * FROM products');
$check('static and chained where agree', Spec_product::where(['id' => 1])->where(['enable' => 1])->toSQL()['params'], [1, 1]);

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
