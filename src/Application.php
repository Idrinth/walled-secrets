<?php

namespace De\Idrinth\WalledSecrets;

use De\Idrinth\WalledSecrets\Factories\Superglobals;
use De\Idrinth\WalledSecrets\Factories\Users;
use De\Idrinth\WalledSecrets\Services\Access;
use De\Idrinth\WalledSecrets\Services\Cookie;
use De\Idrinth\WalledSecrets\Services\DependencyInjector;
use De\Idrinth\WalledSecrets\Services\SessionHandler;
use Dotenv\Dotenv;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
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
        session_set_save_handler(new SessionHandler());
        session_set_cookie_params(Cookie::getParams(0));
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
    private function result(string $data, int $status = -1): string
    {
        if ($status >= 100) {
            header('', true, $status);
        }
        header('Content-Length: ' . strlen($data), true);
        return $data;
    }
    private function dispatch(array $routeInfo): string
    {
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return $this->result('404 NOT FOUND', 404);
            case Dispatcher::METHOD_NOT_ALLOWED:
                return $this->result('405 METHOD NOT ALLOWED', 405);
            case Dispatcher::FOUND:
                $vars = $routeInfo[2];
                $user = Users::get($routeInfo[1], $this->di->get('PDO'));
                if (!$this->di->get(Access::class)->may($_SERVER['REMOTE_ADDR'], $user)) {
                    return $this->result('IP is blacklisted.', 403);
                }
                $obj = $this->di->get($routeInfo[1]);
                try {
                    switch ($_SERVER['REQUEST_METHOD']) {
                        case 'GET':
                            return $this->result($obj->get($user, ...array_values($vars)));
                        case 'POST':
                            return $this->result($obj->post($user, Superglobals::post(), ...array_values($vars)));
                        case 'PUT':
                            return $this->result($obj->put($user, Superglobals::post(), ...array_values($vars)));
                        case 'DELETE':
                            return $this->result($obj->delete($user, ...array_values($vars)));
                        default:
                            return $this->result('405 METHOD NOT ALLOWED', 405);
                    }
                } catch (Throwable $t) {
                    error_log($t->getFile() . ':' . $t->getLine() . ': ' . $t->getMessage());
                    error_log($t->getTraceAsString());
                    return $this->result('', 500);
                }
            default:
                error_log('Dispatcher worked in an undocumented way.');
                return $this->result('', 500);
        }
    }
    public function run(): void
    {
        $access = $this->di->get(Access::class);
        $dispatcher = simpleDispatcher(
            function (RouteCollector $r) {
                foreach ($this->routes as $path => $data) {
                    foreach ($data as $method => $func) {
                        $r->addRoute($method, $path, $func);
                    }
                }
            }
        );
        $uri = $_SERVER['REQUEST_URI'];
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        echo $this->dispatch($dispatcher->dispatch($_SERVER['REQUEST_METHOD'], rawurldecode($uri)));
    }
}
