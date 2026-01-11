<?php

namespace The\DB;

class sqlBuilder {
    public static function buildSelectList($alias, $cols = [], $prefix = null) {
        if (!$cols || count($cols) === 0) {
            return ["$alias.*"];
        }
        return array_map(function($col) use ($alias, $prefix) {
            $as = $prefix ? "{$prefix}__${col}" : $col;
            return "$alias.`$col` AS $as";
        }, $cols);
    }

    public static function buildJoinQuery($baseTable, $baseAlias, $baseCols, $joins, $where = []) {
        $selectParts = self::buildSelectList($baseAlias, $baseCols);
        $joinSQL = "";

        foreach ($joins as $join) {
            $relSelect = self::buildSelectList(
                $join['alias'],
                $join['cols'] ?? [],
                $join['prefix'] ?? null
            );
            $selectParts = array_merge($selectParts, $relSelect);
            $joinSQL .= " LEFT JOIN {$join['table']} {$join['alias']} ON {$baseAlias}.`{$join['localKey']}` = {$join['alias']}.`{$join['foreignKey']}`";
        }

        $whereClauses = [];
        $placeholders = [];

        if ($where) {
            foreach ($where as $col => $val) {
                if (is_array($val)) {
                    $qs = implode(',', array_fill(0, count($val), '?'));
                    $whereClauses[] = "$baseAlias.`$col` IN ($qs)";
                    $placeholders = array_merge($placeholders, $val);
                } else {
                    $whereClauses[] = "$baseAlias.`$col` = ?";
                    $placeholders[] = $val;
                }
            }
        }

        $whereSQL = count($whereClauses) > 0 ? " WHERE " . implode(" AND ", $whereClauses) : "";

        $sql = "SELECT " . implode(", ", $selectParts) . " FROM $baseTable $baseAlias" . $joinSQL . $whereSQL;

        return [
            'sql' => $sql,
            'placeholders' => $placeholders,
            'selectParts' => $selectParts
        ];
    }
}
