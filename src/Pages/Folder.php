<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Random;
use phpseclib3\Crypt\RSA;
use Ramsey\Uuid\Uuid;

class Folder
{
    private PDO $database;
    private Twig $twig;
    private ENV $env;

    public function __construct(PDO $database, Twig $twig, ENV $env)
    {
        $this->database = $database;
        $this->env = $env;
        $this->twig = $twig;
    }

    private function updateNote(int $owner, string $uuid, int $folder, string $id, string $name, string $content)
    {
        $public = RSA::loadPublicKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $uuid . '/public'));
        $this->database
            ->prepare('INSERT IGNORE INTO notes (public,content,iv,name,`key`,id,`owner`,folder) VALUES ("","","","","",:id,:owner,:folder)')
            ->execute([':id' => $id, ':owner' => $owner, ':folder' => $folder]);
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('UPDATE notes SET public=:public,content=:content, name=:name, iv=:iv, `key`=:key WHERE id=:id AND `owner`=:owner')
            ->execute([
                ':owner' => $_SESSION['id'],
                ':id' => $id,
                ':key' => $public->encrypt($key),
                ':iv' => $public->encrypt($iv),
                ':name' => $shared->encrypt($name),
                ':public' => $name,
                ':content' => $shared->encrypt($content),
            ]);
    }

    private function updateLogin(int $owner, string $uuid, int $folder, string $id, string $login, string $password, string $domain, string $note)
    {
        $public = RSA::loadPublicKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $uuid . '/public'));
        $this->database
            ->prepare('INSERT IGNORE INTO logins (public,domain,pass,login,id,iv,`key`,`note`,`account`,folder) VALUES ("","","","","","","",:id,:owner,:folder)')
            ->execute([':id' => $id, ':owner' => $owner, ':folder' => $folder]);
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('UPDATE logins SET public=:public,pass=:pass, domain=:domain, login=:login,iv=:iv,`key`=:key,`note`=:note WHERE id=:id AND `account`=:owner')
            ->execute([
                ':owner' => $_SESSION['id'],
                ':id' => $id,
                ':pass' => $public->encrypt($password),
                ':domain' => $public->encrypt($domain),
                ':public' => $domain,
                ':login' => $public->encrypt($login),
                ':key' => $public->encrypt($key),
                ':iv' => $public->encrypt($iv),
                ':note' => $shared->encrypt($note),
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
        $isOrganisation = false;
        if (!$folder) {
            $isOrganisation = true;
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
        if (isset($post['domain']) && isset($post['password']) && isset($post['user']) && isset($post['note'])) {
            if ($isOrganisation) {
                $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
                $stmt->execute([':org' => $folder['owner']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->updateLogin($row['aid'], $row['id'], $folder['aid'], $post['id'], $post['user'], $post['password'], $post['domain'], $post['note']);
                }
            } else {
                $this->updateLogin($_SESSION['id'], $_SESSION['uuid'], $folder['aid'], $post['id'], $post['user'], $post['password'], $post['domain']);
            }
        } elseif (isset($post['content']) && isset($post['name'])) {
            if ($isOrganisation) {
                $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
                $stmt->execute([':org' => $folder['owner']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->updateNote($row['aid'], $row['id'], $folder['aid'], $post['id'], $post['name'], $post['content']);
                }
            } else {
                $this->updateNote($_SESSION['id'], $_SESSION['uuid'], $folder['aid'], $post['id'], $post['name'], $post['content']);
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
        $stmt = $this->database->prepare('SELECT public,id FROM logins WHERE account=:user AND folder=:id');
        $stmt->execute([':id' => $folder['aid'], ':user' => $_SESSION['id']]);
        $logins = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $logins[] = $row;
        }
        $stmt = $this->database->prepare('SELECT public,id FROM notes WHERE account=:user AND folder=:id');
        $stmt->execute([':id' => $folder['aid'], ':user' => $_SESSION['id']]);
        $notes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
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
