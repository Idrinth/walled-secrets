<?php

namespace De\Idrinth\WalledSecrets\Services;

class Cookie
{
    public static function getParams(int $livetime): array
    {
        if ($livetime) {
            return [
                'expires' => time() + $livetime,
                'path' => '/',
                'domain' => $_ENV['SYSTEM_HOSTNAME'],
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ];
        }
        return [
            'path' => '/',
            'domain' => $_ENV['SYSTEM_HOSTNAME'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }
    public static function set(string $name, string $value, int $livetime): void
    {
        setcookie($name, $value, self::getParams($livetime));
    }
    public static function setIfExists($name, $livetime): void
    {
        if (isset($_COOKIE[$name])) {
            self::set($name, $_COOKIE[$name], $livetime);
        }
    }
}
