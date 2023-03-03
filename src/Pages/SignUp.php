<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use phpseclib3\Crypt\Random;
use phpseclib3\Crypt\RSA;
use Ramsey\Uuid\Uuid;

class SignUp
{
    private PDO $database;
    private Blowfish $blowfish;
    private AES $aes;
    private ENV $env;

    public function __construct(PDO $database, Blowfish $blowfish, AES $aes,ENV $env)
    {
        $this->database = $database;
        $this->blowfish = $blowfish;
        $this->aes = $aes;
        $this->env = $env;
        $this->aes->setKeyLength(256);
        $this->aes->setKey($this->env->getString('PASSWORD_KEY'));
        $this->aes->setIV($this->env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($this->env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($this->env->getString('PASSWORD_BLOWFISH_IV'));
    }

    public function get(string $id, string $pass): string
    {
        $stmt = $this->database->prepare('SELECT aid FROM invites WHERE id=:id AND secret=:secret AND ISNULL(invitee)');
        $stmt->execute([':id' => $id, ':secret' => $pass]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            header ('Location: /', true, 303);
            return '';
        }
        return $this->twig->render('register', ['title' => 'Registration']);
    }
    private function addKnown(int $user, int $known, string $uuid, string $comment): void
    {
        $public = RSA::loadPublicKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $uuid . '/public'));
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('INSERT INTO knowns (owner,target,comment,iv,key) VALUES (:owner,:target,:comment,:iv,:key)')
            ->execute([
                ':comment' => $shared->encrypt($comment),
                ':iv' => $public->encrypt($iv),
                ':key' => $public->encrypt($key),
                ':owner' => $user,
                ':target' => $known,
            ]);
    }
    public function post(array $post, string $id, string $pass): string
    {
        $stmt = $this->database->prepare('SELECT aid,inviter FROM invites WHERE id=:id AND mail=:mail AND secret=:secret AND ISNULL(invitee)');
        $stmt->execute([':id' => $id, ':secret' => $pass, ':mail' => $post['email']]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$id) {
            header ('Location: /', true, 303);
            return '';
        }
        $uuid = Uuid::uuid1();
        $private = RSA::createKey($this->env->getInt('SYSTEM_KEY_BYTES'));
        $private->withPassword($post['master']);
        mkdir(__DIR__ . '/../../keys/' . $uuid);
        file_put_contents(__DIR__ . '/../../keys/' . $uuid . '/private', $private->toString('openssl'));
        file_put_contents(__DIR__ . '/../../keys/' . $uuid . '/public', $private->getPublicKey()->toString('openssl'));
        $this->database
            ->prepare('INSERT INTO accounts (id,display,mail) VALUES (:id,:display,:mail)')
            ->execute([':display' => $post['display'], ':id' => $uuid, ':mail' => $post['email']]);
        $new = $this->database->lastInsertId();
        $_SESSION['id'] = $new;
        $_SESSION['uuid'] = $uuid;
        $_SESSION['password'] = $this->blowfish->encrypt($this->aes->encrypt($post['password']));
        $this->database
            ->prepare('UPDATE invitations SET invitee=:invitee WHERE aid=:invite')
            ->execute([':id' => $invite['aid'], ':invitee' => $new]);
        $this->addKnown($new, $invite['inviter'], $uuid, 'Was invited by them.');
        $stmt = $this->database->prepare('SELECT id FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $invite['inviter']]);
        $this->addKnown($invite['inviter'], $new, $stmt->fetchColumn(), 'Invited them.');
        header ('Location: /', true, 303);
        return '';
    }
}
