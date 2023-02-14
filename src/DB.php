<?php

namespace The;

use mysqli_result;

class DB extends \mysqli
{
    public mysqli_result|bool $result;
    private $query;
    private $placeholder = [];
    public function __construct(
        private $table,
        protected $col = ["*"]
    ) {
        parent::__construct();
        parent::options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        parent::real_connect(dbhost, dbuser, dbpwd, dbname);
        if (mysqli_connect_error()) {
            exit('Connect Error (' . mysqli_connect_errno() . ') '
                . mysqli_connect_error());
        }
    }
    public function where($where = [])
    {
        $this->SelSet()->WhereQ($where);
        return $this;
    }

    public function find($value, $key)
    {
        $where = [];
        if (is_array($value)) {
            $where[$key] = $value;
        } else {
            $where[$key] = [$value];
        }
        return $this->SelSet()->WhereQ($where)->LimitQ(1)->exe();
    }

    public function create($data)
    {
        return $this->InSet()->InsertQ($data)->exe();
    }
    public function update($where)
    {
        return $this->SelSet()->WhereQ($where)->exe();
    }
    public function delete($where)
    {
        return $this->DelSet()->WhereQ($where)->exe();
    }
    public function upsert($data)
    {
        return $this->InSet()->UpsertQ($data)->exe();
    }
    public function exe()
    {
        $smt = $this->prepare($this->query);
        $smt->execute($this->placeholder);
        $this->result = $smt->get_result();
        // $this->result = $this->execute_query($this->query, $this->placeholder);
        return $this;
    }
    public function UpsertQ($data)
    {
        $this->InsertQ($data);
        $this->query .= " on duplicate key update " . implode(",", array_map(function ($key) {
            return "`$key`=values(`$key`)";
        }, array_keys($data[0])));
        return $this;
    }
    public function InsertQ($data)
    {
        if (count($data) > 0) {
            $this->query .= "( " . implode(",", array_keys($data[0])) . " ) VALUES " . implode(",", array_map(function ($row) {

                $this->placeholder = [...$this->placeholder, ...array_values($row)];
                return "(" . implode(",", array_map(function ($col) {
                    return "?";
                }, $row)) . ")";
            }, $data));
        }
        return $this;
    }
    public function UpdateQ($data)
    {
        $this->query = "UPDATE $this->table SET " . implode(" , ", array_map(function ($key, $value) {
            $this->placeholder = [...$this->placeholder, $value];
            return "$key = (" . implode(",", array_map(function ($x) {
                return "?";
            }, $value)) . ")";
        }, array_keys($data), array_values($data)));
        return $this;
    }
    public function SelectQ()
    {
        $this->query = "SELECT " . implode(" , ", $this->col) . " FROM $this->table";
        return $this;
    }
    public function WhereQ($where)
    {
        $this->query .= " WHERE " .  implode(" AND ", array_map(function ($key, $value) {
            $this->placeholder = [...$this->placeholder, ...$value];
            return " `$key` IN (" . implode(",", array_map(function () {
                return "?";
            }, $value)) . ")";
        }, array_keys($where), array_values($where)));
        return $this;
    }
    public function SelSet()
    {
        $this->query = "SELECT " . implode(" , ", $this->col) . " FROM $this->table";
        return $this;
    }
    public function InSet()
    {
        $this->query = "INSERT INTO $this->table";
        return $this;
    }
    public function UpSet()
    {
        $this->query = "UPDATE $this->table SET ";
        return $this;
    }
    public function DelSet()
    {
        $this->query = "DELETE FROM $this->table ";
        return $this;
    }
    public function  LimitQ(int $limit)
    {
        $this->query .= " LIMIT " . $limit;
        return $this;
    }
}
