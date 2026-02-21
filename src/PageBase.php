<?php

namespace The;

abstract class PageBase{
    public $page = [];
    public function __construct() {
        if(class_exists('\App\Model\Page')) {
            $blocks = \App\Model\Page::where(['slug REGEXP' => "^"])->get()->with(['page_block'])->array()["page_block"];
            $this->page = array_merge(['page_block' => $blocks], ...array_map(fn($x) => [$x["component"]=> $x ] , $blocks));
        }
    }
}