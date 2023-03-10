<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\Mailer;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\PasswordGenerator;
use De\Idrinth\WalledSecrets\Twig;
use Exception;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use Ramsey\Uuid\Uuid;

class Home
{
    private Twig $twig;
    private PDO $database;
    private Mailer $mailer;
    private AES $aes;
    private Blowfish $blowfish;
    private ENV $env;
    private May2F $twoFactor;
    private Audit $audit;

    public function __construct(Audit $audit, May2F $twoFactor, Twig $twig, PDO $database, Mailer $mailer, AES $aes, Blowfish $blowfish, ENV $env)
    {
        $this->audit = $audit;
        $this->twoFactor = $twoFactor;
        $this->env = $env;
        $this->blowfish = $blowfish;
        $this->twig = $twig;
        $this->database = $database;
        $this->mailer = $mailer;
        $this->aes = $aes;
        $this->aes->setKeyLength(256);
        $this->aes->setKey($this->env->getString('PASSWORD_KEY'));
        $this->aes->setIV($this->env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($this->env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($this->env->getString('PASSWORD_BLOWFISH_IV'));
    }
    public function get(): string
    {
        if (isset($_SESSION['id'])) {
            $stmt = $this->database->prepare('SELECT * FROM accounts WHERE aid=:id');
            $stmt->execute([':id' => $_SESSION['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->database->prepare('SELECT * FROM folders WHERE (`owner`=:id AND `type`="Account") OR (`type`="Organisation" AND `owner` IN (SELECT organisation FROM memberships WHERE `role`<>"Proposed" AND `account`=:id))');
            $stmt->execute([':id' => $_SESSION['id']]);
            $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $organisations = [];
            $stmt = $this->database->prepare('SELECT organisations.aid,organisations.name FROM organisations INNER JOIN memberships ON memberships.organisation=organisations.aid WHERE memberships.`role`<>"Proposed" AND memberships.`account`=:id');
            $stmt->execute([':id' => $user['aid']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $org) {
                $organisations[$org['aid']] = $org['name'];
            }
            return $this->twig->render(
                'home-user',
                [
                'title' => 'Home',
                'user' => $user,
                'folders' => $folders,
                'organisations' => $organisations,
                ]
            );
        }
        if (isset($_COOKIE[$this->env->getString('SYSTEM_QUICK_LOGIN_COOKIE')])) {
            header('Location: /master', true, 303);
            return '';
        }
        return $this->twig->render(
            'home-anon',
            [
            'title' => 'Login',
            'disableRefresh' => true
            ]
        );
    }
    public function post(array $post): string
    {
        if (isset($_SESSION['id'])) {
            if (!$this->twoFactor->may($post['code'] ?? '', $_SESSION['id'])) {
                header('Location: /', true, 303);
                return '';
            }
            if (isset($post['haveibeenpwned'])) {
                $stmt = $this->database
                    ->prepare('UPDATE `accounts` SET `haveibeenpwned`=:haveibeenpwned WHERE `aid`=:id');
                $stmt->bindValue(':id', $_SESSION['id']);
                $stmt->bindValue(':haveibeenpwned', $post['haveibeenpwned']);
                $stmt->execute();
                $this->audit->log('account', 'modify', $_SESSION['id'], null, $_SESSION['uuid']);
            } elseif (isset($post['regenerate'])) {
                $stmt = $this->database
                    ->prepare('UPDATE `accounts` SET `apikey`=:ak WHERE `aid`=:id');
                $stmt->bindValue(':id', $_SESSION['id']);
                $stmt->bindValue(':ak', PasswordGenerator::make());
                $stmt->execute();
                $this->audit->log('account', 'modify', $_SESSION['id'], null, $_SESSION['uuid']);
            } elseif (isset($post['folder'])) {
                $stmt = $this->database->prepare('INSERT INTO folders (`name`,`owner`,id) VALUES (:name, :owner,:id)');
                $stmt->bindValue(':name', $post['folder']);
                $stmt->bindValue(':owner', $_SESSION['id']);
                $folder = Uuid::uuid1()->toString();
                $stmt->bindValue(':id', $folder);
                $stmt->execute();
                $this->audit->log('folder', 'create', $_SESSION['id'], null, $folder);
            } elseif (isset($post['default'])) {
                $this->database
                    ->prepare('UPDATE folders SET `default`=0 WHERE `owner`=:owner')
                    ->execute([':owner' => $_SESSION['id']]);
                $this->database
                    ->prepare('UPDATE folders SET `default`=1 WHERE `type`="Account" AND `owner`=:owner AND id=:id')
                    ->execute([':owner' => $_SESSION['id'], ':id' => $post['default']]);
                $this->audit->log('folder', 'modify', $_SESSION['id'], null, $post['default']);
            }
            header('Location: /', true, 303);
            return '';
        }
        if (!isset($post['email']) || !isset($post['password'])) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT id, display, since, aid FROM accounts WHERE mail=:mail');
        $stmt->execute([':mail' => $post['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            header('Location: /', true, 303);
            return '';
        }
        try {
            KeyLoader::private($user['id'], $post['password']);
        } catch (Exception $ex) {
            header('Location: /', true, 303);
            return '';
        }
        if (strtotime($user['since'] ?? 'now') < time() - $this->env->getInt('SYSTEM_SESSION_DURATION')) {
            $id = PasswordGenerator::make();
            $_SESSION['password'] = $this->blowfish->encrypt($this->aes->encrypt($post['password']));
            $this->mailer->send(
                $user['aid'],
                'login',
                ['password' => $id, 'uuid' => $user['id'], 'name' => $user['display']],
                'Login Request at ' . $this->env->getString('SYSTEM_HOSTNAME'),
                $post['email'],
                $user['display']
            );
            $this->audit->log('login', 'create', $user['aid'], null, $user['id']);
            $this->database
                ->prepare('UPDATE accounts SET since=NOW(),identifier=:id WHERE aid=:aid')
                ->execute([':id' => $id, ':aid' => $user['aid']]);
        }
        header('Location: /mailed', true, 303);
        return '';
    }
}
