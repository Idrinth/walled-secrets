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

    public function __construct(May2F $twoFactor, PDO $database, Twig $twig, AES $aes, Blowfish $blowfish, ENV $env, ShareWithOrganisation $share)
    {
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
        $login = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$login) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `type`,`owner` FROM folders WHERE aid=:aid');
        $stmt->execute([':aid' => $login['folder']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $mayEdit = true;
        $isOrganisation = false;
        if ($folder === 'Organisation') {
            $stmt = $this->database->prepare('SELECT `role` FROM memberships WHERE organisation=:org AND `account`=:owner');
            $stmt->execute([':org' => $folder['owner'], ':owner' => $_SESSION['id']]);
            $role = $stmt->fetchColumn();
            $mayEdit = in_array($role, ['Administrator', 'Owner', 'Member'], true);
            $isOrganisation = true;
        }
        if (!$mayEdit) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code']??'', $_SESSION['id'], $isOrganisation ? $folder['owner'] : 0)) {
            header ('Location: /logins/' . $id, true, 303);
            return '';            
        }
        if (isset($post['delete'])) {
            $this->database
                ->prepare('DELETE FROM notes WHERE id=:id')
                ->execute([':id' => $id]);
            header ('Location: /', true, 303);
            return '';
        }
        if ($folder === 'Organisation') {
            $stmt = $this->database->prepare('SELECT `aid`,`id` FROM `memberships` INNER JOIN accounts ON memberships.`account`=accounts.aid WHERE organisation=:org AND `role`<>"Proposed"');
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $this->share->updateLogin($user['aid'], $user['id'], $login['folder'], $id, $post['login'], $post['password'], $post['domain'], $post['note'], $post['identifier']);
            }
            header ('Location: /', true, 303);
            return '';
        }
        $this->share->updateLogin($_SESSION['id'], $_SESSION['uuid'], $login['folder'], $id, $post['login'], $post['password'], $post['domain'], $post['note'], $post['identifier']);
        header ('Location: /', true, 303);
        return '';
    }

    public function get(string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header ('Location: /', true, 303);
            return '';            
        }
        $stmt = $this->database->prepare('SELECT * FROM notes WHERE id=:id AND `account`=:account');
        $stmt->execute([':id' => $id, ':account' => $_SESSION['id']]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$note) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `type`,`owner` FROM folders WHERE aid=:aid');
        $stmt->execute([':aid' => $note['folder']]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $maySee = true;
        if ($folder === 'Organisation') {
            $stmt = $this->database->prepare('SELECT `role` FROM memberships WHERE organisation=:org AND `account`=:owner');
            $stmt->execute([':org' => $folder['owner'], ':owner' => $_SESSION['id']]);
            $role = $stmt->fetchColumn();
            $maySee = in_array($role, ['Administrator', 'Owner', 'Member', 'Reader'], true);
        }
        if (!$maySee) {
            header ('Location: /', true, 303);
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
            $note['content'] = $shared->decrypt($note['content']);
        }
        $knowns = [];
        if ($folder === 'Account') {
            $stmt = $this->database->prepare('SELECT target FROM knowns WHERE owner=:id');
            $stmt->execute([':id' => $_SESSION['id']]);
            $knowns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->twig->render('note', [
            'note' => $note,
            'title' => $note['public'],
            'knows' => $knowns,
        ]);
    }
}
