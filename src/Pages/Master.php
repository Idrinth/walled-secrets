<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Twig;
use Exception;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;

class Master
{
    private PDO $database;
    private ENV $env;
    private AES $aes;
    private Blowfish $blowfish;
    private Twig $twig;

    public function __construct(Twig $twig, PDO $database, ENV $env, AES $aes, Blowfish $blowfish)
    {
        $this->twig = $twig;
        $this->database = $database;
        $this->env = $env;
        $this->aes = $aes;
        $this->blowfish = $blowfish;
        $this->aes->setKeyLength(256);
        $this->aes->setKey($this->env->getString('PASSWORD_KEY'));
        $this->aes->setIV($this->env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($this->env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($this->env->getString('PASSWORD_BLOWFISH_IV'));
    }

    public function get(): string
    {
        if (!isset($_COOKIE[$this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE')])) {
            header('Location: /', true, 303);
            return '';
        }
        return $this->twig->render('master', ['title' => 'Confirm Login', 'disableRefresh' => true]);
    }
    public function post($post): string
    {
        if (!isset($_COOKIE[$this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE')])) {
            header('Location: /', true, 303);
            return '';
        }
        if ($_COOKIE[$this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE')] !== sha1($this->env->getString('SYSTEM_SALT') . $post['email'])) {
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
        $_SESSION['password'] = $this->blowfish->encrypt($this->aes->encrypt($post['password']));
        header('Location: /', true, 303);
        return '';
    }
}
