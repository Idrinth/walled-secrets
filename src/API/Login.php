<?php

namespace De\Idrinth\WalledSecrets\API;

use De\Idrinth\WalledSecrets\Services\KeyLoader;
use PDO;
use phpseclib3\Crypt\AES;

class Login
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function post(array $post, string $id): string
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        if (!isset($post['email']) || !isset($post['apikey']) || !isset($post['master'])) {
            header('Content-Type: application/json', true, 403);
            return '{"error":"email and apikey must be set."}';
        }
        $stmt = $this->database->prepare('SELECT aid,id FROM accounts WHERE mail=:mail and apikey=:apikey');
        $stmt->execute([':mail' => $post['email'], ':apikey' => $post['apikey']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            header('Content-Type: application/json', true, 403);
            return '{"error":"eMail and ApiKey can\'t be found"}';
        }
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE id=:id AND `account`=:owner');
        $stmt->execute([':id' => $id, ':owner' => $user['aid']]);
        $login = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$login) {
            header('Content-Type: application/json', true, 404);
            return '{"error":"Secret can\'t be found"}';
        }
        set_time_limit(0);
        try {
            $private = KeyLoader::private($user['id'], $post['master']);
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 403);
            return '{"error":"Master Password is wrong."}';
        }
        $login['login'] = $private->decrypt($login['login']);
        $login['pass'] = $private->decrypt($login['pass']);
        $login['domain'] = $private->decrypt($login['domain']);
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
            'login' => $login['login'],
            'pass' => $login['pass'],
            'domain' => $login['domain'],
            'note' => $login['note'],
        ]);
    }
}
