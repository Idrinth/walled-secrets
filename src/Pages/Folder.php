<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\ShareFolderWithOrganisation;
use De\Idrinth\WalledSecrets\Services\ShareWithOrganisation;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use Ramsey\Uuid\Uuid;

class Folder
{
    private PDO $database;
    private Twig $twig;
    private ENV $env;
    private ShareFolderWithOrganisation $bigShare;
    private ShareWithOrganisation $smallShare;
    private May2F $twoFactor;

    public function __construct(May2F $twoFactor, PDO $database, Twig $twig, ENV $env, ShareFolderWithOrganisation $bigShare, ShareWithOrganisation $smallShare)
    {
        $this->database = $database;
        $this->env = $env;
        $this->twig = $twig;
        $this->bigShare = $bigShare;
        $this->smallShare = $smallShare;
        $this->twoFactor = $twoFactor;
    }

    public function post(array $post, string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT *, "Owner" as `role` FROM folders WHERE `owner`=:user AND id=:id AND `type`="Account"');
        $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $isOrganisation = false;
        if (!$folder) {
            $isOrganisation = true;
            $stmt = $this->database->prepare('SELECT folders.*,memberships.`role`
FROM folders
INNER JOIN memberships ON memberships.organisation=folders.owner
WHERE memberships.account=:user AND folders.id=:id AND folders.`type`="Organisation" AND memberships.`role` <> "Proposed"');
            $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$folder) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header ('Location: /', true, 303);
            return '';            
        }
        if (!$this->twoFactor->may($post['code']??'', $_SESSION['id'], $isOrganisation ? $folder['owner'] : 0)) {
            header ('Location: /folder/' . $id, true, 303);
            return '';            
        }
        if (isset($post['organisation'])) {
            $this->database
                ->prepare('UPDATE folders SET `owner`=:owner AND `type`="Organisation" WHERE aid=:id')
                ->execute([':aid' => $folder['aid'], ':owner' => $post['organisation']]);
            $this->bigShare->setOrganisation($post['organisation']);
            $this->bigShare->setFolder($folder['aid']);
            header ('Location: /folder/' . $id, true, 303);
            return '';
        }
        if (isset($post['name'])) {
            if (in_array($folder['role'], ['Administrator', 'Owner'], true)) {
                $this->database
                    ->prepare('UPDATE folders SET `name`=:name WHERE id=:id')
                    ->execute([':name' => $post['name'], ':id' => $post['id']]);
            }
            header ('Location: /folder/' . $id, true, 303);
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
            }
            header ('Location: /', true, 303);
            return '';
        }
        if (!isset($post['id'])) {
            $post['id'] = Uuid::uuid1();
        }
        if (isset($post['domain']) && isset($post['password']) && isset($post['user']) && isset($post['identifier'])) {
            if ($isOrganisation) {
                $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
                $stmt->execute([':org' => $folder['owner']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->smallShare->updateLogin($row['aid'], $row['id'], $folder['aid'], $post['id'], $post['user'], $post['password'], $post['domain'], $post['note']??'', $post['identifier']);
                }
            } else {
                $this->smallShare->updateLogin($_SESSION['id'], $_SESSION['uuid'], $folder['aid'], $post['id'], $post['user'], $post['password'], $post['domain'], $post['note']??'', $post['identifier']);
            }
        } elseif (isset($post['content']) && isset($post['name'])) {
            if ($isOrganisation) {
                $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
                $stmt->execute([':org' => $folder['owner']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->smallShare->updateNote($row['aid'], $row['id'], $folder['aid'], $post['id'], $post['name'], $post['content'], $post['public']);
                }
            } else {
                $this->smallShare->updateNote($_SESSION['id'], $_SESSION['uuid'], $folder['aid'], $post['id'], $post['name'], $post['content'], $post['public']);
            }
        }
        header ('Location: /folder/' . $id, true, 303);
        return '';
    }
    public function get(string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT *, "Owner" as `role` FROM folders WHERE `owner`=:user AND id=:id AND `type`="Account"');
        $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $isOrganisation = false;
        if (!$folder) {
            $isOrganisation = true;
            $stmt = $this->database->prepare('SELECT folders.*,memberships.`role`
FROM folders
INNER JOIN memberships ON memberships.organisation=folders.owner
WHERE memberships.account=:user AND folders.id=:id AND folders.`type`="Organisation" AND memberships.role <> "Proposed"');
            $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$folder) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT public,id FROM logins WHERE account=:user AND folder=:id');
        $stmt->execute([':id' => $folder['aid'], ':user' => $_SESSION['id']]);
        $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT public,id FROM notes WHERE account=:user AND folder=:id');
        $stmt->execute([':id' => $folder['aid'], ':user' => $_SESSION['id']]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $organisations = [];
        if (!$isOrganisation) {
            $stmt = $this->database->prepare('SELECT name,id FROM organisations INNER JOIN memberships ON memberships.organisation=organisations.aid WHERE memberships.`account`=:user AND memberships.`role`<>"Proposed"');
            $stmt->execute([':user' => $_SESSION['id']]);
            $organisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->twig->render('folder', [
            'notes' => $notes,
            'logins' => $logins,
            'folder' => $folder,
            'title' => $folder['name'],
            'isOrganisation' => $isOrganisation,
            'organisations' => $organisations,
        ]);
    }
}
