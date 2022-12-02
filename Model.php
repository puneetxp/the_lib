<?php

namespace App\TheDep;

abstract class Model {

    //items
    protected $items = [];
    protected $singular = false;

    //__construct
    public function __construct(
            protected $id = '',
            protected $key = 'id',
            protected $col = ['*']
    ) {
        
    }

    //DB_connect
    public function connect() {
        return DB::inti($this->table, $this->fillable, $this->id, $this->key, $this->col);
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
        return (new static('*'))->get_items();
    }

    public function get_items() {
        $this->items = $this->connect()->all()->many();
        return $this;
    }

    //where *
    public static function where($id, $key = "id", $col = ['*']) {
        return (new static(id: $id, key: $key, col: $col))->_where();
    }

    public function _where() {
        $this->items = $this->connect()->where($this->col)->many();
        return $this;
    }

    //single
    public static function find($id, $key = 'id', $col = ['*']) {
        return (new static(id: $id, key: $key, col: $col))->set_singular()->get_item();
    }

    public function get_item() {
        $this->items = $this->connect()->find();
        if ($this->items == null) {
            return null;
        } else {
            return $this;
        }
    }

    //insert
    public function insert($data = '') {
        $this->items = $this->connect()->create($data);
        return $this;
    }

    public static function create($data = '') {
        return (new static())->insert($data);
    }

    //update
    public static function update($id, $key = 'id', $data = '') {
        return (new static(id: $id, key: $key, col: ["*"]))->_update($data);
    }

    public static function upsert($data) {
        return (new static())->_upsert($data);
    }

    public function _update($data) {
        $this->items = $this->connect()->update($data);
        return $this;
    }

    public function _upsert($data) {
        $this->items = $this->connect()->upsert($data);
        return $this;
    }

    //delete
    public static function delete($id, $key = 'id') {
        return (new static($id, $key))->_where()?->_destroy();
    }

    public function _destroy() {
        $array_col = array_column($this->items, $this->key);
        $this->items = $this->connect()->delete($array_col, $this->key);
        return $this;
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
                    $x = array_merge($this->relation(array_keys($item)[0])?->wfast(array_values($item)[0])->array(), $x);
                } else {
                    $x[$item] = $this->relation($item)->array();
                }
            }
        } else {
            $x[$data] = $this->relation($data)->array();
        }
        //        if ($this->singular) {
        //            $this->items = [...$this->items, ...$x];
        //            return $this;
        //        }
        $x[$this->name] = $this->items;
        $this->items = $x;
        foreach ($single as $key) {
            $this->items[$key] = $this->items[$key][0];
        }
        return $this;
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
        $return = $this->relation($data)->array();
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
        return call_user_func_array(
                [$this->relations[$data]['callback'], 'where'],
                [($this->singular ? $this->items[$this->relations[$data]['name']] : array_column($this->items, $this->relations[$data]['name'])), $this->relations[$data]['key']]
        );
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

    //don't work usage of more memory that it need.
    //load with relationship with some filltering
    //   public function with($data) {
    //      if (is_array($data)) {
    //         foreach ($data as $item) {
    //            if (is_array($item)) {
    //               $this->nested_relations($item);
    //            } else {
    //               $this->no_nested_realtions($item);
    //            }
    //         }
    //      } else {
    //         $this->no_nested_realtions($data);
    //      }
    //      return $this;
    //   }
    //
    //   public function no_nested_realtions($data) {
    //      $this->items[$data] = isset($this->relations[$data]['level']) ? $this->relation($data)->array() : $this->relation($data)->array();
    ////      $this->filter_relations($data, $this->relation($data)->array());
    //   }
    //
    //   public function nested_relations($data) {
    //      foreach ($data as $key => $value) {
    //         $this->singular ?
    //                         $this->items[$data] = (isset($this->relations[$data]['level']) ? $this->relation($data)?->array() : $this->relation($data)?->array() ) : $this->filter_relations($key, $this->relation($key)?->with($value)?->array());
    //      }
    //   }
    //
    //   public function relation($data) {
    //      return call_user_func_array(
    //              [$this->relations[$data]['callback'], 'where'],
    //              [($this->singular ?
    //                  $this->items[$this->relations[$data]['name']] :
    //                  array_column($this->items, $this->relations[$data]['name'])), $this->relations[$data]['key']]
    //      );
    //   }
    //
    //   public function filter_relations($model, $data) {
    //      for (
    //              $i = 0;
    //              count($this->items) > $i;
    //              ++$i
    //      ) {
    //         isset($this->relations[$model]['level']) ?
    //                         $this->items[$i][$model] = array_filter($data, function ($item) use ($i, $model) {
    //                            return $item[$this->relations[$model]['key']] == $this->items[$i][$this->relations[$model]['name']];
    //                         })[0] :
    //                         $this->items[$i][$model] = array_filter($data, function ($item) use ($i, $model) {
    //                    return $item[$this->relations[$model]['key']] == $this->items[$i][$this->relations[$model]['name']];
    //                 });
    //      }
    //   }
}
