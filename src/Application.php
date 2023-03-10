<?php

namespace De\Idrinth\WalledSecrets;

use De\Idrinth\WalledSecrets\Services\Access;
use De\Idrinth\WalledSecrets\Services\Cookie;
use De\Idrinth\WalledSecrets\Services\DependencyInjector;
use De\Idrinth\WalledSecrets\Services\SessionHandler;
use Dotenv\Dotenv;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use ReflectionClass;
use Throwable;

use function FastRoute\simpleDispatcher;

class Application
{
    private $routes = [];
    private DependencyInjector $di;
    public function __construct()
    {
        Dotenv::createImmutable(dirname(__DIR__))->load();
        date_default_timezone_set('UTC');
        Cookie::setIfExists($_ENV['SYSTEM_QUICK_LOGIN_COOKIE'], intval($_ENV['SYSTEM_QUICK_LOGIN_DURATION'], 10));
        $handler = new SessionHandler();
        session_set_save_handler($handler);
        session_set_cookie_params(Cookie::getParams(0));
        session_start();
        Cookie::setIfExists(session_name(), intval($_ENV['SYSTEM_SESSION_DURATION'], 10));
        $_SESSION['_last'] = time();
        $this->di = new DependencyInjector();
    }

    public function register(object $singleton): self
    {
        $this->di->register($singleton);
        return $this;
    }

    public function get(string $path, string $class): self
    {
        return $this->add('GET', $path, $class);
    }
    public function post(string $path, string $class): self
    {
        return $this->add('POST', $path, $class);
    }
    public function put(string $path, string $class): self
    {
        return $this->add('PUT', $path, $class);
    }
    public function delete(string $path, string $class): self
    {
        return $this->add('DELETE', $path, $class);
    }
    private function add(string $method, string $path, string $class): self
    {
        $this->routes[$path] = $this->routes[$path] ?? [];
        $this->routes[$path][$method] = $class;
        return $this;
    }
    private function result(string $data): void
    {
        header('Content-Length: ' . strlen($data));
        die($data);
    }
    public function run(): void
    {
        $access = $this->di->init(new ReflectionClass(Access::class));
        if (!$access->may($_SERVER['REMOTE_ADDR'])) {
            header('Content-Type: text/plain', true, 403);
            die('IP not allowed.');
        }
        $dispatcher = simpleDispatcher(
            function (RouteCollector $r) {
                foreach ($this->routes as $path => $data) {
                    foreach ($data as $method => $func) {
                        $r->addRoute($method, $path, $func);
                    }
                }
            }
        );
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $routeInfo = $dispatcher->dispatch($httpMethod, rawurldecode($uri));
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                header('', true, 404);
                echo "404 NOT FOUND";
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                header('', true, 405);
                echo "405 METHOD NOT ALLOWED";
                break;
            case Dispatcher::FOUND:
                $vars = $routeInfo[2];
                $obj = $this->di->init(new ReflectionClass($routeInfo[1]));
                try {
                    switch ($httpMethod) {
                        case 'GET':
                            $this->result($obj->get(...array_values($vars)));
                        case 'POST':
                            $this->result($obj->post($this->filteredPost(), ...array_values($vars)));
                        case 'PUT':
                            $this->result($obj->put($this->filteredPost(), ...array_values($vars)));
                        case 'DELETE':
                            $this->result($obj->delete(...array_values($vars)));
                    }
                } catch (Throwable $t) {
                    header('', true, 500);
                    error_log($t->getFile() . ':' . $t->getLine() . ': ' . $t->getMessage());
                    error_log($t->getTraceAsString());
                }
                break;
        }
    }
    private function filteredPost(): array
    {
        $out = [];
        foreach ($_POST as $key => $value) {
            if (!empty($value)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
