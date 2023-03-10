<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\Cookie;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\PasswordGenerator;
use De\Idrinth\WalledSecrets\Services\Twig;
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
    private Twig $twig;
    private Audit $audit;

    public function __construct(Audit $audit, Twig $twig, PDO $database, Blowfish $blowfish, AES $aes, ENV $env)
    {
        $this->audit = $audit;
        $this->twig = $twig;
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

    public function get(User $user, string $id, string $pass): string
    {
        $stmt = $this->database->prepare(
            'SELECT aid FROM invites WHERE id=:id AND secret=:secret AND ISNULL(invitee)'
        );
        $stmt->execute([':id' => $id, ':secret' => $pass]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            header('Location: /', true, 303);
            return '';
        }
        return $this->twig->render(
            'register',
            [
                'title' => 'Registration',
                'disableRefresh' => true,
                'minPasswordLength' => $this->env->getInt('SYSTEM_MIN_PASSWORD_LENGTH')
            ]
        );
    }
    private function addKnown(int $user, int $known, string $uuid, string $comment): void
    {
        $public = KeyLoader::public($uuid);
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare(
                'INSERT INTO knowns (`owner`,target,note,iv,`key`,id) VALUES (:owner,:target,:comment,:iv,:key,:id)'
            )
            ->execute(
                [
                ':comment' => $shared->encrypt($comment),
                ':iv' => $public->encrypt($iv),
                ':key' => $public->encrypt($key),
                ':owner' => $user,
                ':target' => $known,
                ':id' => Uuid::uuid1()->toString(),
                ]
            );
    }
    public function post(User $user, array $post, string $id, string $pass): string
    {
        if (!isset($post['name']) || !isset($post['email'])) {
            header('Location: /register/' . $id . '/' . $pass, true, 303);
            return '';
        }
        $length = $this->env->getInt('SYSTEM_MIN_PASSWORD_LENGTH');
        if (!isset($post['password']) || strlen($post['password']) < $length) {
            header('Location: /register/' . $id . '/' . $pass, true, 303);
            return '';
        }
        if (!isset($post['password2']) || strlen($post['password2']) < $length) {
            header('Location: /register/' . $id . '/' . $pass, true, 303);
            return '';
        }
        if ($post['password2'] !== $post['password']) {
            header('Location: /register/' . $id . '/' . $pass, true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT aid,inviter FROM invites
WHERE id=:id AND mail=:mail AND secret=:secret AND ISNULL(invitee)');
        $stmt->execute([':id' => $id, ':secret' => $pass, ':mail' => $post['email']]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invite) {
            header('Location: /', true, 303);
            return '';
        }
        $uuid = Uuid::uuid1();
        $private = RSA::createKey($this->env->getInt('SYSTEM_KEY_BYTES'));
        $private->withPassword($post['master']);
        mkdir(__DIR__ . '/../../keys/' . $uuid);
        file_put_contents(__DIR__ . '/../../keys/' . $uuid . '/private', $private->toString('OpenSSH'));
        file_put_contents(__DIR__ . '/../../keys/' . $uuid . '/public', $private->getPublicKey()->toString('OpenSSH'));
        $this->database
            ->prepare('INSERT INTO accounts (id,display,mail,apikey) VALUES (:id,:display,:mail,:apikey)')
            ->execute(
                [
                    ':display' => $post['name'],
                    ':id' => $uuid,
                    ':mail' => $post['email'],
                    ':apikey' => PasswordGenerator::make(),
                ]
            );
        $new = $this->database->lastInsertId();
        $_SESSION['id'] = $new;
        $_SESSION['uuid'] = $uuid;
        $this->audit->log('account', 'create', $new, null, $uuid);
        $_SESSION['password'] = $this->blowfish->encrypt($this->aes->encrypt($post['password']));
        $this->database
            ->prepare('UPDATE invitations SET invitee=:invitee WHERE aid=:invite')
            ->execute([':invite' => $invite['aid'], ':invitee' => $new]);
        $this->addKnown($new, $invite['inviter'], $uuid, 'Was invited by them.');
        $stmt = $this->database->prepare('SELECT id FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $invite['inviter']]);
        $inviter = $stmt->fetchColumn();
        $this->addKnown($invite['inviter'], $new, $inviter, 'Invited them.');
        $this->audit->log('known', 'create', $new, null, $inviter);
        $this->audit->log('known', 'create', $invite['inviter'], null, $uuid);
        header('Location: /', true, 303);
        Cookie::set(
            $this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE'),
            sha1($this->env->getString('SYSTEM_SALT') . $post['mail']),
            $this->env->getInt('SYSTEM_QUICK_LOGIN_DURATION')
        );
        $folder = Uuid::uuid1()->toString();
        $this->database
            ->prepare('INSERT INTO folders (id,`owner`,`name`,`default`) VALUES (:id,:owner,"unsorted",1)')
            ->execute([':id' => $folder, ':owner' => $new]);
        $this->audit->log('folder', 'create', $new, null, $folder);
        return '';
    }
}
