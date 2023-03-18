<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\Mailer;
use De\Idrinth\WalledSecrets\Services\MasterPassword;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\SecretHandler;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Random;
use Ramsey\Uuid\Uuid;

class Knowns
{
    private PDO $database;
    private Twig $twig;
    private ENV $env;
    private SecretHandler $share;
    private Mailer $mailer;
    private May2F $twoFactor;
    private Audit $audit;
    private MasterPassword $master;

    public function __construct(
        Audit $audit,
        May2F $twoFactor,
        Mailer $mailer,
        PDO $database,
        Twig $twig,
        ENV $env,
        SecretHandler $share,
        MasterPassword $master
    ) {
        $this->master = $master;
        $this->audit = $audit;
        $this->twoFactor = $twoFactor;
        $this->mailer = $mailer;
        $this->database = $database;
        $this->twig = $twig;
        $this->env = $env;
        $this->share = $share;
    }

    public function post(User $user, array $post, string $id): string
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
        $stmt = $this->database->prepare(
            'SELECT knowns.*
FROM knowns
WHERE knowns.id=:id AND knowns.`owner`=:account'
        );
        $stmt->execute([':id' => $id, ':account' => $user->aid()]);
        $known = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$known) {
            header('Location: /socials', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code'] ?? '', $user->aid())) {
            header('Location: /knowns/' . $id, true, 303);
            return '';
        }
        if (isset($post['identifier']) && isset($post['user']) && isset($post['password'])) {
            $stmt = $this->database->prepare(
                'SELECT accounts.id,accounts.aid,folders.aid as folder
FROM accounts
INNER JOIN knowns ON knowns.target=accounts.aid
INNER JOIN folders ON knowns.target=folders.`owner` AND folders.`default` AND folders.`type`="Account"
WHERE knowns.`owner`=:owner AND knowns.id=:id'
            );
            $stmt->execute([':id' => $id, ':owner' => $user->aid()]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $login = Uuid::uuid1()->toString();
            $this->share->updateLogin(
                $data['aid'],
                $data['id'],
                $data['folder'],
                $login,
                $post['user'],
                $post['password'],
                $post['note'] ?? '',
                $post['identifier']
            );
            $this->audit->log('login', 'create', $user->aid(), null, $login);
            $this->mailer->send(
                'new-login',
                [
                    'public' => $post['identifier'],
                    'sender' => $user->display(),
                    'id' => $login,
                ],
                'Login added at ' . $this->env->getString('SYSTEM_HOSTNAME'),
                $data['email'],
                $data['display']
            );
            header('Location: /socials', true, 303);
            return '';
        } elseif (isset($post['content']) && isset($post['public'])) {
            $stmt = $this->database->prepare(
                'SELECT accounts.display,accounts.mail,accounts.id,accounts.aid,folders.aid as folder
FROM accounts
INNER JOIN knowns ON knowns.target=accounts.aid
INNER JOIN folders ON knowns.target=folders.`owner` AND folders.`default` AND folders.`type`="Account"
WHERE knowns.`owner`=:owner AND knowns.id=:id'
            );
            $stmt->execute([':id' => $id, ':owner' => $user->aid()]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $note = Uuid::uuid1()->toString();
            $this->share->updateNote(
                $data['aid'],
                $data['id'],
                $data['folder'],
                $note,
                $post['content'],
                $post['public']
            );
            $this->audit->log('note', 'create', $user->aid(), null, $note);
            $this->mailer->send(
                'new-note',
                [
                    'public' => $post['public'],
                    'sender' => $user->display(),
                    'id' => $note,
                ],
                'Note added at ' . $this->env->getString('SYSTEM_HOSTNAME'),
                $data['email'],
                $data['display']
            );
            header('Location: /socials', true, 303);
            return '';
        }
        if (!isset($post['note'])) {
            header('Location: /knowns/' . $id, true, 303);
            return '';
        }
        $this->audit->log('known', 'modify', $user->aid(), null, $id);
        $public = KeyLoader::public($user->id());
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare('UPDATE knowns SET note=:note, iv=:iv, `key`=:key WHERE id=:id AND `owner`=:owner')
            ->execute([
                ':owner' => $user->aid(),
                ':id' => $id,
                ':key' => $public->encrypt($key),
                ':iv' => $public->encrypt($iv),
                ':note' => $shared->encrypt($post['note']),
            ]);
        header('Location: /knowns/' . $id, true, 303);
        return '';
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
        $stmt = $this->database->prepare(
            'SELECT knowns.*,accounts.display
FROM knowns
INNER JOIN accounts ON accounts.aid = knowns.target
WHERE knowns.id=:id AND knowns.`owner`=:account'
        );
        $stmt->execute([':id' => $id, ':account' => $user->aid()]);
        $known = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$known) {
            header('Location: /', true, 303);
            return '';
        }
        $this->audit->log('known', 'read', $user->aid(), null, $id);
        set_time_limit(0);
        $private = KeyLoader::private($user->id(), $this->master->get());
        if ($known['note']) {
            $known['iv'] = $private->decrypt($known['iv']);
            $known['key'] = $private->decrypt($known['key']);
            $shared = new AES('ctr');
            $shared->setIV($known['iv']);
            $shared->setKeyLength(256);
            $shared->setKey($known['key']);
            $known['note'] = $shared->decrypt($known['note']);
        }
        return $this->twig->render(
            'known',
            [
                'known' => $known,
                'title' => $known['display']
            ]
        );
    }
}
