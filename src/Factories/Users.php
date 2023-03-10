<?php

namespace De\Idrinth\WalledSecrets\Factories;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Cookie;
use PDO;
use UnexpectedValueException;

class Users
{
    public static function get(string $class, PDO $database): User
    {
        if (strpos($class, 'De\\Idrinth\\WalledSecrets\\API\\') === 0) {
            return self::forAPI($database);
        }
        if (strpos($class, 'De\\Idrinth\\WalledSecrets\\Pages\\') === 0) {
            return self::forPage($database);
        }
        throw new UnexpectedValueException("$class is of unknown type");
    }
    private static function forAPI(PDO $database): User
    {
        $post = Superglobals::post();
        if (!isset($post['email']) || !isset($post['apikey'])) {
            return new Model(0, $database);
        }
        $stmt = $database->prepare('SELECT aid FROM accounts WHERE mail=:mail AND apikey=:apikey');
        $stmt->execute([':mail' => $post['email'], ':apikey' => $post['apikey']]);
        return new Model(intval($stmt->fetchColumn(), 10), $database);
    }
    private static function forPage(PDO $database): User
    {
        session_start();
        Cookie::setIfExists(session_name(), intval($_ENV['SYSTEM_SESSION_DURATION'], 10));
        Cookie::setIfExists($_ENV['SYSTEM_QUICK_LOGIN_COOKIE'], intval($_ENV['SYSTEM_QUICK_LOGIN_DURATION'], 10));
        return new Model(Superglobals::session()['id'] ?? 0, $database);
    }
}
