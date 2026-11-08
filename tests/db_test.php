<?php
// Model and DB against a real database: PostgreSQL with DBDRIVER=postgres,
// MySQL/MariaDB otherwise. Creates and drops the_t_items / the_t_categories.
//   DBTEST=1 DBHOST=127.0.0.1 DBUSER=root DBNAME=the_test php tests/db_test.php
//   DBTEST=1 DBDRIVER=postgres DBHOST=127.0.0.1 DBUSER=postgres DBNAME=the_test php tests/db_test.php

if (getenv('DBTEST') !== '1') {
    echo "skipped (set DBTEST=1)\n";
    exit(0);
}

require __DIR__ . '/../src/Req.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/Model.php';

use The\DB;

class Category extends \The\Model
{
    public $model = ['id', 'title'];
    public $name = 'category';
    protected $table = 'the_t_categories';
    protected $fillable = ['title'];
    protected $relations = ['item' => ['table' => 'the_t_items', 'name' => 'id', 'key' => 'category_id', 'callback' => Item::class]];
}

class Item extends \The\Model
{
    public $model = ['id', 'name', 'order', 'enable', 'price', 'category_id'];
    public $name = 'item';
    protected $table = 'the_t_items';
    protected $fillable = ['name', 'order', 'enable', 'price', 'category_id'];
    protected $relations = ['category' => ['table' => 'the_t_categories', 'name' => 'category_id', 'key' => 'id', 'callback' => Category::class]];
}

$pg = DB::driver() === 'postgres';
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
$names = fn ($m) => array_column($m->array(), 'name');

$serial = $pg ? 'bigserial' : 'bigint unsigned AUTO_INCREMENT';
$fk = $pg ? 'bigint' : 'bigint unsigned';
DB::raw('DROP TABLE IF EXISTS the_t_items');
DB::raw('DROP TABLE IF EXISTS the_t_categories');
DB::raw("CREATE TABLE the_t_categories (id $serial PRIMARY KEY, title varchar(100) NOT NULL)");
DB::raw("CREATE TABLE the_t_items (id $serial PRIMARY KEY, name varchar(100) NOT NULL,
    `order` int NOT NULL DEFAULT 0, enable smallint NOT NULL DEFAULT 1, price numeric(10,2) NOT NULL DEFAULT 0,
    category_id $fk NULL REFERENCES the_t_categories(id))");

try {
    $tea = Category::create(['title' => 'Tea'])->getInserted()->array();
    Category::create(['title' => 'Empty']);
    check('create + getInserted returns the row with an int id', [is_int($tea['id']), $tea['title']], [true, 'Tea']);

    $a = Item::create(['name' => 'Assam', 'order' => 2, 'price' => '120.50', 'category_id' => $tea['id']])->getInserted()->array();
    check('reserved column name `order` and DECIMAL as string', [$a['order'], $a['price'], $a['enable']], [2, '120.50', 1]);

    $many = Item::insert([['name' => 'Darjeeling', 'order' => 1], ['name' => 'Nilgiri', 'order' => 3], ['name' => 'Kangra', 'order' => 4]])->getsInserted();
    check('insert many + getsInserted', $names($many), ['Darjeeling', 'Nilgiri', 'Kangra']);

    check('chained where', $names(Item::where(['name' => ['Assam', 'Kangra']])->where(['order' => 4])->get()), ['Kangra']);
    check('wherec custom operator', count(Item::wherec([['order', '>', 2]])->get()->array()), 2);
    check('find', Item::find('Nilgiri', 'name')->array()['order'], 3);

    Item::where(['name' => ['Nilgiri']])->update(['name' => 'Nilgiri FTGFOP']);
    check('where()->update()', Item::find('Nilgiri FTGFOP', 'name')->array()['order'], 3);

    check('whereHas', $names(Item::whereHas('category', ['title' => 'Tea'])->get()), ['Assam']);
    check('whereHas with a chain', array_column(Category::whereHas('item', fn ($i) => $i->where(['name' => 'Assam']))->get()->array(), 'title'), ['Tea']);
    check('whereNotHas', array_column(Category::whereNotHas('item')->get()->array(), 'title'), ['Empty']);
    check('orderBy + limit', $names(Item::orderBy('order', 'DESC')->limit(2)->get()), ['Kangra', 'Nilgiri FTGFOP']);

    $j = Item::whereHas('category', ['title' => 'Tea'])->orderBy('id')->withJoin(['category'])->array();
    check('withJoin nests the relation', [count($j), $j[0]['name'] ?? null, $j[0]['category']['title'] ?? null], [1, 'Assam', 'Tea']);

    $w = Item::where(['category_id' => [$tea['id']]])->get()->with(['category'])->sort()->array();
    check('with()->sort() still works', $w[0]['category'][0]['title'] ?? null, 'Tea');

    (new Item())->toggle(['name' => 'Assam']);
    check('toggle', Item::find('Assam', 'name')->array()['enable'], 0);

    Item::upsert([['id' => $a['id'], 'name' => 'Assam Gold', 'order' => 2, 'enable' => 1, 'price' => '1.00', 'category_id' => $tea['id']]]);
    check('upsert updates by id', Item::find($a['id'])->array()['name'], 'Assam Gold');

    $m = new Item();
    check('a find() LIMIT does not leak into the next query', [Item::find('Kangra', 'name') !== null, count(Item::where(['name' => ['Kangra', 'Darjeeling']])->get()->array())], [true, 2]);

    $d = Item::delete(['name' => ['Darjeeling']]);
    check('delete reports affected rows', $d->rows, 1);

    check('empty list matches nothing', Item::where(['id' => []])->get()->array(), []);
} finally {
    DB::raw('DROP TABLE IF EXISTS the_t_items');
    DB::raw('DROP TABLE IF EXISTS the_t_categories');
}

echo ($pg ? 'PostgreSQL' : 'MySQL') . ': ' . ($failed ? "$failed failed\n" : "all passed\n");
exit($failed ? 1 : 0);
