<?php

namespace The;

abstract class PageBase
{
    public $page = [];
    protected static $pageCache = null;

    public function __construct()
    {
        if (class_exists('\App\Model\Page')) {
            if (self::$pageCache === null) {
                $pageData = \App\Model\Page::where(['slug REGEXP' => "^", 'enable' => 1])
                    ->get()
                    ->with(['page_block'])
                    ->array();

                $pageBlocks = $pageData['page_block'] ?? [];

                $blocks = array_filter($pageBlocks, function ($x) {
                    return ($x['enable'] ?? 0) == 1;
                });
                self::$pageCache = array_merge(['page_block' => $blocks], ...array_map(function($x) {
                    return [$x["component"] => $x];
                }, $blocks));
            }
            $this->page = self::$pageCache;
        }
    }
}
