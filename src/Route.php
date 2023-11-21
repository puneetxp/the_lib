<?php

namespace The;

/**
 * Routing Module of TPHP 
 *
 * @author puneetxp
 */
class Route {

    private $_trim = '/\^$';
    private $_uri = '';
    private $_method = "";
    private $_match_route = [];
    private $_realUri;
    private $_n = 0;
    private $_login;

    public function __construct(
            private $routes,
            private $_url = "REQUEST_URI"
    ) {
        ob_start();
        session_start([
            'cookie_secure' => secure,
            "cookie_path" => '/',
            'cookie_domain' => sslhost,
            'cookie_httponly' => httponly,
            'cookie_samesite' => samesite
        ]);
        date_default_timezone_set("Asia/Kolkata");
        // if (json_decode(file_get_contents('php://input'), true)) {
        //     $_POST = json_decode(file_get_contents('php://input'), true);
        // }
        if (isset($_POST['_action'])) {
            $actions = $_POST["_action"];
            $return = [];
            foreach ($actions as $action) {
                ob_start();
                $action = json_decode($action, true);
                $this->active_route_set($action['url'], $action['method'], $action['data'] ?? null);
                echo $this->run_route();
                $return[$action['url']] = json_decode(ob_get_contents());
                ob_end_clean();
            }
            echo Response::json($return);
        } else {
            $this->active_route_set();
            echo $this->run_route();
        }
    }

    public function active_route_set($url = null, $method = null, $data = null) {
        $this->_uri = trim(parse_url($url ?? $_SERVER[$this->_url], PHP_URL_PATH), $this->_trim);
        $this->_method = filter_var($method ?? $_SERVER['REQUEST_METHOD'], FILTER_SANITIZE_URL) ?? 'GET';
        $this->_realUri = explode('/', $this->_uri);
        $this->_n = count($this->_realUri);
        $this->_login = Sessions::get_current_user();
        if (isset($_POST['_method'])) {
            $this->_method = strtoupper($_POST['_method']);
            unset($_POST['_method']);
        }
    }

    public function run_route() {
        foreach ($this->routes[$this->_method] as $value) {
            if ($this->_n === $value["n"] && preg_match("#^" . trim($value["path"], $this->_trim) . "$#", $this->_uri)) {
                $this->_match_route = $value;
                if (!(isset($this->_match_route["islogin"]) && $this->_match_route["islogin"])) {
                    return $this->run();
                } else {
                    if (isset($this->_match_route["guard"])) {
                        foreach ($this->_match_route["guard"] as $guard) {
                            call_user_func($guard);
                        }
                    }
                    if ($this->_match_route["islogin"] && $this->_login) {
                        if (isset($this->_match_route['roles'])) {
                            return $this->check_permission()?->run();
                        } else {
                            return $this->run();
                        }
                    }
                    return Response::NotLogin();
                }
            }
        }
        return Response::not_found("Not Found");
    }

    public function check_permission() {
        if (array_intersect($this->_match_route['roles'], Sessions::roles())) {
            return $this;
        }
        return Response::not_authorised();
    }

    public function run() {
        $fakeUri = explode('/', $this->_match_route['path']);
        $attributes = [];
        foreach ($fakeUri as $key => $value) {
            if ($value == '.+') {
                $attributes[] = $this->_realUri[$key];
            }
        }
        return call_user_func_array($this->_match_route['handler'], $attributes);
    }

    public function not_found() {
        return Response::not_found("Not Found");
    }
}
