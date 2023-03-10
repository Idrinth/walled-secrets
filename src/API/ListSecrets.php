<?php

namespace De\Idrinth\WalledSecrets\API;

use De\Idrinth\WalledSecrets\Services\Audit;
use PDO;

class ListSecrets
{
    private PDO $database;
    private Audit $audit;

    public function __construct(Audit $audit, PDO $database)
    {
        $this->database = $database;
        $this->audit = $audit;
    }

    public function post(array $post)
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type,X-LAST-UPDATED');
        if (!isset($post['email']) || !isset($post['apikey'])) {
            header('Content-Type: application/json', true, 403);
            return '{"error":"email and apikey must be set."}';
        }
        $stmt = $this->database->prepare('SELECT aid FROM accounts WHERE mail=:mail and apikey=:apikey');
        $stmt->execute([':mail' => $post['email'], ':apikey' => $post['apikey']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            header('Content-Type: application/json', true, 403);
            return '{"error":"eMail and ApiKey can\'t be found"}';
        }
        $headers = apache_request_headers();
        if (isset($headers['X-LAST-UPDATED']) && intval($headers['X-LAST-UPDATED'], 10) !== 0) {
            $stmt = $this->database->prepare('SELECT MAX(modified) FROM folders WHERE (`owner`=:id AND `type`="Account") OR (`type`="Organisation" AND `owner` IN (SELECT organisation FROM memberships WHERE `role`<>"Proposed" AND `account`=:id))');
            $stmt->execute([':id' => $user['aid']]);
            $lastModified = intval($stmt->fetchColumn(), 10);
            if (intval($headers['X-LAST-UPDATED'], 10) > $lastModified * 1000) {
                header('Content-Type: text/plain', true, 304);
                return '';
            }
        }
        $organisations = [];
        $stmt = $this->database->prepare('SELECT organisations.aid,organisations.name FROM organisations INNER JOIN memberships ON memberships.organisation=organisations.aid WHERE memberships.`role`<>"Proposed" AND memberships.`account`=:id');
        $stmt->execute([':id' => $user['aid']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $org) {
            $organisations[$org['aid']] = $org['name'];
        }
        $data = [];
        $stmt = $this->database->prepare('SELECT * FROM folders WHERE (`owner`=:id AND `type`="Account") OR (`type`="Organisation" AND `owner` IN (SELECT organisation FROM memberships WHERE `role`<>"Proposed" AND `account`=:id))');
        $stmt->execute([':id' => $user['aid']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $folder) {
            $stmt2 = $this->database->prepare('SELECT public,id FROM notes WHERE folder=:folder AND `account`=:id');
            $stmt2->execute([':id' => $user['aid'], ':folder' => $folder['aid']]);
            $stmt3 = $this->database->prepare('SELECT public,id FROM logins WHERE folder=:folder AND `account`=:id');
            $stmt3->execute([':id' => $user['aid'], ':folder' => $folder['aid']]);
            $data[$folder['id']] = [
                'type' => $folder['type'],
                'name' => $folder['name'],
                'notes' => $stmt2->fetchAll(PDO::FETCH_ASSOC),
                'logins' => $stmt3->fetchAll(PDO::FETCH_ASSOC),
            ];
            if ($folder['type'] === 'Organisation') {
                $data[$folder['id']]['organisation'] = $organisations[$folder['owner']];
                $this->audit->log('folder', 'read', $user['aid'], $folder['owner'], $folder['id']);
            } else {
                $this->audit->log('folder', 'read', $user['aid'], null, $folder['id']);
            }
        }
        header('Content-Type: application/json', true, 200);
        return json_encode($data);
    }
}
