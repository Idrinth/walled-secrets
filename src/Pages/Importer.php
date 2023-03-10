<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\SecretHandler;
use De\Idrinth\WalledSecrets\Services\Twig;
use DOMDocument;
use League\Csv\Reader;
use PDO;
use Ramsey\Uuid\Uuid;

class Importer
{
    private Twig $twig;
    private ENV $env;
    private PDO $database;
    private SecretHandler $share;
    private Audit $audit;

    public function __construct(Audit $audit, Twig $twig, ENV $env, PDO $database, SecretHandler $share)
    {
        $this->audit = $audit;
        $this->twig = $twig;
        $this->env = $env;
        $this->database = $database;
        $this->share = $share;
    }

    public function get(User $user): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        return $this->twig->render('import', ['title' => 'Import']);
    }
    private function getFolder(User $user, string $name): int
    {
        $stmt = $this->database->prepare('SELECT aid
FROM folders
WHERE `owner`=:owner AND `type`="Account" AND `name`=:name');
        $stmt->execute([':name' => $name, ':owner' => $user->aid()]);
        $folder = $stmt->fetchColumn();
        if ($folder) {
            return $folder;
        }
        $folder = Uuid::uuid1()->toString();
        $this->audit->log('folder', 'create', $user->aid(), null, $folder);
        $this->database
            ->prepare('INSERT INTO folders (id,`name`,`owner`) VALUES (:id,:name,:owner)')
            ->execute([':name' => $name, ':owner' => $user->aid(), ':id' => $folder]);
        return $this->database->lastInsertId();
    }
    private function importKeypass(User $user, string $file): string
    {
        $dom = new DOMDocument();
        if (!$dom->load($file)) {
            return '';
        }
        $groups = $dom->getElementsByTagName('Group');
        for ($i = 0; $i < $groups->count(); $i++) {
            $group = $groups->item($i);
            $folder = $this->getFolder($group->getElementsByTagName('Name')->item(0)->nodeValue);
            for ($j = 0; $j < $group->childNodes->length; $j++) {
                $secret = $group->childNodes->item($j);
                if ($secret->localName === 'Entry') {
                    $note = '';
                    $password = '';
                    $login = '';
                    $publicIdentifier = '';
                    for ($k = 0; $k < $secret->childNodes->length; $k++) {
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
                                    $publicIdentifier .= ' ' . preg_replace(
                                        '/http?s:\/\/(.+?)($|\/.*$)/',
                                        '$1',
                                        $data->getElementsByTagName('Value')->item(0)->nodeValue
                                    );
                                    break;
                                case 'Notes':
                                    $note = $data->getElementsByTagName('Value')->item(0)->nodeValue;
                                    break;
                            }
                        }
                    }
                    $uuid = Uuid::uuid1()->toString();
                    $this->audit->log('login', 'create', $user->aid(), null, $uuid);
                    $this->share->updateLogin(
                        $user->aid(),
                        $user->id(),
                        $folder,
                        $uuid,
                        $login,
                        $password,
                        $note,
                        trim($publicIdentifier)
                    );
                }
            }
        }
        return '';
    }
    private function importBitwarden(User $user, string $file): string
    {
        $data = json_decode(file_get_contents($file), true);
        $folders = [];
        foreach ($data['folders'] as $folder) {
            $folders[$folder['id']] = $this->getFolder($user, $folder['name']);
        }
        foreach ($data['items'] as $item) {
            if ($item['type'] === 1) {
                $uuid = Uuid::uuid1()->toString();
                $this->audit->log('login', 'create', $user->aid(), null, $uuid);
                $this->share->updateLogin(
                    $user->aid(),
                    $user->id(),
                    $folders[$item['folderId']],
                    $uuid,
                    $item['login']['username'] ?? '',
                    $item['login']['password'] ?? '',
                    '',
                    $item['name'],
                );
            }
        }
        return '';
    }
    private function importFirefox(User $user, string $file): string
    {
        $stmt = $this->database->prepare('SELECT aid FROM folders WHERE `owner`=:owner AND `default`=1');
        $stmt->execute([':owner' => $user->aid()]);
        $folder = $stmt->fetchColumn();
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        foreach ($csv->getRecords() as $record) {
            $uuid = Uuid::uuid1()->toString();
            $this->audit->log('login', 'create', $user->aid(), null, $uuid);
            $this->share->updateLogin(
                $user->aid(),
                $user->id(),
                $folder,
                $uuid,
                $record['username'],
                $record['password'],
                '',
                preg_replace('/http?s:\/\/(.+?)($|\/.*$)/', '$1', $record['url'])
            );
        }
        return '';
    }
    public function post(User $user, array $post): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        header('Location: /', true, 303);
        switch ($post['source']) {
            case '0':
                return $this->importKeypass($user, $_FILES['file']['tmp_name']);
            case '1':
                return $this->importBitwarden($user, $_FILES['file']['tmp_name']);
            case '2':
                return $this->importFirefox($user, $_FILES['file']['tmp_name']);
        }
        return '';
    }
}
