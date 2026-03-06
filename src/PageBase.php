<?php

namespace The;

abstract class PageBase
{
    public $page = [];
    protected static $pageCache = null;

    public static $title = null;
    public static $meta_description = null;
    public static $canonical_url = null;
    public function __construct()
    {
        if (class_exists('\App\Model\Page')) {
            if (self::$pageCache === null) {
                $pageData = \App\Model\Page::where(['slug REGEXP' => "^", 'enable' => 1])
                    ->get()
                    ->with(['page_block'])
                    ->array();

                $pageBlocks = $pageData['page_block'] ?? [];

                self::$title = $pageData['title'] ?? self::$title;
                
                self::$meta_description = $pageData['meta_description'] ?? self::$meta_description;

                self::$canonical_url = $pageData['slug'] ?? self::$canonical_url;

                $blocks = array_filter($pageBlocks, fn($x) => ($x['enable'] ?? 0) == 1);
                self::$pageCache = array_merge(['page_block' => $blocks], ...array_map(fn($x) => [$x["component"] => $x], $blocks));
            }
            $this->page = self::$pageCache;
        }
    }
}
