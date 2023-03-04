<?php

namespace De\Idrinth\WalledSecrets;

use ReflectionClass;
use ReflectionMethod;
use UnexpectedValueException;

class DependencyInjector
{
    private array $singletons=[];

    public function register(object $singleton): void
    {
        $rf = new ReflectionClass($singleton);
        $this->singletons[$rf->getName()] = $singleton;
        foreach ($rf->getInterfaces() as $interface) {
            $this->singletons[$interface->getName()] = $singleton;
        }
        while ($rf = $rf->getParentClass()) {
            $this->singletons[$rf->getName()] = $singleton;
            foreach ($rf->getInterfaces() as $interface) {
                $this->singletons[$interface->getName()] = $singleton;
            }
        }
    }
    public function init(ReflectionClass $class): object
    {
        error_log('Building ' . $class->getName());
        if (!isset($this->singletons[$class->getName()])) {
            $args = [];
            $constructor = $class->getConstructor();
            if ($constructor instanceof ReflectionMethod) {
                foreach ($constructor->getParameters() as $parameter) {
                    if ($parameter->isOptional()) {
                        break;
                    }
                    $args[] = $this->init($parameter->getClass());
                }
            }
            $handler = $class->getName();
            $this->register(new $handler(...$args));
        }
        if (!isset($this->singletons[$class->getName()])) {
            throw new UnexpectedValueException("Couldn'find {$class->getName()} in " . implode(',', array_keys($this->singletons)));
        }
        return $this->singletons[$class->getName()];
    }
}
