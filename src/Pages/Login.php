<?php

namespace De\Idrinth\WalledSecrets\Pages;

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
        $stmt = $this->database->prepare('SELECT aid,since FROM accounts WHERE id=:id AND identifier=:password');
        $stmt->execute([':id' => $id, ':password' => $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if (strtotime($user['since']) + $this->env->getInt('SYSTEM_SESSION_DURATION') > time()) {
                $_SESSION['id'] = $user['aid'];
                $_SESSION['uuid'] = $id;
            }
        }
        header ('Location: /', true, 303);
        return '';
    }
}
