<?php

namespace The;

abstract class Model {

    //items
    protected $items = [];
    protected $singular = false;
    protected $col = ["*"];
    protected DB $db;
    protected $relations = [];
    protected array $with = [];
    protected $table;
    protected $name;
    protected $model;
    public $page = [];

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

    public function paginate(int $pageNumber = 1, int $pageItems = 25) {
        $pageNumber = $_GET['page'] ?? $pageNumber;
        $pageItems = $_GET['pageItems'] ?? $pageItems;
        $this->page['result'] = $this->db->count();
        if ($this->page['result']) {
            $this->page['pageNumber'] = $pageNumber;
            $this->page['pageItems'] = $pageItems;
            $this->page['totalpages'] = $this->page['result'] / $this->page['pageItems'];
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

    protected function pages(int $number = 5) {
        $pages = [];
        $int = intdiv($number, 2);
        if($this->page['totalpages'] <= $number ){
            for($i =1; $i >= $this->page['totalpages']; ++$i) {
                $pages[$i] = http_build_query(["page"=>$i])."&".$this->page['get'];
            }
        } elseif ($this->page['pageNumber'] > $int && $this->page['pageNumber'] >= $this->page['totalpages'] - $int ) {
            $i = $this->page['pageNumber'] - $int;
            while (count($pages) < $number)  {
                $pages[$i] = http_build_query(["page"=>$i])."&".$this->page['get'];
                ++$i;
            }
        } else {
            if($this->page['pageNumber'] < $int ){
                $i = 1;
            }else{
                $i = $this->page['totalpages'] - $number;
            }
            while (count($pages) < $number)  {
                $pages[$i] = http_build_query(["page"=>$i])."&".$this->page['get'];
                ++$i;
            }
        }
        return $pages;
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

    public static function create($data = []) {
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

    //call realtionship
    //Better for spa and fastest way
    public function with($data, bool $first = true) {
        $x = [];
        if (is_array($data)) {
            $first && $this->with = $data;
            foreach ($data as $item) {
                if (is_array($item)) {
                    foreach ($item as $key => $value) {
                        $x = array_merge($this->isnull($this->relation($key)?->with($value), false), $x);
                    }
                } else {
                    $x[$item] = $this->isnull($this->relation($item));
                }
            }
        } else {
            $first && $this->with = [$data];
            $x[$data] = $this->isnull($this->relation($data));
        }
        $this->singular ? $x[$this->name] = [$this->items] : $x[$this->name] = $this->items;
        $this->items = $x;
        return $this;
    }

    public function isnull($x) {
        if ($x == null) {
            return [];
        }
        return $x?->array();
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

    //bindintosomepattern
    public function sort() {
        if (count($this->with)) {
            $this->items = ($this->sortout($this->with, $this->items[$this->name]));
        }
        $this->singular && $this->items = $this->items[0];
        return $this;
    }

    private function sortout($relation, $data, $base = null) {
        foreach ($relation as $item) {
            $data = is_array($item) ? $this->filter_relations($item, $data, $base) : $this->filter_relation($item, $data, $base);
        }
        return $data;
    }

    public function filter_relation($model, $data, $base = null) {
        return array_map(function ($item) use ($model, $base) {
            $y = array_values(
                    array_filter($base ? $base[$model] : $this->items[$model],
                            fn($model_item) =>
                            $model_item[$this->relations[$model]['key']] == $item[$this->relations[$model]['name']]
                    )
            );
            return [...$item, $model => isset($this->relations[$model]['level']) ? $y[0] : $y];
        }, $data);
    }

    public function filter_relations($model, $data, $base = null) {
        foreach (array_keys($data) as $index) {
            foreach ($model as $key => $item) {
                $data = $this->filter_relation($key, $data);
                $data[$index][$key] = (new $this->relations[$key]['callback']())->sortout($item, $data[$index][$key], $base ? $base : $this->items);
            }
        }
        return $data;
    }
}
