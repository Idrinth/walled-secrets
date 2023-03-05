<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\Mailer;
use De\Idrinth\WalledSecrets\Twig;
use Exception;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use Ramsey\Uuid\Uuid;

class Home
{
    private Twig $twig;
    private PDO $database;
    private Mailer $mailer;
    private AES $aes;
    private Blowfish $blowfish;
    private ENV $env;

    public function __construct(Twig $twig, PDO $database, Mailer $mailer, AES $aes, Blowfish $blowfish, ENV $env)
    {
        $this->env = $env;
        $this->blowfish = $blowfish;
        $this->twig = $twig;
        $this->database = $database;
        $this->mailer = $mailer;
        $this->aes = $aes;
        $this->aes->setKeyLength(256);
        $this->aes->setKey($this->env->getString('PASSWORD_KEY'));
        $this->aes->setIV($this->env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($this->env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($this->env->getString('PASSWORD_BLOWFISH_IV'));
    }
    public function get(): string
    {
        if (isset($_SESSION['id'])) {
            $stmt = $this->database->prepare('SELECT * FROM accounts WHERE aid=:id');
            $stmt->execute([':id' => $_SESSION['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM folders WHERE (`owner`=:id AND `type`="Account") OR (`type`="Organisation" AND `owner` IN (SELECT organisation FROM memberships WHERE `role`<>"Proposed" AND `account`=:id))');
            $stmt->execute([':id' => $_SESSION['id']]);
            $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM organisations INNER JOIN memberships ON memberships.organisation=organisations.aid WHERE account=:id');
            $stmt->execute([':id' => $_SESSION['id']]);
            $organisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT iv,`key`,note,display,knowns.id,accounts.id as uid FROM accounts INNER JOIN knowns ON knowns.target=accounts.aid WHERE knowns.owner=:id');
            $stmt->execute([':id' => $_SESSION['id']]);
            $knowns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->twig->render('home-user', [
                'title' => 'Home',
                'user' => $user,
                'folders' => $folders,
                'organisations' => $organisations,
                'knowns' => $knowns,
            ]);
        }
        if (isset($_COOKIE[$this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE')])) {
            header('Location: /master', true, 303);
            return '';
        }
        return $this->twig->render('home-anon', [
            'title' => 'Login',
            'disableRefresh' => true
        ]);
    }
    function makePass(): string
    {
        $chars = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        $out = '';
        while (strlen($out) < 255) {
            $out .= $chars[rand(0, 61)];
        }
        return $out;
    }
    public function post(array $post): string
    {
        if (isset($_SESSION['id'])) {
            if (isset($post['regenerate'])) {
                $stmt = $this->database
                    ->prepare('UPDATE `accounts` SET `apikey`=:ak WHERE `aid`=:id');
                $stmt->bindValue(':id', $_SESSION['id']);
                $stmt->bindValue(':ak', $this->makePass());
                $stmt->debugDumpParams();
                #    ->execute([':ak' => $this->makePass(), ':id ' => $_SESSION['id']]);
            } elseif (isset($post['folder'])) {
                $this->database
                    ->prepare('INSERT INTO folders (`name`,`owner`,id) VALUES (:name, :owner,:id)')
                    ->execute([':name' => $post['folder'], ':owner ' => $_SESSION['id'], ':id' => Uuid::uuid1()->toString()]);
            } elseif (isset($post['organisation'])) {
                $this->database
                    ->prepare('INSERT INTO organisations (`name`,id) VALUES (:name,:uuid)')
                    ->execute([':name' => $post['organisation'], ':uuid' => Uuid::uuid1()->toString()]);
                $this->database
                    ->prepare('INSERT INTO memberships (organisation,account,role) VALUES (:organisation,:account,"Owner")')
                    ->execute([':organisation' => $this->database->lastInsertId(), ':account' => $_SESSION['id']]);
            } elseif (isset($post['email']) && isset($post['name'])) {
                $stmt = $this->database->prepare('SELECT aid FROM invites WHERE mail=:mail AND inviter=:id');
                $stmt->execute([':id' => $_SESSION['id'], ':mail' => $post['email']]);
                if ($stmt->fetchColumn() !== false) {
                    header('Location: /', true, 303);
                    return '';
                }
                $id = $this->makePass();
                $uuid = Uuid::uuid1()->toString();
                $stmt = $this->database->prepare('SELECT display FROM accounts WHERE aid=:id');
                $stmt->execute([':id' => $_SESSION['id']]);
                $sender = $stmt->fetchColumn();
                $this->mailer->send(
                    0,
                    'invite',
                    [
                        'hostname' => $this->env->getString('SYSTEM_HOSTNAME'),
                        'password' => $id,
                        'uuid' => $uuid,
                        'name' => $post['name'],
                        'sender' => $sender,
                    ],
                    'Invite to ' . $this->env->getString('SYSTEM_HOSTNAME'),
                    $post['email'],
                    $post['name']
                 );
                $this->database
                    ->prepare('INSERT INTO invites (id,mail,secret,inviter) VALUES (:id,:mail,:secret,:inviter)')
                    ->execute([':id' => $uuid, ':mail' => $post['email'], ':secret' => $id, ':inviter' => $_SESSION['id']]);
            }
            header('Location: /', true, 303);
            return '';
        }
        if (!isset($post['email'])) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT id, display, since, aid FROM accounts WHERE mail=:mail');
        $stmt->execute([':mail' => $post['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if (!isset($user['since']) || strtotime($user['since']) < time() - $this->env->getInt('SYSTEM_SESSION_DURATION')) {
                try {
                    KeyLoader::private($_SESSION['uuid'], $post['password']);
                } catch (Exception $ex) {
                    header('Location: /', true, 303);
                    return '';
                }
                $id = $this->makeOneTimePass();
                $_SESSION['password'] = $this->blowfish->encrypt($this->aes->encrypt($post['password']));
                $this->mailer->send(
                    $user['aid'],
                    'login',
                    ['hostname' => $this->env->getString('SYSTEM_HOSTNAME'), 'password' => $id, 'uuid' => $user['id'], 'name' => $user['display']],
                    'Login Request at ' . $this->env->getString('SYSTEM_HOSTNAME'),
                    $post['email'],
                    $user['display']
                 );
                $this->database
                    ->prepare('UPDATE accounts SET since=NOW(),identifier=:id WHERE aid=:aid')
                    ->execute([':id' => $id, ':aid' => $user['aid']]);
                
            }
        }
        return $this->twig->render('home-mailed', [
            'title' => 'Login',
            'mail' => $post['email'],
            'minutes' => ceil($this->env->getInt('SYSTEM_SESSION_DURATION')/60)
        ]);
    }
}
