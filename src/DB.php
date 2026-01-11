<?php

namespace The;

use mysqli_result;

class DB extends \mysqli {

    public $result; // type hint removal for compatibility if needed, or keep
    private $query;
    public $placeholder = [];
    public $rows;
    protected $limit = null;
    protected $offset = null;
    protected $enable = null;
    protected array $where = [
        "AND" => [],
        "OR" => []
    ];

    public function __construct(
            private $table = "",
    ) {
        parent::__construct();
        parent::options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        parent::real_connect(dbhost, dbuser, dbpwd, dbname);
        if (mysqli_connect_error()) {
            exit('Connect Error (' . mysqli_connect_errno() . ') '
                    . mysqli_connect_error());
        }
    }

    public static function raw(string $sql, array $bind = []) {
        $new = (new self())->rawsql($sql);
        $new->placeholder = $bind;
        $new->bind();
        return $new->exe();
    }

    public function where($where) {
        return $this->WhereQ($where);
    }

    public function find($value, $key = "id") {
        return $this->findQ($value, $key)->LimitQ(1);
    }

    public function create($data) {
        return $this->InSet()->CreateQ($data)->exe();
    }

    public function update($data) {
        return $this->UpdateQ($data)->exe();
    }

    public function insert($data) {
        return $this->InSet()->InsertQ($data)->exe();
    }

    public function delete() {
        return $this->DelSet();
    }

    public function upsert($data) {
        $this->InSet()->UpsertQ($data)->exe();
        return $this;
    }

    public function exe() {
        $this->bind();
//         print_r($this->query);
        // print_r("<br>");
        $smt = $this->prepare($this->query);
        // print_r($this->placeholder);
        $smt->execute($this->placeholder);
        $this->result = $smt->get_result();
        // $this->result = $this->execute_query($this->query, $this->placeholder);
        $this->rows = $this->affected_rows;
        return $this;
    }

    public function bind() {
        if (count($this->where["AND"]) > 0) {
            if (isset($this->enable)) {
                $this->WhereQ(["enable", ["1"]]);
            }
            $this->query .= " WHERE ";
            if (count($this->where["AND"]) > 0) {
                $this->bindwhere($this->where["AND"], "AND");
            }
            if (count($this->where["OR"]) > 0) {
                $this->bindwhere($this->where["OR"], "OR");
            }
        }
        if ($this->limit && $this->limit > 0) {
            $this->query .= " LIMIT " . $this->limit;
        }
        if ($this->offset && $this->offset > 0) {
            $this->query .= " OFFSET " . $this->offset;
        }
    }

    public function bindwhere($data, $join = "AND") {
        $this->query .= ($join == "AND" ? "" : " $join ") . implode($join, array_map(function ($value) {
                            // print_r($value);
                            // print_r("<br>");
                            if (is_array($value[2])) {
                                $this->placeholder = [...$this->placeholder, ...$value[2]];
                                return " `$value[0]` $value[1] " . "(" . implode(",", array_map(fn() => "? ", $value[2])) . ")";
                            } elseif ($value[1] == "IN") {
                                $this->placeholder = [...$this->placeholder, $value[2]];
                                return " `$value[0]` $value[1] (?) ";
                            } else {
                                $this->placeholder = [...$this->placeholder, $value[2]];
                                return " `$value[0]` $value[1] ? ";
                            }
                        }, array_values($data)));
    }

    public function many() {
        return $this->result->fetch_all(MYSQLI_ASSOC);
    }

    public function first() {
        return $this->result->fetch_assoc();
    }

    public function lastInserted() {
        return $this->SelSet()->where(['id' => [$this->insert_id]])->exe();
    }

    public function getInserted() {
        return $this->SelSet()->rawsql(" ORDER BY updated_at DESC ")->LimitQ($this->rows);
    }

    public function findQ($value, $key = "id") {
        $this->WhereQ([$key => [$value]]);
        return $this;
    }

    public function UpsertQ($data) {
        $this->InsertQ($data);
        $this->query .= " on duplicate key update " . implode(",", array_map(function ($key) {
                            return "`$key`=values(`$key`)";
                        }, array_keys($data[0])));
        return $this;
    }

    public function rawsql($sql) {
        $this->query .= $sql;
        return $this;
    }

    public function CreateQ($data) {
        $this->placeholder = [...$this->placeholder, ...array_values($data)];
        $this->query .= "( " . implode(",", array_keys($data)) . " ) VALUES " . "(" . implode(",", array_map(fn() => "?", array_values($data))) . ")";
        return $this;
    }

    public function InsertQ($data) {
        if (count($data) > 0) {
            $this->query .= "( " . implode(",", array_keys($data[0])) . " ) VALUES " . implode(",", array_map(function ($row) {
                                $this->placeholder = [...$this->placeholder, ...array_values($row)];
                                return "(" . implode(",", array_map(fn() => "?", $row)) . ")";
                            }, $data));
        }
        return $this;
    }

    public function UpdateQ($data) {
        $this->placeholder = [];
        $this->query = "UPDATE $this->table SET " . implode(" , ", array_map(function ($key, $value) {
                            $this->placeholder = [...$this->placeholder, $value];
                            return "$key = ?";
                        }, array_keys($data), array_values($data)));
        return $this;
    }

    public function WhereQ($where, $type = "AND") {
        array_map(function ($key, $value) use ($type) {
            $this->where[$type][] = [$key, "IN", $value];
        }, array_keys($where), array_values($where));
        return $this;
    }

    public function WhereCustomQ($where, $type = "AND") {
        array_map(function ($value) use ($type) {
            $this->where[$type][] = [$value[0], $value[1], $value[2]];
        }, array_values($where));
        return $this;
    }

    public function SelSet($col = ["*"]) {
        $this->placeholder = [];
        $this->query = "SELECT " . implode(" , ", $col) . " FROM $this->table";
        return $this;
    }

    public function CountSet($id = "*") {
        $this->query = "SELECT count($id) FROM $this->table";
        return $this;
    }

    public function InSet() {
        $this->query = "INSERT INTO $this->table";
        return $this;
    }

    public function UpSet() {
        $this->query = "UPDATE $this->table SET ";
        return $this;
    }

    public function DelSet() {
        $this->query = "DELETE FROM $this->table ";
        return $this;
    }

    public function LimitQ(int $limit) {
        $this->limit = $limit;
        return $this;
    }

    public function OffsetQ(int $Offset) {
        $this->offset = $Offset;
        return $this;
    }
}
