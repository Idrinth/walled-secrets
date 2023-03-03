<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use phpseclib3\Crypt\RSA;

class Logins
{
    private PDO $database;
    private Twig $twig;
    private AES $aes;
    private Blowfish $blowfish;
    private ENV $env;

    public function __construct(PDO $database, Twig $twig, AES $aes, Blowfish $blowfish, ENV $env)
    {
        $this->database = $database;
        $this->twig = $twig;
        $this->aes = $aes;
        $this->blowfish = $blowfish;
        $this->env = $env;
        $this->aes->setKeyLength(256);
        $this->aes->setKey($this->env->getString('PASSWORD_KEY'));
        $this->aes->setIV($this->env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($this->env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($this->env->getString('PASSWORD_BLOWFISH_IV'));
    }

    public function get(string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header ('Location: /', true, 303);
            return '';            
        }
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE id=:id AND `account`=:account');
        $stmt->execute([':id' => $id, ':account' => $_SESSION['id']]);
        $login = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$login) {
            header ('Location: /', true, 303);
            return '';
        }
        set_time_limit(0);
        $master = $this->aes->decrypt($this->blowfish->decrypt($_SESSION['password']));
        $private = RSA::loadPrivateKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $_SESSION['uuid'] . '/private'), $master);;
        $login['login'] = $private->decrypt($login['login']);
        $login['pass'] = $private->decrypt($login['pass']);
        $login['domain'] = $private->decrypt($login['domain']);
        $login['iv'] = $private->decrypt($login['iv']);
        $login['key'] = $private->decrypt($login['key']);
        $shared = new AES('ctr');
        $shared->setIV($login['iv']);
        $shared->setKeyLength(256);
        $shared->setKey($login['key']);
        $login['note'] = $shared->decrypt($login['note']);
        return $this->twig->render('login', [
            'title' => $login['public'],
            'login' => $login,
        ]);
    }
}
