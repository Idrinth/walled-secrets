<?php

namespace De\Idrinth\WalledSecrets\Pages;

use Curl\Curl;
use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\MasterPassword;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\SecretHandler;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;
use phpseclib3\Crypt\AES;

class Logins
{
    private PDO $database;
    private Twig $twig;
    private MasterPassword $master;
    private ENV $env;
    private SecretHandler $share;
    private May2F $twoFactor;
    private Audit $audit;

    public function __construct(
        Audit $audit,
        May2F $twoFactor,
        PDO $database,
        Twig $twig,
        MasterPassword $master,
        ENV $env,
        SecretHandler $share
    ) {
        $this->master = $master;
        $this->audit = $audit;
        $this->twoFactor = $twoFactor;
        $this->database = $database;
        $this->twig = $twig;
        $this->env = $env;
        $this->share = $share;
    }

    public function post(User $user, array $post, string $id): string
    {
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE id=:id AND `account`=:account');
        $stmt->execute([':id' => $id, ':account' => $user->aid()]);
        $login = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$login) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `type`,`owner` FROM folders WHERE aid=:aid');
        $stmt->execute([':aid' => $login['folder']]);
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
            header('Location: /logins/' . $id, true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code'] ?? '', $user->aid(), $isOrganisation ? $folder['owner'] : 0)) {
            header('Location: /logins/' . $id, true, 303);
            return '';
        }
        if (isset($post['delete'])) {
            $this->database
                ->prepare('DELETE FROM logins WHERE id=:id')
                ->execute([':id' => $id]);
            $this->database
                ->prepare('UPDATE folders SET modified=NOW() WHERE id=:id')
                ->execute([':id' => $login['folder']]);
            $this->audit->log('login', 'delete', $user->aid(), $isOrganisation ? $folder['owner'] : null, $id);
            header('Location: /', true, 303);
            return '';
        }
        if (isset($post['organisation']) && !$isOrganisation) {
            $isOrganisation = true;
            list($org, $fid) = explode(':', $post['organisation']);
            $stmt = $this->database->prepare('SELECT aid,`type`,`owner` FROM folders WHERE id=:id');
            $stmt->execute([':id' => $fid]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
            $login['folder'] = $folder['aid'];
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
                header('Location: /logins/' . $id, true, 303);
                return '';
            }
            set_time_limit(0);
            $private = KeyLoader::private($user->id(), $this->master->get());
            $post['identifier'] = $login['public'];
            $post['user'] = $private->decrypt($login['login']);
            $post['password'] = $private->decrypt($login['pass']);
            if ($login['note']) {
                $login['iv'] = $private->decrypt($login['iv']);
                $login['key'] = $private->decrypt($login['key']);
                $shared = new AES('ctr');
                $shared->setIV($login['iv']);
                $shared->setKeyLength(256);
                $shared->setKey($login['key']);
                $post['note'] = $shared->decrypt($login['note']);
            }
            $this->audit->log('login', 'create', $user->aid(), $organisation, $id);
            $this->database
                ->prepare('UPDATE logins SET folder=:new WHERE id=:id AND `account`=:user')
                ->execute([':new' => $folder['aid'], ':id' => $id, ':user' => $user->aid()]);
        }
        if ($isOrganisation) {
            $stmt = $this->database->prepare('SELECT `aid`,`id`
FROM `memberships`
INNER JOIN accounts ON memberships.`account`=accounts.aid
WHERE organisation=:org AND `role`<>"Proposed"');
            $stmt->execute([':org' => $folder['owner']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $this->share->updateLogin(
                    $user['aid'],
                    $user['id'],
                    $login['folder'],
                    $id,
                    $post['user'],
                    $post['password'],
                    $post['note'] ?? '',
                    $post['identifier']
                );
            }
            $this->audit->log('login', 'modify', $user->aid(), $folder['owner'], $id);
            header('Location: /logins/' . $id, true, 303);
            return '';
        }
        $this->audit->log('login', 'modify', $user->aid(), null, $id);
        $this->share->updateLogin(
            $user->aid(),
            $user->id(),
            $login['folder'],
            $id,
            $post['user'],
            $post['password'],
            $post['note'] ?? '',
            $post['identifier']
        );
        header('Location: /logins/' . $id, true, 303);
        return '';
    }
    private function pwned(User $user, string $id, string $login): int
    {
        if (!$this->env->getString('HAVEIBEENPWNED_API_KEY')) {
            return 0;
        }
        if (!$user->haveibeenpwned()) {
            return 0;
        }
        $stmt = $this->database->prepare('SELECT checked,pwned FROM waspwned WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data && $data['pwned'] === '1') {
            return 1;
        } elseif (!$data || strtotime($data['checked']) < time() - 3600) {
            $curl = new Curl();
            $curl->setHeader('hibp-api-key', $this->env->getString('HAVEIBEENPWNED_API_KEY'));
            $curl->setUserAgent('idrinth/walled-secrets@' . $this->env->getString('SYSTEM_HOSTNAME'));
            $curl->get('https://haveibeenpwned.com/api/v3/breachedaccount/' . urlencode($login));
            if ($curl->httpStatusCode === 200) {
                $this->database
                    ->prepare('INSERT INTO waspwned (id,pwned) VALUES (:id,1) ON DUPLICATE KEY UPDATE pwned=1')
                    ->execute([':id' => $id]);
            } elseif ($curl->httpStatusCode === 429) {
                error_log('Rate Limit exceeded.');
            }
            $this->database
                ->prepare('INSERT INTO waspwned (id,checked) VALUES (:id,Now()) ON DUPLICATE KEY UPDATE checked=Now()')
                ->execute([':id' => $id]);
            return $curl->httpStatusCode === 200 ? 1 : 0;
        }
        return 0;
    }
    public function get(User $user, string $id): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        if (!isset($_SESSION['password'])) {
            session_destroy();
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE id=:id AND `account`=:account');
        $stmt->execute([':id' => $id, ':account' => $user->aid()]);
        $login = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$login) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `type`,`owner` FROM folders WHERE aid=:aid');
        $stmt->execute([':aid' => $login['folder']]);
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
        $this->audit->log('login', 'read', $user->aid(), $isOrganisation ? $folder['owner'] : null, $id);
        set_time_limit(0);
        $private = KeyLoader::private($user->id(), $this->master->get());
        $login['login'] = $private->decrypt($login['login']);
        $login['pass'] = $private->decrypt($login['pass']);
        if ($login['note']) {
            $login['iv'] = $private->decrypt($login['iv']);
            $login['key'] = $private->decrypt($login['key']);
            $shared = new AES('ctr');
            $shared->setIV($login['iv']);
            $shared->setKeyLength(256);
            $shared->setKey($login['key']);
            $login['note'] = $shared->decrypt($login['note']);
        }
        $login['pwned'] = $this->pwned($user, $id, $login['login']);
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
            'login',
            [
            'title' => $login['public'],
            'login' => $login,
            'organisations' => $organisations,
            ]
        );
    }
}
