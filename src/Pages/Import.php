<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;
use PDO;

class Import
{
    private Twig $twig;
    private Env $env;
    private PDO $database;

    public function get(): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        return $this->twig->render('import', ['title' => 'Import']);
    }

    private function updateLogin(int $owner, string $uuid, int $folder, string $id, string $login, string $password, string $domain, string $note)
    {
        $public = RSA::loadPublicKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $uuid . '/public'));
        $this->database
            ->prepare('INSERT IGNORE INTO logins (domain,pass,login,id,iv,`key`,`note`,`account`,folder) VALUES ("","","","","","",:id,:owner,:folder)')
            ->execute([':id' => $id, ':owner' => $owner, ':folder' => $folder]);
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('UPDATE logins SET pass=:pass, domain=:domain, login=:login,iv=:iv,`key`=:key,`note`=:note WHERE id=:id AND `account`=:owner')
            ->execute([
                ':owner' => $_SESSION['id'],
                ':id' => $id,
                ':pass' => $public->encrypt($password),
                ':domain' => $public->encrypt($domain),
                ':login' => $public->encrypt($login),
                ':key' => $public->encrypt($key),
                ':iv' => $public->encrypt($iv),
                ':note' => $shared->encrypt($note),
            ]);
    }
    private function importKeypass(string $file): string
    {
        return '';        
    }
    private function importBitwarden(string $file): string
    {
        $data = json_decode(file_get_contents($file), true);
        $folders = [];
        foreach ($data['folders'] as $folder) {
            $this->database
                ->prepare('INSERT INTO folders (id,`name`,`owner`) VALUES (:id,:name,:owner)')
                ->execute([':name' => $folder['name'], ':owner' => $_SESSION['id']]);
            $folders[$folder['id']] = $this->database->lastInsertId();
        }
        foreach ($data['items'] as $item) {
            if ($item['type'] === 1) {
                $this->updateLogin($_SESSION['id'], $_SESSION['uuid'], $folders[$item['folderId']], Uuid::uuid1()->toString(), $item['login']['username'], $item['login']['password'], $item['name'], '');
            }
        }
        return '';
    }
    public function post(array $post): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        header ('Location: /', true, 303);
        switch ($post['source']) {
            case '0':
                return $this->importKeypass($_FILES['file']['tmp_name']);
            case '1':
                return $this->importBitwarden($_FILES['file']['tmp_name']);
        }
        return '';
    }
}
