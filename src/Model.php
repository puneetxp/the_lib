<?php

namespace The;

abstract class Model
{
    //items
    protected $items = [];
    protected $singular = false;
    protected DB $db;
    protected $relations = [];
    protected array $with = [];
    protected $table;
    protected $name;
    protected $model;
    protected $one;
    public $page = [];
    protected $relation = [];
    protected $fillable = [];
    //__construct
    public function __construct()
    {
        $this->db = new DB($this->table);
    }

    public function set_singular()
    {
        $this->singular = true;
        return $this;
    }

    public function paginate(int $pageNumber = 1, int $pageItems = 25)
    {
        $pageNumber = $_GET['page'] ?? $pageNumber;
        $pageItems = $_GET['pageItems'] ?? $pageItems;
        // count() runs a query, which clears the chain; keep it for the page query.
        $chain = $this->db->state();
        $this->page['result'] = $this->count();
        $this->db->restore($chain);
        if ($this->page['result']) {
            $this->page['pageNumber'] = $pageNumber;
            $this->page['pageItems'] = $pageItems;
            $this->page['totalpages'] = (int)$this->page['result'] / (int)$this->page['pageItems'];
            $this->page['get'] = http_build_query($_GET);
            $offset = ($pageNumber - 1) * $pageItems;
            while ($offset > $this->page['result']) {
                $offset -= $pageItems;
            }
            $this->db->OffsetQ($offset)->LimitQ($pageItems);
            return $this->get();
        } else {
            return null;
        }
    }

    protected function pages(int $number = 5)
    {
        $pages = [];
        $int = intdiv($number, 2);
        if ($this->page['totalpages'] <= $number) {
            for ($i = 1; $i >= $this->page['totalpages']; ++$i) {
                $pages[$i] = http_build_query(["page" => $i]) . "&" . $this->page['get'];
            }
        } elseif ($this->page['pageNumber'] > $int && $this->page['pageNumber'] >= $this->page['totalpages'] - $int) {
            $i = $this->page['pageNumber'] - $int;
            while (count($pages) < $number) {
                $pages[$i] = http_build_query(["page" => $i]) . "&" . $this->page['get'];
                ++$i;
            }
        } else {
            if ($this->page['pageNumber'] < $int) {
                $i = 1;
            } else {
                $i = $this->page['totalpages'] - $number;
            }
            while (count($pages) < $number) {
                $pages[$i] = http_build_query(["page" => $i]) . "&" . $this->page['get'];
                ++$i;
            }
        }
        return $pages;
    }

    //GET_data
    //mulitple
    public static function all()
    {
        $x = (new static());
        $x->db->SelSet();
        $x->get();
        return $x;
    }

    // ---- the chain ------------------------------------------------------
    // where, whereHas, whereNotHas, orderBy and limit work both as the start
    // of a query (User::where([...])) and chained (->where([...])). PHP runs
    // a static method even when called on an instance, so they are protected
    // and reached through __callStatic / __call.

    private const CHAIN = ['where', 'whereHas', 'whereNotHas', 'orderBy', 'limit'];

    public static function __callStatic($method, $args)
    {
        if (!in_array($method, self::CHAIN, true)) {
            throw new \BadMethodCallException(static::class . "::$method() does not exist");
        }
        return (new static())->$method(...$args);
    }

    public function __call($method, $args)
    {
        if (!in_array($method, self::CHAIN, true)) {
            throw new \BadMethodCallException(static::class . "->$method() does not exist");
        }
        return $this->$method(...$args);
    }

    /**
     * Add conditions; calls chain with AND: where(['a' => 1])->where(['b' => [2, 3]]).
     * Columns that are not on the model are ignored. The chain is cleared after
     * every query; pass $reset = true to drop conditions added before.
     */
    protected function where($where, bool $reset = false)
    {
        $reset && $this->db->resetdata();
        $this->db->where(Req::get((array) $this->model, (array) $where));
        return $this;
    }

    /**
     * Keep rows that have at least one related row, by relation name. $where is
     * an array of conditions or a function that builds on the related model:
     *   Book_stock::whereHas('book', ['public_listing' => 1])
     *   Book::whereHas('book_stock', fn ($s) => $s->where(['publish' => 1])->whereHas('product', ['enable' => 1]))
     */
    protected function whereHas(string $relation, array|callable|null $where = null, string $type = 'AND')
    {
        return $this->relationExists($relation, $where, $type, 'EXISTS');
    }

    /** Keep rows with no related row matching $where (see whereHas). */
    protected function whereNotHas(string $relation, array|callable|null $where = null, string $type = 'AND')
    {
        return $this->relationExists($relation, $where, $type, 'NOT EXISTS');
    }

    protected function orderBy(string $column, string $direction = 'ASC')
    {
        $this->db->OrderQ($column, $direction);
        return $this;
    }

    protected function limit(int $count, int $offset = 0)
    {
        if ($count < 0 || $offset < 0) {
            throw new \InvalidArgumentException("limit needs whole numbers >= 0, got $count, $offset");
        }
        $this->db->LimitQ($count)->OffsetQ($offset);
        return $this;
    }

    /** The SQL and params the chain would run, without running it. */
    public function toSQL(): array
    {
        return $this->db->toSQL();
    }

    // The name this model's table goes by in the SQL being built: an alias
    // when it is the inner side of a whereHas.
    protected ?string $sqlName = null;
    // How deep this model sits in nested whereHas calls (0 = the query itself).
    protected int $hasDepth = 0;

    private function relationExists(string $relation, array|callable|null $where, string $type, string $op)
    {
        $rel = $this->relations[$relation] ?? null;
        if (!$rel) {
            throw new \InvalidArgumentException("{$this->name}: no relation \"$relation\" (has: " . implode(', ', array_keys($this->relations)) . ")");
        }
        $related = new $rel['callback']();
        // Aliased <table>__<depth> so relations to the same table and nested
        // calls refer to the right row; the alias depends only on the chain.
        $related->hasDepth = $this->hasDepth + 1;
        $alias = $rel['table'] . '__' . $related->hasDepth;
        $related->sqlName = $alias;
        if (is_callable($where)) {
            $where($related);
        } elseif ($where) {
            $related->where($where);
        }
        $inner = $related->db->whereSQL();
        $related->db->resetdata();
        $q = [DB::class, 'ident'];
        $this->db->WhereRawQ(
            "$op (SELECT 1 FROM {$rel['table']} AS {$q($alias)} WHERE {$q($alias)}.{$q($rel['key'])} = "
                . $q($this->sqlName ?? $this->table) . ".{$q($rel['name'])}"
                . ($inner['sql'] !== '' ? " AND ({$inner['sql']})" : '') . ')',
            $inner['params'],
            $type
        );
        return $this;
    }

    /**
     * Load rows with their related rows in one query: a LEFT JOIN per relation,
     * generated from the relations map, using the chain built so far. Related
     * columns are nested as $row[$relation].
     */
    public function withJoin(array $relations)
    {
        $rows = DB::raw(...array_values($this->withJoinSQL($relations)))->many();
        $this->items = array_map(function ($row) use ($relations) {
            $base = [];
            $nested = [];
            foreach ($row as $key => $value) {
                $p = strpos($key, '__');
                $rel = $p === false ? null : substr($key, 0, $p);
                if ($rel !== null && in_array($rel, $relations, true)) {
                    $nested[$rel][substr($key, $p + 2)] = $value;
                } else {
                    $base[$key] = $value;
                }
            }
            return $base + $nested;
        }, $rows);
        return $this;
    }

    /** The SQL withJoin($relations) would run for the chain so far. */
    public function withJoinSQL(array $relations): array
    {
        $q = [DB::class, 'ident'];
        $base = $this->table;
        $select = $this->selectList($base, (array) $this->model);
        $joins = '';
        foreach ($relations as $relName) {
            $rel = $this->relations[$relName] ?? null;
            if (!$rel) {
                throw new \InvalidArgumentException("{$this->name}: no relation \"$relName\" (has: " . implode(', ', array_keys($this->relations)) . ")");
            }
            $alias = "r_$relName";
            array_push($select, ...$this->selectList($alias, (array) ((new $rel['callback']())->model ?? []), $relName));
            $joins .= " LEFT JOIN {$rel['table']} {$q($alias)} ON {$q($base)}.{$q($rel['name'])} = {$q($alias)}.{$q($rel['key'])}";
        }
        $where = $this->db->whereSQL($base);
        $sql = "SELECT " . implode(', ', $select) . " FROM {$this->table} {$q($base)}$joins"
            . ($where['sql'] !== '' ? " WHERE {$where['sql']}" : '')
            . $this->db->orderSQL($base) . $this->db->limitSQL();
        $this->db->resetdata();
        return ['sql' => $sql, 'params' => $where['params']];
    }

    private function selectList(string $alias, array $cols, ?string $prefix = null): array
    {
        $q = [DB::class, 'ident'];
        if (!$cols) {
            return [$q($alias) . '.*'];
        }
        return array_map(fn ($c) => $q($alias) . '.' . $q($c) . ' AS ' . $q($prefix ? "{$prefix}__$c" : $c), $cols);
    }

    public function andwhere($data)
    {
        $this->db->WhereQ($data);
        return $this;
    }

    public function orwhere($data)
    {
        $this->db->WhereQ($data, "OR");
        return $this;
    }

    public function andWhereC($data)
    {
        $this->db->WhereCustomQ($data);
        return $this;
    }

    public function orWhereC($data)
    {
        $this->db->WhereCustomQ($data, "OR");
        return $this;
    }

    public static function wherec($where)
    {
        return (new static())->_wherec($where);
    }

    public function get()
    {
        $this->db->SelSet()->exe();
        $this->items = (array) $this->db->many();
        return $this;
    }

    public function getnull()
    {
        $this->db->SelSet()->exe();
        $this->items = (array) $this->db->many();
        if (count($this->items)) {
            return $this;
        }
        return null;
    }

    public function count()
    {
        $this->db->CountSet()->exe();
        return (array) $this->db->many();
    }

    public function first($select = ["*"])
    {
        $this->items = (array) $this->db->SelSet($select)->exe()->first();
        if (count($this->items) > 0) {
            $this->singular = true;
            return $this;
        }
        return null;
    }

    public function _wherec($where = [])
    {
        $this->db->SelSet()->WhereCustomQ($where);
        return $this;
    }

    public function _where($where = [])
    {
        $this->db->where(Req::get($this->model, $where));
        return $this;
    }

    //single
    public static function find($value, $key = 'id')
    {
        $x = (new static());
        $x->db->find($value, $key);
        return $x->first();
    }

    public function getInserted()
    {
        $this->db->lastInserted();
        $this->items = (array) $this->db->first();
        $this->singular = true;
        return $this;
    }

    public function getsInserted()
    {
        $this->db->getInserted()->exe();
        $this->items = (array) $this->db->many();
        return $this;
    }

    public static function create($data = [])
    {
        $x = (new static());
        $x->db->create(Req::get($x->model, $data));
        return $x;
    }

    //insert
    public static function insert($data)
    {
        $x = (new static());
        $x->db->insert($data);
        return $x;
    }

    //update
    public static function upsert($data)
    {
        return (new static())->_upsert($data);
    }

    public function update($data)
    {
        $this->db->update(Req::get($this->model, $data));
        return $this;
    }

    public function _upsert($data)
    {
        $this->db->upsert(Req::array($this->model, $data));
        return $this;
    }

    public function toggle($where, $filed = "enable")
    {
        $x = (new static());
        // UpSet() already wrote "UPDATE <table> SET "; this was "SET SET ...".
        // CASE instead of NOT: flag columns are 0/1 integers, and PostgreSQL's
        // NOT only takes booleans. Same result as MySQL's NOT on 0/1.
        $f = DB::ident($filed);
        $x->db->UpSet()->WhereQ($where)->rawsql("$f = CASE WHEN $f = 0 THEN 1 ELSE 0 END")->exe();
        return $x;
    }

    //delete
    public static function delete($where)
    {
        return (new static())->db->where($where)->delete()->exe();
    }

    public function del()
    {
        $this->db->delete();
        return $this;
    }

    public function clean($data)
    {
        return array_map(fn ($item) => array_filter($item, fn ($key) => in_array($key, $this->fillable)), $data);
    }

    //default output
    public function __toString()
    {
        return Response::json($this->items);
    }

    //array output
    public function array()
    {
        return $this->items;
    }

    //call realtionship
    //Better for spa and fastest way
    public function with($data, bool $first = true)
    {
        if (count($this->items) || $this->singular) {
            $x = [];
            if (is_array($data)) {
                $first && $this->with = $data;
                foreach ($data as $item) {
                    if (is_array($item)) {
                        foreach ($item as $key => $value) {
                            $this->relation[$key]["class"] = $this->relation($key)?->with($value);
                            $x = array_merge($this->isnull($this->relation[$key]["class"]), $x);
                        }
                    } else {
                        $this->relation[$item]["class"] = $this->relation($item);
                        $x[$item] = $this->isnull($this->relation[$item]["class"]);
                    }
                }
            } else {
                $first && $this->with = [$data];
                $this->relation[$data]["class"] = $this->relation($data);
                $x[$data] = $this->isnull($this->relation[$data]["class"]);
            }
            $this->singular ? ($x[$this->name] = [$this->items]) : ($x[$this->name] = $this->items);
            $this->items = $x;
        }
        return $this;
    }

    public function join($joins, $where = [])
    {
        $joinSpecs = [];
        foreach ($joins as $key => $val) {
            $relationName = is_int($key) ? $val : $key;
            $cols = is_array($val) ? ($val['cols'] ?? []) : [];
            $alias = is_array($val) ? ($val['alias'] ?? $relationName) : $relationName;

            if (isset($this->relations[$relationName])) {
                $r = $this->relations[$relationName];
                $joinSpecs[] = [
                    'alias' => $alias,
                    'table' => $r['table'],
                    'localKey' => $r['name'],
                    'foreignKey' => $r['key'],
                    'cols' => $cols,
                    'prefix' => $relationName
                ];
            }
        }

        $query = DB\sqlBuilder::buildJoinQuery(
            $this->table,
            $this->table,
            [],
            $joinSpecs,
            $where
        );

        $this->db->rawsql($query['sql']);
        $this->db->placeholder = $query['placeholders'];
        $this->items = (array) $this->db->exe()->many();
        return $this;
    }

    public static function joins($joins, $where = [])
    {
        return (new static())->join($joins, $where);
    }

    public function isnull($x)
    {
        if ($x == null) {
            return [];
        }
        return $x?->array();
    }

    public function relation($data)
    {
        $where = [];
        $x = ($this->singular ?
                [$this->items[$this->relations[$data]['name']]] :
                array_column($this->items, $this->relations[$data]['name']));
        if (count($x) > 0) {
            $where[$this->relations[$data]['key']] = $x;
            return (new $this->relations[$data]['callback']())->where($where)->get();
        }
        return null;
    }

    //bindintosomepattern
    public function sort()
    {
        if (count($this->with)) {
            $this->items = ($this->sortout($this->with, $this->items[$this->name]));
        }
        $this->singular && $this->items = $this->items[0];
        return $this;
    }

    protected function sortout($relations, $data, $base = null)
    {
        foreach ($relations as $relation) {
            $data = is_array($relation) ?
                    $this->filter_relations($relation, $data, $base ?? $this->items) :
                    $this->filter_relation($relation, $data, $base ?? $this->items);
        }
        return $data;
    }

    public function filter_relation(string $relation, array $data, $base)
    {
        return array_values(array_map(function ($item) use ($relation, $base) {
            $y = array_values(
                array_filter(
                    ($base[$relation] ?? $this->items[$relation]) ?? [],
                    fn ($model_item) =>
                            $model_item[$this->relations[$relation]['key']] == $item[$this->relations[$relation]['name']]
                )
            );
            // if $y have sort key $this->relations['callback'] class having fillable having 'sort'
            if (count($y) > 0 && in_array('sort', (new $this->relations[$relation]['callback']())->fillable)) {
                usort($y, fn ($a, $b) => $a['sort'] - $b['sort']);
            }
            $item[$relation] = in_array($relation, $this->one ?? []) ? ($y[0] ?? "") : $y;
            return $item;
        }, $data));
    }

    public function filter_relations(array $relation, array $data, array $base)
    {
        foreach ($relation as $key => $item) {
            if (is_string($key)) {
                $data = $this->filter_relation($key, $data, $base);
                foreach ($data as $datakey => $value) {
                    $data[$datakey][$key] = $this->relation[$key]["class"]->sortout($item, $value[$key], $base);
                }
            } else {
                if (is_array($item)) {
                    foreach ($data as $datakey => $value) {
                        $data[$datakey] = $this->sortout($item, $base[$this->name], $base);
                    }
                } else {
                    $data[$item] = $this->filter_relation($item, $data, $base);
                }
            }
        }
        return $data;
    }
}
