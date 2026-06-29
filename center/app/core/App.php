<?php
class App {
    protected $controller = 'DashboardController';
    protected $method = 'index';
    protected $params = [];

    public function __construct() {
        session_start();
        $url = $this->parseUrl();

        if (isset($url[0]) && file_exists('../app/controllers/' . ucfirst($url[0]) . 'Controller.php')) {
            $this->controller = ucfirst($url[0]) . 'Controller';
            unset($url[0]);
        } elseif (!isset($_SESSION['user_id']) && ($url[0] ?? '') != 'auth') {
             $this->controller = 'AuthController';
             $this->method = 'login';
        }

        require_once '../app/controllers/' . $this->controller . '.php';
        $this->controller = new $this->controller;

        if (isset($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }

        $this->params = $url ? array_values($url) : [];

        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    public function parseUrl() {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }

        $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $script_name = dirname($_SERVER['SCRIPT_NAME']);

        if (strpos($request_uri, $script_name) === 0) {
            $url = substr($request_uri, strlen($script_name));
        } else {
            $url = $request_uri;
        }

        $url = trim($url, '/');

        if (!empty($url)) {
            return explode('/', filter_var($url, FILTER_SANITIZE_URL));
        }

        return [];
    }
}