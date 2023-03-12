<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\SecretHandler;
use De\Idrinth\WalledSecrets\Services\ShareFolderWithOrganisation;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;
use Ramsey\Uuid\Uuid;

class Folder
{
    private PDO $database;
    private Twig $twig;
    private ENV $env;
    private ShareFolderWithOrganisation $bigShare;
    private SecretHandler $smallShare;
    private May2F $twoFactor;
    private Audit $audit;

    public function __construct(
        Audit $audit,
        May2F $twoFactor,
        PDO $database,
        Twig $twig,
        ENV $env,
        ShareFolderWithOrganisation $bigShare,
        SecretHandler $smallShare
    ) {
        $this->audit = $audit;
        $this->database = $database;
        $this->env = $env;
        $this->twig = $twig;
        $this->bigShare = $bigShare;
        $this->smallShare = $smallShare;
        $this->twoFactor = $twoFactor;
    }

    public function post(User $user, array $post, string $id): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT *, "Owner" as `role`
FROM folders WHERE `owner`=:user AND id=:id AND `type`="Account"');
        $stmt->execute([':id' => $id, ':user' => $user->aid()]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $isOrganisation = false;
        if (!$folder) {
            $isOrganisation = true;
            $stmt = $this->database->prepare(
                'SELECT folders.*,memberships.`role`
FROM folders
INNER JOIN memberships ON memberships.organisation=folders.owner
WHERE memberships.account=:user
AND folders.id=:id
AND folders.`type`="Organisation"
AND memberships.`role` <> "Proposed"'
            );
            $stmt->execute([':id' => $id, ':user' => $user->aid()]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$folder) {
            header('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code'] ?? '', $user->aid(), $isOrganisation ? $folder['owner'] : 0)) {
            header('Location: /folder/' . $id, true, 303);
            return '';
        }
        if (isset($post['organisation']) && !$isOrganisation) {
            $stmt = $this->database->prepare('SELECT organisations.aid
FROM organisations
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE organisations.id=:id
AND memberships.`account`=:user
AND memberships.`role` IN ("Owner","Administrator","Member")');
            $stmt->execute([':id' => $post['organisation'], ':user' => $user->aid()]);
            $organisation = $stmt->fetchColumn();
            if (!$organisation) {
                header('Location: /folder/' . $id, true, 303);
                return '';
            }
            $this->database
                ->prepare('UPDATE folders SET `owner`=:owner,modified=NOW() AND `type`="Organisation" WHERE aid=:id')
                ->execute([':aid' => $folder['aid'], ':owner' => $organisation]);
            $this->bigShare->setOrganisation($organisation);
            $this->bigShare->setFolder($folder['aid']);
            header('Location: /folder/' . $id, true, 303);
            $this->audit->log('folder', 'create', $user->aid(), $organisation, $id);
            return '';
        }
        if (isset($post['name'])) {
            if (in_array($folder['role'], ['Administrator', 'Owner'], true)) {
                $this->database
                    ->prepare('UPDATE folders SET `name`=:name,modified=NOW() WHERE id=:id')
                    ->execute([':name' => $post['name'], ':id' => $id]);
                $this->audit->log('folder', 'modify', $user->aid(), $isOrganisation ? $folder['owner'] : null, $id);
            }
            header('Location: /folder/' . $id, true, 303);
            return '';
        }
        if (isset($post['delete'])) {
            if ($folder['default'] === '0' && in_array($folder['role'], ['Administrator', 'Owner'], true)) {
                $this->database
                    ->prepare('DELETE FROM logins WHERE folder=:id')
                    ->execute([':id' => $folder['aid']]);
                $this->database
                    ->prepare('DELETE FROM notes WHERE folder=:id')
                    ->execute([':id' => $folder['aid']]);
                $this->database
                    ->prepare('DELETE FROM folders WHERE aid=:id')
                    ->execute([':id' => $folder['aid']]);
                $this->audit->log('folder', 'delete', $user->aid(), $isOrganisation ? $folder['owner'] : null, $id);
            }
            header('Location: /', true, 303);
            return '';
        }
        if (!isset($post['id'])) {
            $post['id'] = Uuid::uuid1();
        }
        if (isset($post['password']) && isset($post['user']) && isset($post['identifier'])) {
            if ($isOrganisation) {
                $this->audit->log('login', 'create', $user->aid(), $folder['owner'], $post['id']);
                $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid
FROM accounts
INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
                $stmt->execute([':org' => $folder['owner']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->smallShare->updateLogin(
                        $row['aid'],
                        $row['id'],
                        $folder['aid'],
                        $post['id'],
                        $post['user'],
                        $post['password'],
                        $post['note'] ?? '',
                        $post['identifier']
                    );
                }
            } else {
                $this->audit->log('login', 'create', $user->aid(), null, $post['id']);
                $this->smallShare->updateLogin(
                    $user->aid(),
                    $user->id(),
                    $folder['aid'],
                    $post['id'],
                    $post['user'],
                    $post['password'],
                    $post['note'] ?? '',
                    $post['identifier']
                );
            }
        } elseif (isset($post['content']) && isset($post['public'])) {
            if ($isOrganisation) {
                $this->audit->log('note', 'create', $user->aid(), $folder['owner'], $post['id']);
                $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid
FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid
WHERE memberships.organisation=:org');
                $stmt->execute([':org' => $folder['owner']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->smallShare->updateNote(
                        $row['aid'],
                        $row['id'],
                        $folder['aid'],
                        $post['id'],
                        $post['content'],
                        $post['public']
                    );
                }
            } else {
                $this->audit->log('note', 'create', $user->aid(), null, $post['id']);
                $this->smallShare->updateNote(
                    $user->aid(),
                    $user->id(),
                    $folder['aid'],
                    $post['id'],
                    $post['content'],
                    $post['public']
                );
            }
        }
        header('Location: /folder/' . $id, true, 303);
        return '';
    }
    public function get(User $user, string $id): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT *, "Owner" as `role`
FROM folders
WHERE `owner`=:user AND id=:id AND `type`="Account"');
        $stmt->execute([':id' => $id, ':user' => $user->aid()]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $isOrganisation = false;
        if (!$folder) {
            $isOrganisation = true;
            $stmt = $this->database->prepare(
                'SELECT folders.*,memberships.`role`
FROM folders
INNER JOIN memberships ON memberships.organisation=folders.owner
WHERE memberships.account=:user
AND folders.id=:id
AND folders.`type`="Organisation"
AND memberships.role <> "Proposed"'
            );
            $stmt->execute([':id' => $id, ':user' => $user->aid()]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$folder) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT public,id FROM logins WHERE account=:user AND folder=:id');
        $stmt->execute([':id' => $folder['aid'], ':user' => $user->aid()]);
        $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT public,id FROM notes WHERE account=:user AND folder=:id');
        $stmt->execute([':id' => $folder['aid'], ':user' => $user->aid()]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $organisations = [];
        if (!$isOrganisation) {
            $stmt = $this->database->prepare('SELECT name,id
FROM organisations
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE memberships.`account`=:user AND memberships.`role`<>"Proposed"');
            $stmt->execute([':user' => $user->aid()]);
            $organisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->twig->render(
            'folder',
            [
                'notes' => $notes,
                'logins' => $logins,
                'folder' => $folder,
                'title' => $folder['name'],
                'isOrganisation' => $isOrganisation,
                'organisations' => $organisations,
            ]
        );
    }
}
