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
                $blocks = \App\Model\Page::where(['slug REGEXP' => "^","enable" => 1])->get()->with(['page_block'])->array()["page_block"];
                self::$pageCache = array_merge(['page_block' => $blocks], ...array_map(fn($x) => [$x["component"] => $x], $blocks));
            }
            $this->page = self::$pageCache;
        }
    }
}