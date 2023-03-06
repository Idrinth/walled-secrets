<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use phpseclib3\Crypt\Random;

class Knowns
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

    public function post(array $post, string $id): string
    {
        if (!isset($post['note'])) {
            header ('Location: /knowns/'.$id, true, 303);
            return '';
        }
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header ('Location: /', true, 303);
            return '';            
        }
        $stmt = $this->database->prepare('SELECT knowns.*
FROM knowns
WHERE knowns.id=:id AND knowns.`owner`=:account');
        $stmt->execute([':id' => $id, ':account' => $_SESSION['id']]);
        $known = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$known) {
            header ('Location: /', true, 303);
            return '';
        }
        $public = KeyLoader::public($_SESSION['uuid']);
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('UPDATE knowns SET note=:note, iv=:iv, `key`=:key WHERE id=:id AND `owner`=:owner')
            ->execute([
                ':owner' => $_SESSION['id'],
                ':id' => $id,
                ':key' => $public->encrypt($key),
                ':iv' => $public->encrypt($iv),
                ':note' => $shared->encrypt($post['note']),
            ]);
        header ('Location: /knowns/'.$id, true, 303);
        return '';
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
        $stmt = $this->database->prepare('SELECT knowns.*,accounts.display
FROM knowns
INNER JOIN accounts ON accounts.aid = knowns.target
WHERE knowns.id=:id AND knowns.`owner`=:account');
        $stmt->execute([':id' => $id, ':account' => $_SESSION['id']]);
        $known = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$known) {
            header ('Location: /', true, 303);
            return '';
        }
        set_time_limit(0);
        $master = $this->aes->decrypt($this->blowfish->decrypt($_SESSION['password']));
        $private = KeyLoader::private($_SESSION['uuid'], $master);
        if ($known['note']) {
            $known['iv'] = $private->decrypt($known['iv']);
            $known['key'] = $private->decrypt($known['key']);
            $shared = new AES('ctr');
            $shared->setIV($known['iv']);
            $shared->setKeyLength(256);
            $shared->setKey($known['key']);
            $known['note'] = $shared->decrypt($known['note']);
        }
        return $this->twig->render('known', [
            'known' => $known,
            'title' => $known['display']
        ]);
    }
}