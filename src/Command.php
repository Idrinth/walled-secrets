<?php

namespace De\Idrinth\WalledSecrets;

use De\Idrinth\WalledSecrets\Services\DependencyInjector;
use Dotenv\Dotenv;
use ReflectionClass;
use Throwable;

class Command
{
    private array $routes = [];
    private DependencyInjector $di;
    public function __construct()
    {
        Dotenv::createImmutable(dirname(__DIR__))->load();
        date_default_timezone_set('UTC');
        $this->di = new DependencyInjector();
    }

    public function register(object $singleton): self
    {
        $this->di->register($singleton);
        return $this;
    }

    public function add(string $command, string $class): self
    {
        $this->routes[$command] = $class;
        return $this;
    }
    public function run(string ...$arguments): void
    {
        if (count($arguments) < 2) {
        }
        array_shift($arguments);
        $route = array_shift($arguments);
        if (!isset($this->routes[$route])) {
            echo "$route is unknown.\n";
            die(1);
        }
        $obj = $this->di->get($this->routes[$route]);
        try {
            echo $obj->run(...$arguments);
        } catch (Throwable $t) {
            error_log($t->getFile() . ':' . $t->getLine() . ': ' . $t->getMessage());
            error_log($t->getTraceAsString());
        }
    }
}
