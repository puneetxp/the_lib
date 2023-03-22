<?php

namespace The;

/**
 * Routing Module of TPHP 
 *
 * @author puneetxp
 */
class Route
{
    private $_trim = '/\^$';
    private $_uri = '';
    private $_method = "";
    private $_match_route = [];
    private $_realUri;
    private $_roles = [];
    public function __construct(
        $routes
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
        $this->active_route_set();
        $this->run_route($routes);
    }

    public function active_route_set()
    {
        $this->_uri = trim(isset($_SERVER['REQUEST_URI']) ? filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL) : '/', $this->_trim);
        $this->_method = isset($_SERVER['REQUEST_METHOD']) ? filter_var($_SERVER['REQUEST_METHOD'], FILTER_SANITIZE_URL) : 'GET';
        $this->_realUri = explode('/', $this->_uri);
        $this->_roles = Sessions::roles();
        if (isset($_POST['_method'])) {
            $this->_method = $_POST['_method'];
        }
    }

    public function run_route($routes)
    {
        foreach ($routes[$this->_method] as $value) {
            if (preg_match("#^" . trim($value["path"], $this->_trim) . "$#", $this->_uri)) {
                $this->_match_route = $value;
                if(count($this->_roles) == 0){
                    return $this->run();
                }
                return $this->check_permission()?->run();
            }
        }
        Response::not_found("Not Found");
    }

    public function check_permission()
    {
        if (isset($_SESSION['user_id'])) {
            if (isset($this->_match_route['roles'])) {
                if (array_intersect($this->_match_route['roles'], $this->_roles)) {
                } else {
                    echo Response::not_authorised();
                    return null;
                }
            }
            return $this;
        }
        echo Response::NotLogin();
        return null;
    }

    public function run()
    {
        $fakeUri = explode('/', $this->_match_route['path']);
        $attributes = [];
        foreach ($fakeUri as $key => $value) {
            if ($value == '.+') {
                $attributes[] = $this->_realUri[$key];
            }
        }
        echo call_user_func_array($this->_match_route['handler'], $attributes);
        return null;
    }

    public function not_found()
    {
        echo Response::not_found("Not Found");
    }
}
