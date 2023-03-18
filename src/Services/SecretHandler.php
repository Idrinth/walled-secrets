<?php

namespace De\Idrinth\WalledSecrets\Services;

use PDO;

class SecretHandler
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function updateNote(
        int $owner,
        string $uuid,
        int $folder,
        string $id,
        string $content,
        string $publicIdentifier
    ): void {
        $public = KeyLoader::public($uuid);
        $this->database
            ->prepare('INSERT IGNORE INTO notes (public,content,iv,`key`,id,`account`,folder)
VALUES ("","","","",:id,:owner,:folder)')
            ->execute([':id' => $id, ':owner' => $owner, ':folder' => $folder]);
        $data = AESCrypter::encrypt($public, $content);
        $this->database
            ->prepare(
                'UPDATE notes SET public=:public,content=:content, iv=:iv, `key`=:key WHERE id=:id AND `account`=:owner'
            )
            ->execute([
                ':owner' => $owner,
                ':id' => $id,
                ':key' => $data[2],
                ':iv' => $data[2],
                ':public' => $publicIdentifier,
                ':content' => $data[0],
            ]);
        $this->database
            ->prepare('UPDATE folders SET modified=NOW() WHERE aid=:id')
            ->execute([':id' => $folder]);
    }

    public function updateLogin(
        int $owner,
        string $uuid,
        int $folder,
        string $id,
        string $login,
        string $password,
        string $note,
        string $publicIdentifier
    ): void {
        $public = KeyLoader::public($uuid);
        $this->database
            ->prepare('INSERT IGNORE INTO logins (public,pass,login,iv,`key`,`note`,id,`account`,folder)
VALUES ("","","","","","",:id,:owner,:folder)')
            ->execute([':id' => $id, ':owner' => $owner, ':folder' => $folder]);
        $data = AESCrypter::encrypt($public, $note);
        $this->database
            ->prepare('UPDATE logins SET public=:public,pass=:pass, login=:login,iv=:iv,`key`=:key,`note`=:note
WHERE id=:id AND `account`=:owner')
            ->execute([
                ':owner' => $owner,
                ':id' => $id,
                ':pass' => $public->encrypt($password),
                ':public' => $publicIdentifier,
                ':login' => $public->encrypt($login),
                ':key' => $data[2],
                ':iv' => $data[1],
                ':note' => $data[0],
            ]);
        $this->database
            ->prepare('UPDATE folders SET modified=NOW() WHERE aid=:id')
            ->execute([':id' => $folder]);
    }
}
