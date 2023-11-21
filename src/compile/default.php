<?php

function defaultsetup($data = ['id', 'created_at', 'updated_at']) {
    $table = [];
    if (in_array('id', $data)) {
        $table[] = ['name' => 'id', 'mysql_data' => 'int', 'datatype' => 'number', 'fillable' => "false", 'sql_attribute' => 'UNSIGNED PRIMARY KEY AUTO_INCREMENT'];
    }
    if (in_array('created_at', $data)) {
        $table[] = ['name' => 'created_at', 'mysql_data' => 'timestamp', 'datatype' => 'Date', 'fillable' => "false", 'sql_attribute' => 'DEFAULT CURRENT_TIMESTAMP NOT NULL'];
    }
    if (in_array('updated_at', $data)) {
        $table[] = ['name' => 'updated_at', 'mysql_data' => 'timestamp', 'datatype' => 'Date', 'fillable' => "false", 'sql_attribute' => 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL'];
    }
    return $table;
}

function scanfullfolder($dir) {
    $x = [];
    $d = scandir($dir);
    for ($i = 2; $i < count($d); $i++) {
        if (is_file("$dir/$d[$i]")) {
            array_push($x, "$dir/$d[$i]");
        } else {
            $x = [...$x, ...scanfullfolder("$dir/$d[$i]")];
        }
    }
    return $x;
}

function default_att($item) {
    $default_sql_attribute = " NOT NULL";
    if (isset($item['sql_attribute'])) {
        if (str_contains($item['sql_attribute'], 'NULL')) {
            
        } else {
            $item['sql_attribute'] = $item['sql_attribute'] . $default_sql_attribute;
        }
    } else {
        $item['sql_attribute'] = $default_sql_attribute;
    }
    return $item;
}

$route_default = '/api/';
$output_path = '../php/App/';

function table_set($item, $x) {
    isset($item['default']) ? $table = defaultsetup($item['default']) : $table = defaultsetup();
    isset($item['enable']) ?
                    $table[] = ['name' => 'enable', 'mysql_data' => 'TINYINT(1)', 'datatype' => 'number', 'sql_attribute' => 'DEFAULT ' . $item['enable'] . ' NOT NULL '] : '';
    isset($item['data']) ? ' ' : $item['data'] = [];
    $table = array_merge($table, array_map('default_att', $item['data']));
    $relationfor = [];
    if (isset($item['relation'])) {
        foreach ($item['relation'] as $relation) {
            $r = [];
            is_array($relation) ? $r['name'] = $relation['name'] : $r['name'] = $relation;
            $r = array_search($r['name'], array_column($x, 'name'));
            $rx = ['table' => $x[$r]['table'], 'name' => isset($relation['alias']) ? $relation['alias'] : $x[$r]['name'] . '_id', 'key' => 'id'];
            $table[] = [
                'name' => isset($relation['alias']) ? $relation['alias'] : $x[$r]['name'] . '_id',
                'mysql_data' => 'int',
                'datatype' => 'number',
                'sql_attribute' => 'UNSIGNED' . (is_array($relation) ? $relation['sql_attribute'] : ' NOT NULL'),
                'relations' => [$x[$r]['name'] => $rx]
            ];
            $relationfor[$x[$r]['name']] = $rx;
        }
    }
    return ['name' => $item['name'], 'table' => $item['table'], 'data' => $table, 'relations' => $relationfor, 'crud' => $item['crud']];
}

function php_wrapper($data) {
    return '<?php ' . $data . '?> ';
}

function php_w($data) {
    return '<?php ' . $data;
}

function class_wrapper($name, $data) {
    return ' class ' . $name . ' {' . $data . '} ';
}

function unique_multidim_array($array, $key) {
    $temp_array = array();
    $i = 0;
    $key_array = array();

    foreach ($array as $val) {
        if (!in_array($val[$key], $key_array)) {
            $key_array[$i] = $val[$key];
            $temp_array[$i] = $val;
        }
        $i++;
    }
    return $temp_array;
}

function fopen_dir($link) {
    $filename = $link;
    $dirname = dirname($filename);
    if (!is_dir($dirname)) {
        mkdir($dirname, 0755, true);
    }
    return fopen($filename, 'w');
}

function createfile($dir, $string) {
    fwrite(fopen_dir($dir), $string);
}
