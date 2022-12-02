<?php

namespace App\TheDep;

class DB extends \mysqli {

// single instance of self shared among all instances
    private static $instance = null;

// db connection config vars
//This method must be static, and must return an instance of the object if the object
//does not already exist.
    public static function getInstance() {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
        }
        return self::$instance;
    }

// The clone and wakeup methods prevents external instantiation of copies of the Singleton class,
// thus eliminating the possibility of duplicate objects.
    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    public function __wakeup() {
        trigger_error('Deserializing is not allowed.', E_USER_ERROR);
    }

    private $result;
    private $_sanitized_data;

    private function __construct(
            private $_table = '',
            private $_fillable = [],
            private $_table_id = '',
            private $_table_key = '',
            private $_table_col = '*',
            private $_user = DBUSER,
            private $_pass = DBPWD,
            private $_dbName = DBNAME,
            private $_dbHost = DBHOST,
    ) {
        parent::__construct();
        parent::options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        parent::real_connect($this->_dbHost, $this->_user, $this->_pass, $this->_dbName);
        if (mysqli_connect_error()) {
            exit('Connect Error (' . mysqli_connect_errno() . ') '
                    . mysqli_connect_error());
        }
    }

    //intialize from model
    public static function inti($table, $fillable, $id, $key, $col = ['*']) {
        return new self($table, $fillable, $id, $key, implode(',', $col));
    }

    public static function __callStatic($method, $parameters) {
        return (new static)->$method(...$parameters);
    }

    //get data
    //mutiple
    //all
    public function all() {
        $this->result = $this->query("SELECT $this->_table_col FROM $this->_table");
        return $this;
    }

    //return many
    public function many() {
        if ($this->result->num_rows) {
            return $this->result->fetch_all(MYSQLI_ASSOC);
        } else {
            return [];
        }
    }

    //single return
    public function one() {
        if ($this->result->num_rows) {
            return $this->result->fetch_assoc();
        } else {
            return null;
        }
    }

    public function find() {
        $this->result = $this->query("SELECT $this->_table_col FROM $this->_table WHERE $this->_table_key = '$this->_table_id' LIMIT 1");
        return $this->one();
    }

    public function where() {
        if (is_array($this->_table_id)) {
            $mysql_id = implode("','", $this->_table_id);
        } else {
            $mysql_id = $this->_table_id;
        }
        $this->result = $this->query("SELECT " . $this->_table_col . " FROM $this->_table WHERE $this->_table_key IN ('$mysql_id') ");
        return $this;
    }

    //reale_escape_string
    public function array_sanized($data) {
        if ($data == '') {
            $data = $_POST;
        }
        $data_fillable = array_intersect_key($data, array_flip($this->_fillable));
        foreach ($data_fillable as $key => $value) {
            $data_fillable[$key] = $this->real_escape_string(addcslashes($value, '%_'));
        }
        $this->_sanitized_data = $data_fillable;
    }

    //create
    public function create($data) {
        $this->array_sanized($data);
        $key_sql = array_keys($this->_sanitized_data);
        $value_sql = array_values($this->_sanitized_data);
        if ($this->query("INSERT INTO $this->_table (`" . implode("`,`", $key_sql) . "`) VALUES" . "('" . implode("','", $value_sql) . "')")) {
            $this->_table_key = 'id';
            $this->_table_id = $this->insert_id;
            return $this->find();
        } else {
            $this->error;
        }
    }

    //create mulitple batch
    public function upsert($data) {
        $this->array_sanized($data);
        $key_sql = array_keys($this->_sanitized_data);
        $value_sql = array_values($this->_sanitized_data);
        if ($this->query("REPLACE INTO $this->_table (`" .
                        implode("`,`", $key_sql) .
                        "`) VALUES" . "('" . implode("','", $value_sql) . "')")) {
            $this->_table_key = 'id';
            $this->_table_id = $key_sql;
            return $this->where();
        } else {
            $this->error;
        }
    }

    //update
    public function update($data) {
        $this->array_sanized($data);
        $r = [];
        foreach ($this->_sanitized_data as $key => $value) {
            array_push($r, '`' . $key . "`='" . $value . "'");
        }
        if ($this->query("UPDATE `$this->_table` SET " . implode(",", $r) . "  WHERE `$this->_table_key` = '$this->_table_id';")) {
            return $this->find();
        } else {
            $this->error;
        }
    }

    //delete
    public function delete($id, $key = 'id') {
        if ($this->query("DELETE FROM $this->_table WHERE $key IN ('" . implode(',', $id) . "')")) {
            return $id;
        } else {
            $this->error;
        }
    }


}
