<?php

namespace App\TheDep;

/**
 * Routing Module of TPHP 
 *
 * @author puneetxp
 */
class Route {

    public function __construct(
            private $_trim = '/\^$',
            private $_uri = '',
            private $_method = '',
            private $_match_route = [],
            private $_roles = []
    ) {
        $this->active_route_set();
    }

    public function active_route_set() {
        $this->_uri = trim(isset($_REQUEST['uri']) ? filter_var($_REQUEST['uri'], FILTER_SANITIZE_URL) : '/', $this->_trim);
        $this->_method = isset($_SERVER['REQUEST_METHOD']) ? filter_var($_SERVER['REQUEST_METHOD'], FILTER_SANITIZE_URL) : 'GET';
        $this->_realUri = explode('/', $this->_uri);
        $this->_roles = Sessions::roles();
    }

    public function get($uri, $call, $roles = ['*']) {
        return $this->method_action('GET', $uri, $call, $roles);
    }

    public function post($uri, $call, $roles = ['*']) {
        return $this->method_action('POST', $uri, $call, $roles);
    }

    public function patch($uri, $call, $roles = ['*']) {
        return $this->method_action('PATCH', $uri, $call, $roles);
    }

    public function put($uri, $call, $roles = ['*']) {
        return $this->method_action('PUT', $uri, $call, $roles);
    }

    public function delete($uri, $call, $roles = ['*']) {
        return $this->method_action('DELETE', $uri, $call, $roles);
    }

    public function method_action($method, $uri, $call, $roles = ['*']) {
        if ($this->_method == "$method" && preg_match("#^" . trim($uri, $this->_trim) . "$#", $this->_uri)) {
            $this->_match_route = ["uri" => $uri, "roles" => $roles, "call" => $call];
            return $this->check_permission()?->run();
        }
        return $this;
    }

    public function crud($crud, $name, $permission, $controller) {
        if (in_array('r', $crud)) {
            $x = $this->get($name, [$controller, 'index'], $permission['read'])
                    ?->get($name . '/.+', [$controller, 'show'], $permission['read']);
        }
        if (in_array('c', $crud)) {
            $x = $x?->post($name, [$controller, 'store'], $permission['write']);
        }
        if (in_array('u', $crud)) {
            $x = $x?->patch($name . '/.+', [$controller, 'update'], $permission['update'])
                    ?->put($name, [$controller, 'upsert'], $permission['update']);
        }
        if (in_array('d', $crud)) {
            $x = $x?->delete($name . '/.+', [$controller, 'delete'], $permission['delete']);
        }
        return $x;
    }

    public function check_permission() {
        if ($this->_match_route['roles'] === ['*'] || array_intersect($this->_match_route['roles'], $this->_roles)) {
            return $this;
        } else {
            echo Response::not_authorised();
            return null;
        }
    }

    public function run() {
        $fakeUri = explode('/', $this->_match_route['uri']);
        $attributes = [];
        foreach ($fakeUri as $key => $value) {
            if ($value == '.+') {
                $attributes[] = $this->_realUri[$key];
            }
        }
        echo call_user_func_array($this->_match_route['call'], $attributes);
        return null;
    }

    public function not_found(){
        echo Response::not_found("Not Found");
    }
}
