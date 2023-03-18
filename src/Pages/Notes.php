<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\AESCrypter;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\MasterPassword;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\SecretHandler;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;

class Notes
{
    private PDO $database;
    private Twig $twig;
    private SecretHandler $share;
    private May2F $twoFactor;
    private Audit $audit;
    private MasterPassword $master;

    public function __construct(
        Audit $audit,
        May2F $twoFactor,
        PDO $database,
        Twig $twig,
        SecretHandler $share,
        MasterPassword $master
    ) {
        $this->audit = $audit;
        $this->twoFactor = $twoFactor;
        $this->database = $database;
        $this->twig = $twig;
        $this->share = $share;
        $this->master = $master;
    }

    public function post(User $user, array $post, string $id): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE id=:id AND `account`=:account');
        $stmt->execute([':id' => $id, ':account' => $user->aid()]);
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
            $stmt = $this->database->prepare(
                'SELECT `role` FROM memberships WHERE organisation=:org AND `account`=:owner'
            );
            $stmt->execute([':org' => $folder['owner'], ':owner' => $user->aid()]);
            $role = $stmt->fetchColumn();
            $mayEdit = in_array($role, ['Administrator', 'Owner', 'Member'], true);
            $isOrganisation = true;
        }
        if (!$mayEdit) {
            header('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code'] ?? '', $user->aid(), $isOrganisation ? $folder['owner'] : 0)) {
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
            $this->audit->log('note', 'delete', $user->aid(), $isOrganisation ? $folder['owner'] : null, $id);
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
WHERE organisations.id=:id
AND memberships.`account`=:user
AND memberships.`role` IN ("Owner","Administrator","Member")'
            );
            $stmt->execute([':id' => $org, ':user' => $user->aid()]);
            $organisation = $stmt->fetchColumn();
            if (!$organisation || $organisation !== $folder['owner']) {
                header('Location: /notes/' . $id, true, 303);
                return '';
            }
            set_time_limit(0);
            $private = KeyLoader::private($user->id(), $this->master->get());
            $note['content'] = AESCrypter::decrypt($private, $note['content'], $note['iv'], $note['key']);
            $this->audit->log('note', 'create', $user->aid(), $organisation, $id);
        }
        if ($isOrganisation) {
            $stmt = $this->database->prepare('SELECT `aid`,`id`
FROM `memberships`
INNER JOIN accounts ON memberships.`account`=accounts.aid
WHERE organisation=:org AND `role`<>"Proposed"');
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $this->share->updateNote(
                    $user['aid'],
                    $user['id'],
                    $note['folder'],
                    $id,
                    $post['content'],
                    $post['identifier']
                );
            }
            $this->audit->log('note', 'modify', $user->aid(), $folder['owner'], $id);
            header('Location: /notes/' . $id, true, 303);
            return '';
        }
        $this->audit->log('note', 'modify', $user->aid(), null, $id);
        $this->share->updateNote(
            $user->aid(),
            $user->id(),
            $note['folder'],
            $id,
            $post['content'],
            $post['identifier']
        );
        header('Location:  /notes/' . $id, true, 303);
        return '';
    }

    public function get(User $user, string $id): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        if (!$this->master->has()) {
            session_destroy();
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT * FROM notes WHERE id=:id AND `account`=:account');
        $stmt->execute([':id' => $id, ':account' => $user->aid()]);
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
            $stmt = $this->database->prepare(
                'SELECT `role` FROM memberships WHERE organisation=:org AND `account`=:owner'
            );
            $stmt->execute([':org' => $folder['owner'], ':owner' => $user->aid()]);
            $role = $stmt->fetchColumn();
            $maySee = in_array($role, ['Administrator', 'Owner', 'Member', 'Reader'], true);
            $isOrganisation = true;
        }
        if (!$maySee) {
            header('Location: /', true, 303);
            return '';
        }
        set_time_limit(0);
        $this->audit->log('note', 'read', $user->aid(), $isOrganisation ? $folder['owner'] : null, $id);
        $private = KeyLoader::private($user->id(), $this->master->get());
        $note['content'] = AESCrypter::decrypt($private, $note['content'], $note['iv'], $note['key']);
        $organisations = [];
        if (!$isOrganisation) {
            $stmt = $this->database->prepare(
                'SELECT folders.id AS folder,folders.`name` AS folderName, organisations.`name`,organisations.id
FROM organisations
INNER JOIN folders ON organisations.aid=folders.`owner` AND folders.`type`="Organisation"
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE memberships.`account`=:id AND memberships.`role` NOT IN ("Reader","Proposed")'
            );
            $stmt->execute([':id' => $user->aid()]);
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
