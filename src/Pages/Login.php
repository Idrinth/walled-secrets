<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\Cookie;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\MasterPassword;
use PDO;
use Swoole\MySQL\Exception;

class Login
{
    private PDO $database;
    private ENV $env;
    private MasterPassword $master;
    private Audit $audit;

    public function __construct(Audit $audit, PDO $database, ENV $env, MasterPassword $master)
    {
        $this->audit = $audit;
        $this->database = $database;
        $this->env = $env;
        $this->master = $master;
    }
    public function get(User $user, string $id, string $password): string
    {
        if ($this->master->has()) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT aid,since,mail FROM accounts WHERE id=:id AND identifier=:password');
        $stmt->execute([':id' => $id, ':password' => $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            try {
                KeyLoader::private($id, $this->master->get());
            } catch (Exception $ex) {
                header('Location: /', true, 303);
                return '';
            }
            if (strtotime($user['since']) + $this->env->getInt('SYSTEM_SESSION_DURATION') > time()) {
                $this->audit->log('signin', 'create', $user['aid'], null, $id);
                $_SESSION['id'] = $user['aid'];
                $_SESSION['uuid'] = $id;
                Cookie::set(
                    $this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE'),
                    sha1($this->env->getString('SYSTEM_SALT') . $user['mail']),
                    $this->env->getInt('SYSTEM_QUICK_LOGIN_DURATION')
                );
            }
        }
        header('Location: /', true, 303);
        return '';
    }
}
