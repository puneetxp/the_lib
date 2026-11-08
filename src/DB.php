<?php

namespace The;

use PDO;
use PDOStatement;

/**
 * Query builder and connection for MySQL/MariaDB (default) and PostgreSQL.
 *
 * Queries are written MySQL-style (? placeholders, backtick identifiers) and
 * rewritten for PostgreSQL before they run, so the SQL a chain produces is
 * the same in every adapter of the framework (see spec/README.md).
 *
 * One PDO connection is shared by every model in the process and opened on
 * the first query. Configuration comes from constants (dbhost or DBHOST, ...)
 * or the environment: dbdriver (mysql|postgres), dbhost, dbport, dbuser,
 * dbpwd, dbname.
 */
class DB
{
    public const RAW = "\0raw";

    private static ?PDO $pdo = null;
    private static string $driver = '';
    /** @var array<string, PDOStatement> prepared statements by SQL (PostgreSQL) */
    private static array $statements = [];

    public ?PDOStatement $result = null;
    private array $fetched = [];
    private int $cursor = 0;
    public $rows = 0;
    public $insert_id = null;
    /** @var int[] every id an INSERT created (PostgreSQL reports them all) */
    public array $insert_ids = [];

    private string $query = '';
    public array $placeholder = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected $enable = null;
    protected array $where = ["AND" => [], "OR" => []];
    protected array $order = [];
    protected string $action = 'RAW';
    /** Table name or alias to qualify columns with (join queries). */
    protected ?string $qualifier = null;

    public function __construct(private $table = "")
    {
    }

    // ---- connection -------------------------------------------------------

    private static function config(string $name, ?string $default = null): ?string
    {
        foreach ([$name, strtoupper($name)] as $c) {
            if (defined($c)) {
                return (string) constant($c);
            }
        }
        $env = getenv(strtoupper($name));
        return $env === false ? $default : $env;
    }

    public static function driver(): string
    {
        if (self::$driver === '') {
            $d = strtolower(self::config('dbdriver', 'mysql'));
            self::$driver = match ($d) {
                '', 'mysql', 'mariadb', 'mysqli' => 'mysql',
                'postgres', 'postgresql', 'pgsql', 'pg' => 'postgres',
                default => throw new \InvalidArgumentException("Unknown dbdriver \"$d\". Use mysql (default) or postgres."),
            };
        }
        return self::$driver;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $pg = self::driver() === 'postgres';
            $host = self::config('dbhost', 'localhost');
            $port = self::config('dbport', $pg ? '5432' : '3306');
            $name = self::config('dbname', '');
            $dsn = $pg
                ? "pgsql:host=$host;port=$port;dbname=$name"
                : "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            self::$pdo = new PDO($dsn, self::config('dbuser'), self::config('dbpwd'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                // MySQL: client-side prepares, one round trip per query; PHP 8.1+
                // still returns native ints/floats. PostgreSQL: server prepares,
                // cached below.
                PDO::ATTR_EMULATE_PREPARES => !$pg,
            ]);
        }
        return self::$pdo;
    }

    /** Close the shared connection (tests, long-running scripts). */
    public static function close(): void
    {
        self::$statements = [];
        self::$pdo = null;
        self::$driver = '';
    }

    /**
     * MySQL-style SQL for PostgreSQL: backtick identifiers become double-quoted.
     * Strings, quoted identifiers, dollar quotes and comments are left alone;
     * ? placeholders stay (PDO numbers them).
     */
    public static function toPostgres(string $sql): string
    {
        if (!str_contains($sql, '`')) {
            return $sql;
        }
        $out = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len;) {
            $c = $sql[$i];
            if ($c === "'" || $c === '"') {
                $j = $i + 1;
                while ($j < $len) {
                    if ($sql[$j] === $c) {
                        if (($sql[$j + 1] ?? '') === $c) {
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    if ($c === "'" && $sql[$j] === '\\') {
                        $j++;
                    }
                    $j++;
                }
                $out .= substr($sql, $i, $j + 1 - $i);
                $i = $j + 1;
            } elseif ($c === '`') {
                $j = $i + 1;
                $ident = '';
                while ($j < $len) {
                    if ($sql[$j] === '`') {
                        if (($sql[$j + 1] ?? '') === '`') {
                            $ident .= '`';
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $ident .= $sql[$j++];
                }
                $out .= '"' . str_replace('"', '""', $ident) . '"';
                $i = $j + 1;
            } elseif ($c === '-' && ($sql[$i + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $i);
                $end = $end === false ? $len : $end;
                $out .= substr($sql, $i, $end - $i);
                $i = $end;
            } elseif ($c === '/' && ($sql[$i + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $len : $end + 2;
                $out .= substr($sql, $i, $end - $i);
                $i = $end;
            } elseif ($c === '$' && preg_match('/\G\$([A-Za-z_][A-Za-z0-9_]*)?\$/', $sql, $m, 0, $i)) {
                $end = strpos($sql, $m[0], $i + strlen($m[0]));
                $end = $end === false ? $len : $end + strlen($m[0]);
                $out .= substr($sql, $i, $end - $i);
                $i = $end;
            } else {
                $out .= $c;
                $i++;
            }
        }
        return $out;
    }

    /** Quote one identifier, MySQL-style (rewritten for PostgreSQL when run). */
    public static function ident(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    // ---- running ----------------------------------------------------------

    public static function raw(string $sql, array $bind = [])
    {
        $new = (new self())->rawsql($sql);
        $new->placeholder = $bind;
        return $new->exe();
    }

    public function exe()
    {
        $this->bind();
        $pg = self::driver() === 'postgres';
        $sql = $this->query;
        if ($pg && $this->action === 'INSERT') {
            // PostgreSQL has no insert id; read the ids back from the rows.
            $sql .= ' RETURNING *';
        }
        if ($pg) {
            $sql = self::toPostgres($sql);
            $smt = self::$statements[$sql] ??= self::pdo()->prepare($sql);
        } else {
            $smt = self::pdo()->prepare($sql);
        }
        $smt->execute($this->placeholder);
        $this->result = $smt;
        $this->rows = $smt->rowCount();
        // Read the rows now: a cached statement is reused by the next query
        // with the same SQL, which would drop rows not yet fetched.
        $this->fetched = $smt->columnCount() > 0 ? $smt->fetchAll() : [];
        $this->cursor = 0;
        if ($this->action === 'INSERT') {
            if ($pg) {
                $this->insert_ids = array_map(fn ($r) => $r['id'] ?? null, $this->fetched);
                $this->insert_ids = array_values(array_filter($this->insert_ids, fn ($id) => $id !== null));
                $this->insert_id = $this->insert_ids[0] ?? null;
            } else {
                $id = (int) self::pdo()->lastInsertId();
                $this->insert_id = $id ?: null;
                $this->insert_ids = $id ? range($id, $id + max($this->rows, 1) - 1) : [];
            }
        }
        $this->resetdata();
        return $this;
    }

    /** The rows not read yet with first(). */
    public function many()
    {
        $rows = $this->cursor ? array_slice($this->fetched, $this->cursor) : $this->fetched;
        $this->cursor = count($this->fetched);
        return $rows;
    }

    /** The next row, [] when there is none. */
    public function first()
    {
        return $this->fetched[$this->cursor++] ?? [];
    }

    /** The SELECT this builder would run, without running it; resets the builder. */
    public function toSQL(): array
    {
        if ($this->action !== 'SELECT') {
            $this->SelSet();
        }
        $this->bind();
        $out = ['sql' => $this->query, 'params' => $this->placeholder];
        $this->resetdata();
        return $out;
    }

    /** Clear conditions, order and limit: every query starts from nothing. */
    public function resetdata(): void
    {
        $this->placeholder = [];
        $this->where = ["AND" => [], "OR" => []];
        $this->order = [];
        $this->limit = null;
        $this->offset = null;
        $this->action = 'RAW';
    }

    /** Save and restore the chain, for code that runs two queries on one chain. */
    public function state(): array
    {
        return [$this->where, $this->order, $this->limit, $this->offset];
    }

    public function restore(array $state): static
    {
        [$this->where, $this->order, $this->limit, $this->offset] = $state;
        return $this;
    }

    // ---- building ---------------------------------------------------------

    public function bind()
    {
        if (count($this->where["AND"]) > 0) {
            $this->query .= " WHERE ";
            $this->bindwhere($this->where["AND"], "AND");
            if (count($this->where["OR"]) > 0) {
                $this->bindwhere($this->where["OR"], "OR");
            }
        }
        if ($this->action === 'SELECT') {
            $this->query .= $this->orderSQL();
        }
        $this->query .= $this->limitSQL();
    }

    protected function colRef(string $column): string
    {
        return $this->qualifier !== null
            ? self::ident($this->qualifier) . '.' . self::ident($column)
            : self::ident($column);
    }

    public function bindwhere($data, $join = "AND")
    {
        $parts = [];
        foreach ($data as [$col, $op, $value]) {
            if ($col === self::RAW) {
                // A condition the ORM built itself (whereHas).
                array_push($this->placeholder, ...$value);
                $parts[] = " $op ";
            } elseif (is_array($value)) {
                if (count($value) === 0) {
                    // "IN ()" is invalid SQL: nothing is in an empty list.
                    $parts[] = strtoupper($op) === 'NOT IN' ? ' 1 = 1 ' : ' 1 = 0 ';
                    continue;
                }
                array_push($this->placeholder, ...array_values($value));
                $parts[] = ' ' . $this->colRef($col) . " $op (" . implode(', ', array_fill(0, count($value), '?')) . ')';
            } elseif ($op === 'IN') {
                $this->placeholder[] = $value;
                $parts[] = ' ' . $this->colRef($col) . " $op (?) ";
            } else {
                $this->placeholder[] = $value;
                $parts[] = ' ' . $this->colRef($col) . " $op ? ";
            }
        }
        $this->query .= ($join == "AND" ? "" : " $join ") . implode(" $join ", $parts);
    }

    /** The WHERE conditions so far, without the WHERE keyword; the chain is kept. */
    public function whereSQL(?string $qualifier = null): array
    {
        [$query, $placeholder] = [$this->query, $this->placeholder];
        $this->query = '';
        $this->placeholder = [];
        $this->qualifier = $qualifier;
        try {
            if (count($this->where["AND"]) > 0) {
                $this->bindwhere($this->where["AND"], "AND");
                if (count($this->where["OR"]) > 0) {
                    $this->bindwhere($this->where["OR"], "OR");
                }
            }
            return ['sql' => trim($this->query), 'params' => $this->placeholder];
        } finally {
            $this->qualifier = null;
            [$this->query, $this->placeholder] = [$query, $placeholder];
        }
    }

    public function orderSQL(?string $qualifier = null): string
    {
        if (!$this->order) {
            return '';
        }
        $ref = fn ($c) => $qualifier !== null ? self::ident($qualifier) . '.' . self::ident($c) : self::ident($c);
        return ' ORDER BY ' . implode(', ', array_map(fn ($o) => $ref($o[0]) . ' ' . $o[1], $this->order));
    }

    public function limitSQL(): string
    {
        return ($this->limit ? " LIMIT " . $this->limit : '') . ($this->offset ? " OFFSET " . $this->offset : '');
    }

    public function where($where)
    {
        return $this->WhereQ($where);
    }

    public function find($value, $key = "id")
    {
        return $this->findQ($value, $key)->LimitQ(1);
    }

    public function create($data)
    {
        return $this->InSet()->CreateQ($data)->exe();
    }

    public function update($data)
    {
        return $this->UpdateQ($data)->exe();
    }

    public function insert($data)
    {
        return $this->InSet()->InsertQ($data)->exe();
    }

    public function delete()
    {
        return $this->DelSet();
    }

    public function upsert($data)
    {
        $this->InSet()->UpsertQ($data)->exe();
        return $this;
    }

    public function lastInserted()
    {
        return $this->SelSet()->where(['id' => [$this->insert_id]])->exe();
    }

    public function getInserted()
    {
        return $this->SelSet()->where(['id' => $this->insert_ids]);
    }

    public function findQ($value, $key = "id")
    {
        $this->WhereQ([$key => [$value]]);
        return $this;
    }

    /**
     * Insert, or update the row that already exists. MySQL matches any unique
     * key; PostgreSQL needs one named, so $conflict defaults to id.
     */
    public function UpsertQ($data, array $conflict = ['id'])
    {
        $this->InsertQ($data);
        $cols = array_keys($data[0] ?? []);
        if (self::driver() === 'postgres') {
            $set = array_values(array_diff($cols, $conflict));
            $this->query .= ' ON CONFLICT (' . implode(', ', array_map([self::class, 'ident'], $conflict)) . ') '
                . ($set ? 'DO UPDATE SET ' . implode(', ', array_map(fn ($c) => self::ident($c) . ' = EXCLUDED.' . self::ident($c), $set)) : 'DO NOTHING');
        } else {
            $this->query .= " ON DUPLICATE KEY UPDATE " . implode(", ", array_map(fn ($c) => self::ident($c) . ' = VALUES(' . self::ident($c) . ')', $cols));
        }
        return $this;
    }

    public function rawsql($sql)
    {
        $this->query .= $sql;
        return $this;
    }

    public function CreateQ($data)
    {
        array_push($this->placeholder, ...array_values($data));
        $this->query .= "( " . implode(",", array_map([self::class, 'ident'], array_keys($data))) . " ) VALUES ("
            . implode(",", array_fill(0, count($data), '?')) . ")";
        return $this;
    }

    public function InsertQ($data)
    {
        if (count($data) > 0) {
            $rows = [];
            foreach ($data as $row) {
                array_push($this->placeholder, ...array_values($row));
                $rows[] = "(" . implode(",", array_fill(0, count($row), '?')) . ")";
            }
            $this->query .= "( " . implode(",", array_map([self::class, 'ident'], array_keys($data[0]))) . " ) VALUES " . implode(",", $rows);
        }
        return $this;
    }

    public function WhereNotNull(string $key, $type = "AND")
    {
        return $this->WhereRawQ(self::ident($key) . ' IS NOT NULL', [], $type);
    }

    public function UpdateQ($data)
    {
        $this->action = 'UPDATE';
        $set = [];
        $params = [];
        foreach ($data as $key => $value) {
            $set[] = self::ident($key) . " = ?";
            $params[] = $value;
        }
        $this->placeholder = $params;
        $this->query = "UPDATE $this->table SET " . implode(" , ", $set);
        return $this;
    }

    public function WhereQ($where, $type = "AND")
    {
        // Column-name order, as in every adapter: the SQL never depends on key order.
        ksort($where, SORT_STRING);
        foreach ($where as $key => $value) {
            $this->where[$type][] = [$key, "IN", $value];
        }
        return $this;
    }

    public function WhereCustomQ($where, $type = "AND")
    {
        foreach ($where as $value) {
            $this->where[$type][] = [$value[0], $value[1], $value[2]];
        }
        return $this;
    }

    /** Add a condition the ORM generated (whereHas). Not for app SQL. */
    public function WhereRawQ(string $sql, array $params = [], $type = "AND")
    {
        $this->where[$type][] = [self::RAW, $sql, $params];
        return $this;
    }

    public function OrderQ(string $column, string $direction = 'ASC')
    {
        $d = strtoupper($direction);
        if ($d !== 'ASC' && $d !== 'DESC') {
            throw new \InvalidArgumentException("orderBy direction must be ASC or DESC, got \"$direction\"");
        }
        $this->order[] = [$column, $d];
        return $this;
    }

    public function SelSet($col = ["*"])
    {
        $this->action = 'SELECT';
        $this->placeholder = [];
        $this->query = "SELECT " . implode(" , ", $col) . " FROM $this->table";
        return $this;
    }

    public function CountSet($id = "*")
    {
        $this->action = 'COUNT';
        $this->query = "SELECT count($id) FROM $this->table";
        return $this;
    }

    public function InSet()
    {
        $this->action = 'INSERT';
        $this->query = "INSERT INTO $this->table";
        return $this;
    }

    public function UpSet()
    {
        $this->action = 'UPDATE';
        $this->query = "UPDATE $this->table SET ";
        return $this;
    }

    public function DelSet()
    {
        $this->action = 'DELETE';
        $this->query = "DELETE FROM $this->table ";
        return $this;
    }

    public function LimitQ(?int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function OffsetQ(?int $Offset)
    {
        $this->offset = $Offset;
        return $this;
    }
}
