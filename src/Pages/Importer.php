<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\ShareWithOrganisation;
use De\Idrinth\WalledSecrets\Twig;
use DOMDocument;
use PDO;
use Ramsey\Uuid\Uuid;

class Importer
{
    private Twig $twig;
    private ENV $env;
    private PDO $database;
    private ShareWithOrganisation $share;

    public function __construct(Twig $twig, ENV $env, PDO $database, ShareWithOrganisation $share)
    {
        $this->twig = $twig;
        $this->env = $env;
        $this->database = $database;
        $this->share = $share;
    }

    public function get(): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        return $this->twig->render('import', ['title' => 'Import']);
    }
    private function importKeypass(string $file): string
    {
        $dom = new DOMDocument();
        if (!$dom->load($file)) {
            return '';
        }
        $groups = $dom->getElementsByTagName('Group');
        for ($i = 0; $i < $groups->count(); $i++) {
            $group = $groups->item($i);
            $this->database
                ->prepare('INSERT INTO folders (id,`name`,`owner`) VALUES (:id,:name,:owner)')
                ->execute([
                    ':name' => $group->getElementsByTagName('Name')->item(0)->nodeValue,
                    ':owner' => $_SESSION['id'],
                    ':id' => Uuid::uuid1()->toString()
                ]);
            $folder = $this->database->lastInsertId();
            for ($j = 0; $j < $group->childElementCount; $j++) {
                $secret = $group->childNodes->item($j);
                var_dump($secret);
                if ($secret->localName === 'Entry') {
                    $note = '';
                    $password = '';
                    $login = '';
                    $domain = '';
                    $publicIdentifier = '';
                    for ($k = 0; $k < $secret->childElementCount; $k++) {
                        $data = $secret->childNodes->item($k);
                        if ($data->localName === 'String') {
                            switch ($data->getElementsByTagName('Key')->item(0)->nodeValue) {
                                case 'Title':
                                    $publicIdentifier .= ' ' . $data->getElementsByTagName('Value')->item(0)->nodeValue;
                                    break;
                                case 'UserName':
                                    $login = $data->getElementsByTagName('Value')->item(0)->nodeValue;
                                    break;
                                case 'Password':
                                    $password = $data->getElementsByTagName('Value')->item(0)->nodeValue;
                                    break;
                                case 'URL':
                                    $domain = preg_replace('/http?s:\/\/(.+?)($|\/.*$)/', '$1', $data->getElementsByTagName('Value')->item(0)->nodeValue);
                                    $publicIdentifier .= ' ' . $domain;
                                    break;
                                case 'Notes':
                                    $note = $data->getElementsByTagName('Value')->item(0)->nodeValue;
                                    break;
                            }
                        }
                    }
                    $this->share->updateLogin(
                        $_SESSION['id'],
                        $_SESSION['uuid'],
                        $folder,
                        Uuid::uuid1()->toString(),
                        $login,
                        $password,
                        $domain,
                        $note,
                        trim($publicIdentifier)
                    );
                }
            }
        }
        exit;
        return '';        
    }
    private function importBitwarden(string $file): string
    {
        $data = json_decode(file_get_contents($file), true);
        $folders = [];
        foreach ($data['folders'] as $folder) {
            $stmt = $this->database->prepare('SELECT aid FROM folders WHERE `owner`=:owner AND `name`=:name');
            $stmt->execute([':name' => $folder['name'], ':owner' => $_SESSION['id']]);
            $folders[$folder['id']] = $stmt->fetchColumn();
            if (!$folders[$folder['id']]) {
                $this->database
                    ->prepare('INSERT INTO folders (id,`name`,`owner`) VALUES (:id,:name,:owner)')
                    ->execute([':name' => $folder['name'], ':owner' => $_SESSION['id'], ':id' => Uuid::uuid1()->toString()]);
                $folders[$folder['id']] = $this->database->lastInsertId();
            }
        }
        foreach ($data['items'] as $item) {
            if ($item['type'] === 1) {
                $this->share->updateLogin(
                    $_SESSION['id'],
                    $_SESSION['uuid'],
                    $folders[$item['folderId']],
                    Uuid::uuid1()->toString(),
                    $item['login']['username'] ?? '',
                    $item['login']['password'] ?? '',
                    '',
                    '',
                    $item['name'],
                );
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
