<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use phpseclib3\Crypt\Random;
use phpseclib3\Crypt\RSA;
use Ramsey\Uuid\Uuid;

class Folder
{
    private PDO $database;
    private Twig $twig;
    private Blowfish $blowfish;
    private AES $aes;
    private ENV $env;

    public function __construct(PDO $database, Twig $twig, Blowfish $blowfish, AES $aes, ENV $env)
    {
        $this->database = $database;
        $this->env = $env;
        $this->twig = $twig;
        $this->blowfish = $blowfish;
        $this->aes = $aes;
        $this->aes->setKeyLength(256);
        $this->aes->setKey($this->env->getString('PASSWORD_KEY'));
        $this->aes->setIV($this->env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($this->env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($this->env->getString('PASSWORD_BLOWFISH_IV'));
    }

    private function updateNote(int $owner, string $uuid, string $id, string $name, string $content)
    {
        $public = RSA::loadPublicKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $uuid . '/public'));
        $this->database
            ->prepare('INSERT IGNORE INTO notes (content,iv,name,`key`,id,`owner`) VALUES ("","","","",:id,:owner)')
            ->execute([':id' => $id, ':owner' => $owner]);
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('UPDATE notes SET content=:content, name=:name, iv=:iv, `key`=:key WHERE id=:id AND `owner`=:owner')
            ->execute([
                ':owner' => $_SESSION['id'],
                ':id' => $id,
                ':key' => $public->encrypt($key),
                ':iv' => $public->encrypt($iv),
                ':name' => $shared->encrypt($name),
                ':content' => $shared->encrypt($content),
            ]);
    }

    private function updateLogin(int $owner, string $uuid, string $id, string $login, string $password, string $domain)
    {
        $public = RSA::loadPublicKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $uuid . '/public'));
        $this->database
            ->prepare('INSERT IGNORE INTO logins (domain,pass,login,id,`account`) VALUES ("","","",:id,:owner)')
            ->execute([':id' => $id, ':owner' => $owner]);
        $this->database
            ->prepare('UPDATE logins SET pass=:pass, domain=:domain, login=:login WHERE id=:id AND `account`=:owner')
            ->execute([
                ':owner' => $_SESSION['id'],
                ':id' => $id,
                ':pass' => $public->encrypt($password),
                ':domain' => $public->encrypt($domain),
                ':login' => $public->encrypt($login),
            ]);
    }
    
    public function post(array $post, string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT *, "Owner" as `role` FROM folders WHERE `owner`=:user AND id=:id AND `type`="Account"');
        $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$folder) {
            $stmt = $this->database->prepare('SELECT folders.*,memberships.`role`
FROM folders
INNER JOIN memberships ON memberships.organisation=folders.owner
WHERE memberships.account=:user AND folders.id=:id AND folders.`type`="Organisation" AND memberships.`role` <> "Proposed"');
            $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$folder) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header ('Location: /', true, 303);
            return '';            
        }
        if (!isset($post['id'])) {
            $post['id'] = Uuid::uuid1();
        }
        if (isset($post['domain']) && isset($post['password']) && isset($post['user'])) {
            if (isset($folder['role'])) {
                $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
                $stmt->execute([':org' => $folder['owner']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->updateLogin($row['aid'], $row['id'], $post['id'], $post['user'], $post['password'], $post['domain']);
                }
            } else {
                $this->updateLogin($_SESSION['id'], $_SESSION['uuid'], $post['id'], $post['user'], $post['password'], $post['domain']);
            }
        } elseif (isset($post['content']) && isset($post['name'])) {
            if (isset($folder['role'])) {
                $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
                $stmt->execute([':org' => $folder['owner']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->updateNote($row['aid'], $row['id'], $post['id'], $post['name'], $post['content']);
                }
            } else {
                $this->updateNote($_SESSION['id'], $_SESSION['uuid'], $post['id'], $post['name'], $post['content']);
            }
        }
        header ('Location: /folder/' . $id, true, 303);
        return '';
    }
    public function get(string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT *, "Owner" as `role` FROM folders WHERE `owner`=:user AND id=:id AND `type`="Account"');
        $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$folder) {
            $stmt = $this->database->prepare('SELECT folders.*,memberships.`role`
FROM folders
INNER JOIN memberships ON memberships.organisation=folders.owner
WHERE memberships.account=:user AND folders.id=:id AND folders.`type`="Organisation" AND memberships.role <> "Proposed"');
            $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$folder) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header ('Location: /', true, 303);
            return '';            
        }
        $master = $this->aes->decrypt($this->blowfish->decrypt($_SESSION['password']));
        $private = RSA::loadPrivateKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $_SESSION['uuid'] . '/private'), $master);
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE account=:user AND folder=:id');
        $stmt->execute([':id' => $folder['aid'], ':user' => $_SESSION['id']]);$logins = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['login'] = $private->decrypt($row['login']);
            $row['pass'] = $private->decrypt($row['pass']);
            $row['domain'] = $private->decrypt($row['domain']);
            $logins[] = $row;
        }
        $stmt = $this->database->prepare('SELECT * FROM notes WHERE account=:user AND folder=:id');
        $stmt->execute([':id' => $folder['aid'], ':user' => $_SESSION['id']]);
        $notes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['iv'] = $private->decrypt($row['login']);
            $row['key'] = $private->decrypt($row['pass']);
            $shared = new AES('ctr');
            $shared->setIV($row['iv']);
            $shared->setKeyLength(256);
            $shared->setKey($row['key']);
            $row['content'] = $shared->decrypt($row['content']);
            $row['name'] = $shared->decrypt($row['name']);
            $notes[] = $row;
        }
        return $this->twig->render('folder', [
            'notes' => $notes,
            'logins' => $logins,
            'folder' => $folder,
            'title' => $folder['name'],
        ]);
    }
}
