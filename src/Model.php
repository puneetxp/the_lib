<?php

namespace The;

abstract class Model {

  //items
  protected $items = [];
  protected $singular = false;
  protected $col = ["*"];
  protected DB $db;

  //__construct
  public function __construct() {
    $this->db = new DB($this->table);
  }

  public function set_singular() {
    $this->singular = true;
    return $this;
  }

  public function set_col($col) {
    $this->col = $col;
    return $this;
  }

  //GET_data
  //mulitple
  public static function all() {
    $x = (new static());
    $x->db->SelSet();
    $x->get();
    return $x;
  }

  //where *
  public static function where($where) {
    return (new static())->_where($where);
  }

  public function andwhere($data) {
    $this->db->WhereQ($data);
    return $this;
  }

  public function orwhere($data) {
    $this->db->WhereQ($data, "OR");
    return $this;
  }

  public function andWhereC($data) {
    $this->db->WhereCustomQ($data);
    return $this;
  }

  public function orWhereC($data) {
    $this->db->WhereCustomQ($data, "OR");
    return $this;
  }

  public static function wherec($where) {
    return (new static())->_wherec($where);
  }

  public function get() {
    $this->db = $this->db->exe();
    $this->items = (array) $this->db->many();
    return $this;
  }

  public function first() {
    $this->db = $this->db->exe();
    $this->items = (array) $this->db->first();
    if (count($this->items) > 0) {
      $this->singular = true;
      return $this;
    }
    return null;
  }

  public function _wherec($where = []) {
    $this->db->SelSet()->WhereCustomQ($where);
    return $this;
  }

  public function _where($where = []) {
    $this->db->where(Req::get($this->model, $where));
    return $this;
  }

  //single
  public static function find($value, $key = 'id') {
    $x = (new static());
    $x->db->find($value, $key);
    $x->first();
    return $x;
  }

  public function getInserted() {
    $this->db->lastInserted();
    $this->items = (array) $this->db->first();
    $this->singular = true;
    return $this;
  }

  public function getsInserted() {
    $this->db->getInserted();
    $this->get();
    return $this;
  }

  public static function create($data = '') {
    $x = (new static());
    $x->db->create(Req::get($x->model, $data));
    return $x;
  }

  //insert
  public static function insert($data) {
    $x = (new static());
    $x->db->insert($data);
    return $x;
  }

  //update
  public static function upsert($data) {
    return (new static())->_upsert($data);
  }

  public function update($data) {
    $this->db->update(Req::get($this->model, $data));
    return $this;
  }

  public function _upsert($data) {
    $this->db->upsert(Req::array($this->model, $data));
    return $this;
  }

  public function toggle($where, $filed = "enable") {
    $x = (new static());
    $x->db->UpSet()->WhereQ($where)->rawsql("SET `$filed` = NOT `$filed`")->exe();
    return $x;
  }

  //delete
  public static function delete($where) {
    return (new static())->db->delete($where)->exe();
  }

  public function clean($data) {
    return array_map(fn($item) => array_filter($item, fn($key) => in_array($key, $this->fillable)), $data);
  }

  //default output
  public function __toString() {
    return Response::json($this->items);
  }

  //array output
  public function array() {
    return $this->items;
  }

  //call realtionshi
  //Better for spa and fastest way
  public function wfast($data, $single = []) {
    $x = [];
    if (is_array($data)) {
      foreach ($data as $item) {
        if (is_array($item)) {
          $x = array_merge($this->isnull($this->relation(array_keys($item)[0])
                                  ?->wfast(array_values($item)[0])), $x);
        } else {
          $x[$item] = $this->isnull($this->relation($item));
        }
      }
    } else {
      $x[$data] = $this->isnull($this->relation($data));
    }
    $x[$this->name] = $this->items;
    $this->items = $x;
    foreach ($single as $key) {
      $this->items[$key] = $this->items[$key][0];
    }
    return $this;
  }

  public function isnull($x) {
    if ($x == null) {
      return [];
    }
    return $x?->array();
  }

  //load with relationship with some filltering
  public function with($data) {
    if (is_array($data)) {
      foreach ($data as $item) {
        if (is_array($item)) {
          $this->with_array($item);
        } else {
          $this->with_string($item);
        }
      }
    } else {
      $this->with_string($data);
    }
    return $this;
  }

  public function with_string($data) {
    $return = $this->relation($data)?->array();
    if ($this->singular) {
      isset($this->relations[$data]['level']) ? $this->items[$data] = $return[0] : $this->items[$data] = $return;
    } else {
      isset($this->relations[$data]['level']) ? $this->filter_relation($data, $return) : $this->filter_relations($data, $return);
    }
  }

  public function with_array($data) {
    foreach ($data as $key => $value) {
      $return = $this->relation($key)?->with($value)?->array();
      if ($this->singular) {
        isset($this->relations[$key]['level']) ? ($this->items[$key] = $return[0]) : ($this->items[$key] = $return);
      } else {
        isset($this->relations[$key]['level']) ? $this->filter_relation($key, $return) : $this->filter_relations($key, $return);
      }
    }
    return $this;
  }

  public function relation($data) {
    $where = [];
    $x = ($this->singular ?
            [$this->items[$this->relations[$data]['name']]] :
            array_column($this->items, $this->relations[$data]['name']));
    if (count($x) > 0) {
      $where[$this->relations[$data]['key']] = $x;
      return call_user_func_array(
                      [$this->relations[$data]['callback'], 'where'],
                      [$where]
              )->get();
    }
    return null;
  }

  public function filter_relation($model, $data) {
    for (
            $i = 0;
            count($this->items) > $i;
            ++$i
    ) {
      $x = array_filter($data, function ($item) use ($i, $model) {
        return $item[$this->relations[$model]['key']] == $this->items[$i][$this->relations[$model]['name']];
      });
      if ($x != []) {
        $this->items[$i][$model] = $x[0];
      }
    }
  }

  public function filter_relations($model, $data) {
    for (
            $i = 0;
            count($this->items) > $i;
            ++$i
    ) {
      $this->items[$i][$model] = array_values(array_filter($data, function ($item) use ($i, $model) {
                return $item[$this->relations[$model]['key']] == $this->items[$i][$this->relations[$model]['name']];
              }));
    }
  }
}
