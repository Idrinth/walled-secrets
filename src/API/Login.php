<?php

namespace De\Idrinth\WalledSecrets\API;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use Exception;
use PDO;
use phpseclib3\Crypt\AES;

class Login
{
    private PDO $database;
    private Audit $audit;

    public function __construct(Audit $audit, PDO $database)
    {
        $this->audit = $audit;
        $this->database = $database;
    }

    public function post(User $user, array $post, string $id): string
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        if ($user->aid() === 0) {
            header('Content-Type: application/json', true, 403);
            return '{"error":"eMail and ApiKey can\'t be found"}';
        }
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE id=:id AND `account`=:owner');
        $stmt->execute([':id' => $id, ':owner' => $user->aid()]);
        $login = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$login) {
            header('Content-Type: application/json', true, 404);
            return '{"error":"Secret can\'t be found"}';
        }
        set_time_limit(0);
        try {
            $private = KeyLoader::private($user->id(), $post['master']);
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 403);
            return '{"error":"Master Password is wrong."}';
        }
        $stmt = $this->database->prepare('SELECT `owner` FROM folders WHERE aid=:folder AND `type`="Account"');
        $stmt->execute([':folder' => $login['folder']]);
        $this->audit->log('note', 'read', $user->aid(), $stmt->fetchColumn() ?: null, $id);
        $login['login'] = $private->decrypt($login['login']);
        $login['pass'] = $private->decrypt($login['pass']);
        if ($login['note']) {
            $login['iv'] = $private->decrypt($login['iv']);
            $login['key'] = $private->decrypt($login['key']);
            $shared = new AES('ctr');
            $shared->setIV($login['iv']);
            $shared->setKeyLength(256);
            $shared->setKey($login['key']);
            $login['note'] = $shared->decrypt($login['note']);
        }
        return json_encode([
            'public' => $login['public'],
            'id' => $login['id'],
            'login' => $login['login'],
            'pass' => $login['pass'],
            'note' => $login['note'],
        ]);
    }
}
