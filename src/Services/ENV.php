<?php

namespace De\Idrinth\WalledSecrets\Services;

use PDO;

class ENV
{
    /**
     * @var string[]
     */
    private array $env;

    public function __construct(PDO $database)
    {
        foreach ($database->query('SELECT * FROM configurations') as $row) {
            $this->env[$row['key']] = $row['value'];
        }
    }
    public function getString(string $name): string
    {
        if (isset($this->env[$name])) {
            return $this->env[$name];
        }
        return $_ENV[$name] ?? '';
    }
    public function getStringList(string $name): array
    {
        $data = explode(',', $this->getString($name));
        return array_filter(array_map('trim', $data));
    }
    public function getInt(string $name): int
    {
        if (isset($this->env[$name])) {
            return intval($this->env[$name], 10);
        }
        return intval($_ENV[$name] ?? '', 10);
    }
    public function getBool(string $name): bool
    {
        if (isset($this->env[$name])) {
            return $this->env[$name] === 'true';
        }
        return ($_ENV[$name] ?? '') === 'true';
    }
}
