<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\ShareFolderWithOrganisation;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Random;
use Ramsey\Uuid\Uuid;

class Organisation
{
    private PDO $database;
    private Twig $twig;
    private ShareFolderWithOrganisation $share;
    private May2F $twoFactor;

    public function __construct(May2F $twoFactor, PDO $database, Twig $twig, ShareFolderWithOrganisation $share)
    {
        $this->database = $database;
        $this->twig = $twig;
        $this->share = $share;
        $this->twoFactor = $twoFactor;
    }
    private function addKnown(int $user, int $known, string $uuid, string $comment): void
    {
        $public = KeyLoader::public($uuid);
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('INSERT IGNORE INTO knowns (`owner`,target,note,iv,`key`,id) VALUES (:owner,:target,:comment,:iv,:key,:id)')
            ->execute([
                ':comment' => $shared->encrypt($comment),
                ':iv' => $public->encrypt($iv),
                ':key' => $public->encrypt($key),
                ':owner' => $user,
                ':target' => $known,
                ':target' => Uuid::uuid1(),
            ]);
    }

    public function post(array $post, string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT organisations.*,memberships.role FROM organisations INNER JOIN memberships ON memberships.organisation=organisations.aid WHERE organisations.id=:id AND memberships.account=:user AND memberships.role<>"Proposed"');
        $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
        $organisation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$organisation) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code']??'', $_SESSION['id'], $organisation['aid'])) {
            header ('Location: /organisation/'.$id, true, 303);
            return '';            
        }
        if (isset($post['folder']) && in_array($organisation['role'], ['Administrator', 'Owner'], true)) {
            $this->database
                ->prepare('INSERT INTO folders (`name`,`owner`,id,`type`) VALUES (:name, :owner,:id, "Organisation")')
                ->execute([':name' => $post['folder'], ':owner ' => $organisation['aid'], ':id' => Uuid::uuid1()->toString()]);
            header ('Location: /organisation/'.$id, true, 303);
            return '';
        }
        if (isset($post['id']) && isset($post['role']) && in_array($organisation['role'], ['Administrator', 'Owner'], true)) {
            if ($_SESSION['uuid'] !== $post['id']) {
                $stmt = $this->database->prepare('SELECT accounts.aid,memberships.role FROM memberships INNER JOIN accounts ON memberships.account=accounts.aid WHERE accounts.id=:id AND memberships.organisation=:org');
                $stmt->execute([':org' => $organisation['aid'], ':id' => $post['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    header ('Location: /organisation/'.$id, true, 303);
                    return '';
                }
                if ($organisation['role'] === 'Owner') {
                    $this->database
                        ->prepare('UPDATE memberships SET `role`=:role WHERE organisation=:org AND `account`=:id')
                        ->execute([':role' => $post['role'], ':id' => $user['aid'], ':org' => $organisation['aid']]);
                    if ($post['role'] === 'Owner') {
                        $this->database
                            ->prepare('UPDATE memberships SET `role`=:role WHERE organisation=:org AND `account`=:id')
                            ->execute([':role' => 'Administrator', ':id' => $_SESSION['id'], ':org' => $organisation['aid']]);
                    }
                } elseif ($organisation['role'] === 'Administrator' && in_array($user['role'], ['Member', 'Reader', 'Proposed']) && in_array($post['role'], ['Member', 'Reader', 'Proposed'])) {
                    $this->database
                        ->prepare('UPDATE memberships SET `role`=:role WHERE organisation=:org AND `account`=:id')
                        ->execute([':role' => $post['role'], ':id' => $user['aid'], ':org' => $organisation['aid']]);
                    if ($post['role'] !== 'Proposed' && $user['role'] !== 'Proposed') {
                        $stmt = $this->prepare('SELECT aid FROM folders WHERE `owner`=:org AND `type`="Organisation"');
                        $stmt->execute([':org' => $organisation['aid']]);
                        $this->share->setOrganisation($organisation['aid']);
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                            $this->share->setFolder($row['aid']);
                        }
                    }
                }
            }
        }
        if (isset($post['known'])) {
            $stmt = $this->database->prepare('SELECT accounts.aid,memberships.role FROM memberships INNER JOIN accounts ON memberships.account=accounts.aid WHERE accounts.id=:id AND memberships.organisation=:org');
            $stmt->execute([':org' => $organisation['aid'], ':id' => $post['known']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                header ('Location: /organisation/'.$id, true, 303);
                return '';
            }
            $stmt = $this->database->prepare('SELECT account FROM memberships WHERE role <> "Proposed" AND organisation=:org');
            $stmt->execute([':org' => $organisation['aid']]);
            foreach ($stmt->fetchAll() as $account) {
                $this->addKnown($user['aid'], $account['account'], Uuid::uuid1()->toString(), 'Shares the group ' . $organisation['name'] . '.');
                $this->addKnown($account['account'], $user['aid'], Uuid::uuid1()->toString(), 'Shares the group ' . $organisation['name'] . '.');
            }
            $this->database
                ->prepare('INSERT INTO memberships (organisation,account) VALUES (:org,:id)')
                ->execute([':id' => $user['aid'], ':org' => $organisation['aid']]);
        }
        header ('Location: /organisation/'.$id, true, 303);
        return '';
    }

    public function get(string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT organisations.*,memberships.role FROM organisations INNER JOIN memberships ON memberships.organisation=organisations.aid WHERE organisations.id=:id AND memberships.account=:user AND memberships.role<>"Proposed"');
        $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
        $organisation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$organisation) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT memberships.role,accounts.id,accounts.display FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
        $stmt->execute([':org' => $organisation['aid']]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT accounts.*
FROM knowns
INNER JOIN accounts ON accounts.aid=knowns.target
WHERE `owner`=:id AND target NOT IN (SELECT `account` FROM memberships WHERE organisation=:org)');
        $stmt->execute([':org' => $organisation['aid'], ':id' => $_SESSION['id']]);
        $knowns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT * FROM folders WHERE owner=:org AND `type`="Organisation"');
        $stmt->execute([':org' => $organisation['aid']]);
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->twig->render('organisation', [
            'members' => $members,
            'knowns' => $knowns,
            'organisation' => $organisation,
            'title' => $organisation['name'],
            'folders' => $folders,
        ]);
    }
}
