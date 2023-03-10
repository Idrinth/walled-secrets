<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\MasterPassword;
use De\Idrinth\WalledSecrets\Services\Twig;
use Exception;
use PDO;

class Master
{
    private PDO $database;
    private ENV $env;
    private MasterPassword $master;
    private Twig $twig;
    private Audit $audit;

    public function __construct(Audit $audit, Twig $twig, PDO $database, ENV $env, MasterPassword $master)
    {
        $this->audit = $audit;
        $this->twig = $twig;
        $this->database = $database;
        $this->env = $env;
        $this->master = $master;
    }

    public function get(User $user): string
    {
        if (!isset($_COOKIE[$this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE')])) {
            header('Location: /', true, 303);
            return '';
        }
        return $this->twig->render('master', ['title' => 'Confirm Login', 'disableRefresh' => true]);
    }
    public function post(User $user, array $post): string
    {
        $cookie = $this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE');
        if (!isset($_COOKIE[$cookie])) {
            header('Location: /', true, 303);
            return '';
        }
        if ($_COOKIE[$cookie] !== sha1($this->env->getString('SYSTEM_SALT') . $post['email'])) {
            header('Location: /master', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT id,aid FROM accounts WHERE mail=:mail');
        $stmt->execute([':mail' => $post['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            header('Location: /master', true, 303);
            return '';
        }
        try {
            KeyLoader::private($user['id'], $post['password']);
        } catch (Exception $ex) {
            header('Location: /master', true, 303);
            return '';
        }
        $_SESSION['id'] = $user['aid'];
        $_SESSION['uuid'] = $user['id'];
        $this->master->set($post['password']);
        $this->audit->log('signin', 'create', $_SESSION['id'], null, $user['id']);
        header('Location: /', true, 303);
        return '';
    }
}
