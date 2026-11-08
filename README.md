# the_lib

Dependencies Library for the THE framework.

## Database

MySQL/MariaDB (default) or PostgreSQL through PDO. Needs the `pdo_mysql` or
`pdo_pgsql` extension. Configure with constants or environment variables:
`dbdriver` (`mysql` or `postgres`), `dbhost`, `dbport`, `dbuser`, `dbpwd`,
`dbname`. Lowercase and uppercase constant names both work.

All models share one connection, opened on the first query.

## Querying through models

```php
Book_stock::where(['publish' => 1, 'enable' => 1])
    ->whereHas('book', ['public_listing' => 1])
    ->whereHas('product', fn ($p) => $p->where(['enable' => 1]))
    ->orderBy('id')
    ->limit(50)
    ->get();
```

- **Chaining:** `where`, `whereHas`, `whereNotHas`, `orderBy` and `limit` can start a query (`User::where(...)`) or continue one (`->where(...)`).
- **Joins:** `withJoin(['rel'])` loads relations with a LEFT JOIN generated from the relations map, using the chain built so far.
- **`toSQL()`:** returns the SQL and params without running them.

Every adapter of the framework (Deno, PHP, ...) builds the same SQL for the same chain. `spec/query_cases.json` is the shared contract; it is the same file as the_deno's `spec/query_cases.json`.

**Tests:**
- `php tests/spec_test.php` replays the spec and needs no database.
- `DBTEST=1 ... php tests/db_test.php` runs the models against a real MySQL or PostgreSQL server. See the file header for the exact command.
