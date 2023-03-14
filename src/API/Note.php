<?php

namespace De\Idrinth\WalledSecrets\API;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use Exception;
use PDO;
use phpseclib3\Crypt\AES;

class Note
{
    private PDO $database;
    private Audit $audit;

    public function __construct(Audit $audit, PDO $database)
    {
        $this->audit = $audit;
        $this->database = $database;
    }
    public function options(User $user): string
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        return '';
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
        $stmt = $this->database->prepare('SELECT * FROM notes WHERE id=:id AND `account`=:owner');
        $stmt->execute([':id' => $id, ':owner' => $user->aid()]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$note) {
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
        $stmt = $this->database->prepare('SELECT `owner` FROM folders WHERE aid=:folder AND `type`="Organisation"');
        $stmt->execute([':folder' => $note['folder']]);
        $this->audit->log('note', 'read', $user->aid(), $stmt->fetchColumn() ?: null, $id);
        $note['name'] = $private->decrypt($note['name']);
        if ($note['content']) {
            $note['iv'] = $private->decrypt($note['iv']);
            $note['key'] = $private->decrypt($note['key']);
            $shared = new AES('ctr');
            $shared->setIV($note['iv']);
            $shared->setKeyLength(256);
            $shared->setKey($note['key']);
            $note['content'] = $shared->decrypt($note['content']);
        }
        return json_encode([
            'public' => $note['public'],
            'id' => $note['id'],
            'content' => $note['content'],
        ]);
    }
}
