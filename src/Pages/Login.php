<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\Cookie;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\MasterPassword;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;
use Swoole\MySQL\Exception;

class Login
{
    private PDO $database;
    private ENV $env;
    private MasterPassword $master;
    private Audit $audit;
    private Twig $twig;

    public function __construct(Twig $twig, Audit $audit, PDO $database, ENV $env, MasterPassword $master)
    {
        $this->twig = $twig;
        $this->audit = $audit;
        $this->database = $database;
        $this->env = $env;
        $this->master = $master;
    }
    public function get(User $user, string $id, string $password): string
    {
        if (!$this->master->has()) {
            return $this->twig->render('login-error', [
                'title' => 'Login Failed',
                'error' => 'Master Password could not be retrieved from session. Please try again.'
            ]);
        }
        $stmt = $this->database->prepare('SELECT aid,since,mail FROM accounts WHERE id=:id AND identifier=:password');
        $stmt->execute([':id' => $id, ':password' => $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return $this->twig->render('login-error', [
                'title' => 'Login Failed',
                'error' => 'Couldn\'t find a user for the given link.'
            ]);
        }
        try {
            KeyLoader::private($id, $this->master->get());
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            return $this->twig->render('login-error', [
                'title' => 'Login Failed',
                'error' => 'Master Password could not be verified, please try again.'
            ]);
        }
        if (strtotime($user['since']) + $this->env->getInt('SYSTEM_SESSION_DURATION') < time()) {
            return $this->twig->render('login-error', [
                'title' => 'Login Failed',
                'error' => 'The given Link is too old, please request a new one.'
            ]);
        }
        $this->audit->log('signin', 'create', $user['aid'], null, $id);
        $_SESSION['id'] = $user['aid'];
        $_SESSION['uuid'] = $id;
        Cookie::set(
            $this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE'),
            sha1($this->env->getString('SYSTEM_SALT') . $user['mail']),
            $this->env->getInt('SYSTEM_QUICK_LOGIN_DURATION')
        );
        header('Location: /', true, 303);
        return '';
    }
}
