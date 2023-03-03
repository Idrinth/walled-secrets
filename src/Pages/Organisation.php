<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;
use PDO;

class Organisation
{
    private PDO $database;
    private Twig $twig;

    public function __construct(PDO $database, Twig $twig)
    {
        $this->database = $database;
        $this->twig = $twig;
    }
    private function addKnown(int $user, int $known, string $uuid, string $comment): void
    {
        $public = RSA::loadPublicKey(file_get_contents(dirname(__DIR__, 2) . '/keys/' . $uuid . '/public'));
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('INSERT IGNORE INTO knowns (`owner`,target,note,iv,`key`) VALUES (:owner,:target,:comment,:iv,:key)')
            ->execute([
                ':comment' => $shared->encrypt($comment),
                ':iv' => $public->encrypt($iv),
                ':key' => $public->encrypt($key),
                ':owner' => $user,
                ':target' => $known,
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
        if (isset($post['folder']) && in_array($organisation['role'], ['Administrator', 'Owner'], true)) {
            $this->database
                ->prepare('INSERT INTO folders (`name`,`owner`,id,`type`) VALUES (:name, :owner,:uuid, "Organisation")')
                ->execute([':name' => $post['folder'], ':owner ' => $organisation['aid'], ':uuid' => Uuid::uuid1()->toString()]);
            header ('Location: /organisation/'.$id, true, 303);
            return;
        }
        if (isset($post['id']) && isset($post['role']) && in_array($organisation['role'], ['Administrator', 'Owner'], true)) {
            if ($_SESSION['uuid'] !== $post['id']) {
                $stmt = $this->database->prepare('SELECT accounts.aid,memberships.role FROM memberships INNER JOIN accounts ON memberships.account=accounts.aid WHERE accounts.id=:id AND memberships.organisation=:org');
                $stmt->execute([':org' => $organisation['aid'], ':id' => $post['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    header ('Location: /organisation/'.$id, true, 303);
                    return;
                }
                //@todo
            }
        }
        if (isset($post['known'])) {
            $stmt = $this->database->prepare('SELECT accounts.aid,memberships.role FROM memberships INNER JOIN accounts ON memberships.account=accounts.aid WHERE accounts.id=:id AND memberships.organisation=:org');
            $stmt->execute([':org' => $organisation['aid'], ':id' => $post['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                header ('Location: /organisation/'.$id, true, 303);
                return;
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
        return;
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
        $stmt = $this->database->prepare('SELECT target FROM knowns WHERE owner=:id AND target NOT IN (SELECT account FROM memberships WHERE organisation=:org)');
        $stmt->execute([':org' => $organisation['aid'], ':id' => $_SESSION['id']]);
        $knowns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT * FROM folders WHERE owner=:org AND `type`="Organisation"');
        $stmt->execute([':org' => $organisation['aid']]);
        $knowns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->twig->render('organisation', [
            'members' => $members,
            'knowns' => $knowns,
            'organisation' => $organisation,
            'title' => $organisation['name'],
            'folders' => $folders,
        ]);
    }
}
