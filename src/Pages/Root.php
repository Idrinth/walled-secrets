<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\Mailer;
use De\Idrinth\WalledSecrets\Services\MasterPassword;
use De\Idrinth\WalledSecrets\Services\PasswordGenerator;
use De\Idrinth\WalledSecrets\Services\Twig;
use Exception;
use PDO;

class Root
{
    private Twig $twig;
    private PDO $database;
    private Mailer $mailer;
    private ENV $env;
    private Audit $audit;
    private MasterPassword $master;

    public function __construct(
        Audit $audit,
        Twig $twig,
        PDO $database,
        Mailer $mailer,
        ENV $env,
        MasterPassword $master
    ) {
        $this->master = $master;
        $this->audit = $audit;
        $this->env = $env;
        $this->twig = $twig;
        $this->database = $database;
        $this->mailer = $mailer;
    }
    public function get(User $user): string
    {
        if ($user->aid() !== 0) {
            header('Location: /home', true, 303);
            return '';
        }
        if (isset($_COOKIE[$this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE')])) {
            header('Location: /master', true, 303);
            return '';
        }
        return $this->twig->render(
            'root',
            [
                'title' => 'Login',
                'disableRefresh' => true
            ]
        );
    }
    public function post(User $user, array $post): string
    {
        if (!isset($post['email']) || !isset($post['password'])) {
            error_log('email or password not set.');
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT id, display, since, aid FROM accounts WHERE mail=:mail');
        $stmt->execute([':mail' => $post['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            error_log("{$post['email']} not found among users.");
            header('Location: /', true, 303);
            return '';
        }
        try {
            KeyLoader::private($user['id'], $post['password']);
        } catch (Exception $ex) {
            header('Location: /', true, 303);
            error_log("{$user['id']} tried to login with wrong password. $ex");
            return '';
        }
        if (
            !isset($user['since'])
            || strtotime($user['since']) < time() - $this->env->getInt('SYSTEM_SESSION_DURATION')
        ) {
            $id = PasswordGenerator::make();
            $this->master->set($post['password']);
            $this->mailer->send(
                'login',
                ['password' => $id, 'uuid' => $user['id'], 'name' => $user['display']],
                'Login Request at ' . $this->env->getString('SYSTEM_HOSTNAME'),
                $post['email'],
                $user['display']
            );
            $this->audit->log('login', 'create', $user['aid'], null, $user['id']);
            $this->database
                ->prepare('UPDATE accounts SET since=NOW(),identifier=:id WHERE aid=:aid')
                ->execute([':id' => $id, ':aid' => $user['aid']]);
        }
        header('Location: /mailed', true, 303);
        return '';
    }
}
