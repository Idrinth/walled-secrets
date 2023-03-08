<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\Cookie;
use De\Idrinth\WalledSecrets\Services\ENV;
use PDO;

class Login
{
    private PDO $database;
    private ENV $env;

    public function __construct(PDO $database, ENV $env)
    {
        $this->database = $database;
        $this->env = $env;
    }
    public function get(string $id, string $password): string
    {
        $stmt = $this->database->prepare('SELECT aid,since,mail FROM accounts WHERE id=:id AND identifier=:password');
        $stmt->execute([':id' => $id, ':password' => $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            try {
                $master = $this->aes->decrypt($this->blowfish->decrypt($_SESSION['password']));
                KeyLoader::private($id, $master);
            } catch (Exception $ex) {
                header('Location: /', true, 303);
                return '';
            }
            if (strtotime($user['since']) + $this->env->getInt('SYSTEM_SESSION_DURATION') > time()) {
                $_SESSION['id'] = $user['aid'];
                $_SESSION['uuid'] = $id;
                Cookie::set(
                    $this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE'),
                    sha1($this->env->getString('SYSTEM_SALT') . $user['mail']),
                    $this->env->getInt('SYSTEM_QUICK_LOGIN_DURATION')
                );
            }
        }
        header ('Location: /', true, 303);
        return '';
    }
}
