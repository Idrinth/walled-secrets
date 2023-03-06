<?php

namespace De\Idrinth\WalledSecrets\Services;

use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Random;

class ShareWithOrganisation
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function updateNote(int $owner, string $uuid, int $folder, string $id, string $name, string $content)
    {
        $public = KeyLoader::public($uuid);
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

    public function updateLogin(int $owner, string $uuid, int $folder, string $id, string $login, string $password, string $domain, string $note, string $publicIdentifier)
    {
        $public = KeyLoader::public($uuid);
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
                ':public' => $publicIdentifier,
                ':login' => $public->encrypt($login),
                ':key' => $public->encrypt($key),
                ':iv' => $public->encrypt($iv),
                ':note' => $shared->encrypt($note),
            ]);
    }
}
