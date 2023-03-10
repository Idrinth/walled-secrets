<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\ShareWithOrganisation;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;

class Notes
{
    private PDO $database;
    private Twig $twig;
    private AES $aes;
    private Blowfish $blowfish;
    private ENV $env;
    private ShareWithOrganisation $share;
    private May2F $twoFactor;
    private Audit $audit;

    public function __construct(Audit $audit, May2F $twoFactor, PDO $database, Twig $twig, AES $aes, Blowfish $blowfish, ENV $env, ShareWithOrganisation $share)
    {
        $this->audit = $audit;
        $this->twoFactor = $twoFactor;
        $this->database = $database;
        $this->twig = $twig;
        $this->aes = $aes;
        $this->blowfish = $blowfish;
        $this->env = $env;
        $this->share = $share;
        $this->aes->setKeyLength(256);
        $this->aes->setKey($this->env->getString('PASSWORD_KEY'));
        $this->aes->setIV($this->env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($this->env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($this->env->getString('PASSWORD_BLOWFISH_IV'));
    }

    public function post(array $post, string $id): string
    {
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE id=:id AND `account`=:account');
        $stmt->execute([':id' => $id, ':account' => $_SESSION['id']]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$note) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `type`,`owner` FROM folders WHERE aid=:aid');
        $stmt->execute([':aid' => $note['folder']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $mayEdit = true;
        $isOrganisation = false;
        if ($folder['type'] === 'Organisation') {
            $stmt = $this->database->prepare('SELECT `role` FROM memberships WHERE organisation=:org AND `account`=:owner');
            $stmt->execute([':org' => $folder['owner'], ':owner' => $_SESSION['id']]);
            $role = $stmt->fetchColumn();
            $mayEdit = in_array($role, ['Administrator', 'Owner', 'Member'], true);
            $isOrganisation = true;
        }
        if (!$mayEdit) {
            header('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code'] ?? '', $_SESSION['id'], $isOrganisation ? $folder['owner'] : 0)) {
            header('Location: /logins/' . $id, true, 303);
            return '';
        }
        if (isset($post['delete'])) {
            $this->database
                ->prepare('DELETE FROM notes WHERE id=:id')
                ->execute([':id' => $id]);
            $this->database
                ->prepare('UPDATE folders SET modified=NOW() WHERE id=:id')
                ->execute([':id' => $note['folder']]);
            header('Location: /', true, 303);
            $this->audit->log('note', 'delete', $_SESSION['id'], $isOrganisation ? $folder['owner'] : null, $id);
            return '';
        }
        if (isset($post['organisation']) && !$isOrganisation) {
            $isOrganisation = true;
            list($org, $fid) = explode(':', $post['organisation']);
            $stmt = $this->database->prepare('SELECT aid,`type`,`owner` FROM folders WHERE id=:id');
            $stmt->execute([':id' => $fid]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
            $note['folder'] = $folder['aid'];
            $stmt = $this->database->prepare(
                'SELECT organisations.aid
FROM organisations
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE organisations.id=:id AND memberships.`account`=:user AND memberships.`role` IN ("Owner","Administrator","Member")'
            );
            $stmt->execute([':id' => $org, ':user' => $_SESSION['id']]);
            $organisation = $stmt->fetchColumn();
            if (!$organisation || $organisation !== $folder['owner']) {
                header('Location: /notes/' . $id, true, 303);
                return '';
            }
            set_time_limit(0);
            $master = $this->aes->decrypt($this->blowfish->decrypt($_SESSION['password']));
            $private = KeyLoader::private($_SESSION['uuid'], $master);
            if ($note['content']) {
                $note['iv'] = $private->decrypt($note['iv']);
                $note['key'] = $private->decrypt($note['key']);
                $shared = new AES('ctr');
                $shared->setIV($note['iv']);
                $shared->setKeyLength(256);
                $shared->setKey($note['key']);
                $post['content'] = $shared->decrypt($note['content']);
            }
            $this->audit->log('note', 'create', $_SESSION['id'], $organisation, $id);
        }
        if ($isOrganisation) {
            $stmt = $this->database->prepare('SELECT `aid`,`id` FROM `memberships` INNER JOIN accounts ON memberships.`account`=accounts.aid WHERE organisation=:org AND `role`<>"Proposed"');
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $this->share->updateNote($user['aid'], $user['id'], $note['folder'], $id, $post['content'], $post['identifier']);
            }
            $this->audit->log('note', 'modify', $_SESSION['id'], $folder['owner'], $id);
            header('Location: /notes/' . $id, true, 303);
            return '';
        }
        $this->audit->log('note', 'modify', $_SESSION['id'], null, $id);
        $this->share->updateNote($_SESSION['id'], $_SESSION['uuid'], $note['folder'], $id, $post['content'], $post['identifier']);
        header('Location:  /notes/' . $id, true, 303);
        return '';
    }

    public function get(string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT * FROM notes WHERE id=:id AND `account`=:account');
        $stmt->execute([':id' => $id, ':account' => $_SESSION['id']]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$note) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `type`,`owner` FROM folders WHERE aid=:aid');
        $stmt->execute([':aid' => $note['folder']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $maySee = true;
        $isOrganisation = false;
        if ($folder['type'] === 'Organisation') {
            $stmt = $this->database->prepare('SELECT `role` FROM memberships WHERE organisation=:org AND `account`=:owner');
            $stmt->execute([':org' => $folder['owner'], ':owner' => $_SESSION['id']]);
            $role = $stmt->fetchColumn();
            $maySee = in_array($role, ['Administrator', 'Owner', 'Member', 'Reader'], true);
            $isOrganisation = true;
        }
        if (!$maySee) {
            header('Location: /', true, 303);
            return '';
        }
        set_time_limit(0);
        $this->audit->log('note', 'read', $_SESSION['id'], $isOrganisation ? $folder['owner'] : null, $id);
        $master = $this->aes->decrypt($this->blowfish->decrypt($_SESSION['password']));
        $private = KeyLoader::private($_SESSION['uuid'], $master);
        if ($note['content']) {
            $note['iv'] = $private->decrypt($note['iv']);
            $note['key'] = $private->decrypt($note['key']);
            $shared = new AES('ctr');
            $shared->setIV($note['iv']);
            $shared->setKeyLength(256);
            $shared->setKey($note['key']);
            $note['content'] = $shared->decrypt($note['content']);
        }
        $organisations = [];
        if (!$isOrganisation) {
            $stmt = $this->database->prepare(
                'SELECT folders.id AS folder,folders.`name` AS folderName, organisations.`name`,organisations.id
FROM organisations
INNER JOIN folders ON organisations.aid=folders.`owner` AND folders.`type`="Organisation"
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE memberships.`account`=:id AND memberships.`role` NOT IN ("Reader","Proposed")'
            );
            $stmt->execute([':id' => $_SESSION['id']]);
            $organisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->twig->render(
            'note',
            [
            'note' => $note,
            'title' => $note['public'],
            'organisations' => $organisations,
            ]
        );
    }
}
