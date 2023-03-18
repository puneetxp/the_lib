<?php

namespace The\compile;

class RouteCompile
{

    public $route = [];

    public function __construct(array $routes)
    {
        $this->compiles($routes);
    }

    public function compiles($routes)
    {
        foreach ($routes as $route) {
            $this->compile($route);
        }
    }

    public function compile($route, $prefix = [])
    {
        isset($route['islogin']) ? $prefix['islogin'] = $route['islogin'] : '';
        isset($route['path']) ? (isset($prefix['path']) ? ($prefix['path'] = $prefix['path'] . $route['path']) : ($prefix['path'] =  $route['path'])) : '';
        isset($route['roles']) ? (isset($prefix['roles']) ? ($prefix['roles'] = [...$prefix['roles'], ...$route['roles']]) : ($prefix['roles'] = [...$route['roles']])) : '';
        isset($route['guard']) ? (isset($prefix['guard']) ? ($prefix['guard'] = [...$prefix['guard'], ...$route['guard']]) : ($prefix['guard'] = [...$route['guard']])) : '';
        if (isset($route["handler"])) {
            $x = $prefix;
            $x["handler"] = $route["handler"];
            isset($route['method']) ? $method = $route['method']  : $method = "GET";
            unset($x['method']);
            $this->addroute($x, $method);
        }
        if (isset($route["group"])) {
            $this->compile_group($route["group"], $prefix);
        }
        if (isset($route["child"])) {
            $this->compile_child($route["child"], $prefix);
        }
        if (isset($route["crud"])) {
            $this->crud_compile($route["crud"], $prefix);
        }
    }

    public function compile_group($group, $prefix = [])
    {
        foreach ($group as $key => $value) {
            $value["method"] = $key;
            $this->compile($value, $prefix);
        }
    }
    public function compile_child(array $routes, $prefix)
    {
        foreach ($routes as $value) {
            $this->compile($value, $prefix);
        }
    }
    public function crud_compile($curd, $prefix)
    {
        if (in_array("a", $curd["crud"])) {
            $this->addroute(["handler" => [$curd["class"], "all"], ...$prefix], "GET");
        }
        if (in_array("r",  $curd["crud"])) {
            $x = $prefix;
            $x["path"] .= "/.+";
            $this->addroute(["handler" => [$curd["class"], "show"], ...$x], "GET");
        }

        if (in_array("c",  $curd["crud"])) {
            $this->addroute(["handler" => [$curd["class"], "store"], ...$prefix], "POST");
        }

        if (in_array("u",  $curd["crud"])) {
            $x = $prefix;
            $x["path"] .= "/.+";
            $this->addroute(["handler" => [$curd["class"], "update"], ...$x], "PATCH");
        }

        if (in_array("p",  $curd["crud"])) {
            $this->addroute(["handler" => [$curd["class"], "upsert"], ...$prefix], "PUT");
        }
        if (in_array("d",  $curd["crud"])) {
            $x = $prefix;
            $x["path"] .= "/.+";
            $this->addroute(["handler" => [$curd["class"], "delete"], ...$x], "DELETE");
        }
    }
    public function addroute($route, $method)
    {
        if (!isset($this->route[$method])) {
            $this->route[$method] = [];
        }
        $route['handler'][0] = $route['handler'][0] . "::class";
        $this->route[$method] = [...$this->route[$method], $route];
    }
    public function addroutes($y)
    {
        foreach ($y as $value) {
            $value['method'] ? $method = $value['method'] : $method = "GET";
            unset($value['method']);
            $this->addroute($value, $method);
        }
    }
}
