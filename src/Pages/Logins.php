<?php

namespace De\Idrinth\WalledSecrets\Pages;

use Curl\Curl;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\ShareWithOrganisation;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;

class Logins
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
        $this->aes->setKeyLength(256);
        $this->aes->setKey($this->env->getString('PASSWORD_KEY'));
        $this->aes->setIV($this->env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($this->env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($this->env->getString('PASSWORD_BLOWFISH_IV'));
        $this->share = $share;
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
            header ('Location: /logins/' . $id, true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code']??'', $_SESSION['id'], $isOrganisation ? $folder['owner'] : 0)) {
            header ('Location: /logins/' . $id, true, 303);
            return '';            
        }
        if (isset($post['delete'])) {
            $this->database
                ->prepare('DELETE FROM logins WHERE id=:id')
                ->execute([':id' => $id]);
            header ('Location: /', true, 303);
            return '';
        }
        if (isset($post['organisation']) && !$isOrganisation) {
            $isOrganisation = true;
            list($org, $fid) = explode(':', $post['organisation']);
            $stmt = $this->database->prepare('SELECT aid,`type`,`owner` FROM folders WHERE id=:id');
            $stmt->execute([':id' => $fid]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
            $login['folder'] = $folder['aid'];
            $stmt = $this->database->prepare('SELECT organisations.aid
FROM organisations
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE organisations.id=:id AND memberships.`account`=:user AND memberships.`role` IN ("Owner"."Administrator","Member")');
            $stmt->execute([':id' => $org, ':user' => $_SESSION['id']]);
            $organisation = $stmt->fetchColumn();
            if (!$organisation || $organisation !== $folder['owner']) {
                header ('Location: /logins/' . $id, true, 303);
                return '';
            }
        }
        if ($isOrganisation) {
            $stmt = $this->database->prepare('SELECT `aid`,`id` FROM `memberships` INNER JOIN accounts ON memberships.`account`=accounts.aid WHERE organisation=:org AND `role`<>"Proposed"');
            $stmt->execute([':org' => $folder['owner']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $this->share->updateLogin($user['aid'], $user['id'], $login['folder'], $id, $post['user'], $post['password'], $post['domain'], $post['note']??'', $post['identifier']);
            }
            header ('Location: /logins/' . $id, true, 303);
            return '';
        }
        $this->share->updateLogin($_SESSION['id'], $_SESSION['uuid'], $login['folder'], $id, $post['user'], $post['password'], $post['domain'], $post['note']??'', $post['identifier']);
        header ('Location: /logins/' . $id, true, 303);
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
        $maySee = true;
        $isOrganisation = false;
        if ($folder === 'Organisation') {
            $stmt = $this->database->prepare('SELECT `role` FROM memberships WHERE organisation=:org AND `account`=:owner');
            $stmt->execute([':org' => $folder['owner'], ':owner' => $_SESSION['id']]);
            $role = $stmt->fetchColumn();
            $maySee = in_array($role, ['Administrator', 'Owner', 'Member', 'Reader'], true);
            $isOrganisation = true;
        }
        if (!$maySee) {
            header ('Location: /', true, 303);
            return '';
        }
        set_time_limit(0);
        $master = $this->aes->decrypt($this->blowfish->decrypt($_SESSION['password']));
        $private = KeyLoader::private($_SESSION['uuid'], $master);
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
        $login['pwned'] = 0;
        if ($this->env->getString('HAVEIBEENPWNED_API_KEY')) {
            $stmt = $this->database->prepare('SELECT haveibeenpwned FROM accounts WHERE aid=:id');
            $stmt->execute([':id' => $_SESSION['id']]);
            $haveibeenpwned = $stmt->fetchColumn() === '1';
            if ($haveibeenpwned) {
                $stmt = $this->database->prepare('SELECT checked,pwned FROM waspwned WHERE id=:id');
                $stmt->execute([':id' => $id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($data && $data['pwned'] === '1') {
                    $login['pwned'] = 1;
                } elseif (!$data || strtotime($data['checked']) < time() - 3600) {
                    $curl = new Curl();
                    $curl->setHeader('hibp-api-key', $this->env->getString('HAVEIBEENPWNED_API_KEY'));
                    $curl->setUserAgent('idrinth/walled-secrets@' . $this->env->getString('SYSTEM_HOSTNAME'));
                    $curl->get('https://haveibeenpwned.com/api/v3/breachedaccount/' . urlencode($login['login']));
                    if ($curl->httpStatusCode===200) {
                        $login['pwned'] = true;
                        $this->database
                            ->prepare('INSERT INTO waspwned (id,pwned) VALUES (:id,1) ON DUPLICATE KEY UPDATE pwned=1')
                            ->execute([':id' => $id]);
                    } elseif ($curl->httpStatusCode===429) {
                        error_log('Rate Limit exceeded.');
                    }
                    $this->database
                        ->prepare('INSERT INTO waspwned (id,checked) VALUES (:id,Now()) ON DUPLICATE KEY UPDATE checked=Now()')
                        ->execute([':id' => $id]);
                }
            }
        }
        $organisations = [];
        if (!$isOrganisation) {
            $stmt = $this->database->prepare('SELECT folders.id AS folder,folders.`name` AS folderName, organisations.`name`,organisations.id
FROM organisations
INNER JOIN folders ON organisations.aid=folders.`owner` AND folders.`type`="Organisation"
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE memberships.`account`=:id AND memberships.`role` NOT IN ("Reader","Proposed")');
            $stmt->execute([':id' => $_SESSION['id']]);
            $organisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->twig->render('login', [
            'title' => $login['public'],
            'login' => $login,
            'organisations' => $organisations,
        ]);
    }
}
