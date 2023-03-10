<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\Mailer;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\PasswordGenerator;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Random;
use Ramsey\Uuid\Uuid;

class Socials
{
    private Twig $twig;
    private PDO $database;
    private Mailer $mailer;
    private ENV $env;
    private May2F $twoFactor;

    public function __construct(May2F $twoFactor, Twig $twig, PDO $database, Mailer $mailer, ENV $env)
    {
        $this->twoFactor = $twoFactor;
        $this->env = $env;
        $this->twig = $twig;
        $this->database = $database;
        $this->mailer = $mailer;
    }
    public function get(User $user): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT display,knowns.id,accounts.id as uid
FROM accounts
INNER JOIN knowns ON knowns.target=accounts.aid
WHERE knowns.owner=:id');
        $stmt->execute([':id' => $user->aid()]);
        $knowns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT *
FROM organisations
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE account=:id');
        $stmt->execute([':id' => $user->aid()]);
        $organisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->twig->render(
            'socials',
            [
                'title' => 'Home',
                'knowns' => $knowns,
                'organisations' => $organisations,
            ]
        );
    }
    private function addKnown(int $user, int $known, string $uuid, string $comment): void
    {
        $public = KeyLoader::public($uuid);
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        $this->database
            ->prepare(
                'INSERT INTO knowns (`owner`,target,note,iv,`key`,id) VALUES (:owner,:target,:comment,:iv,:key,:id)'
            )
            ->execute(
                [
                    ':comment' => $shared->encrypt($comment),
                    ':iv' => $public->encrypt($iv),
                    ':key' => $public->encrypt($key),
                    ':owner' => $user,
                    ':target' => $known,
                    ':id' => Uuid::uuid1()->toString(),
                ]
            );
    }
    public function post(User $user, array $post): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['auth'] ?? '', $user->aid())) {
            header('Location: /socials', true, 303);
            return '';
        }
        if (isset($post['email']) && isset($post['name'])) {
            $stmt = $this->database->prepare('SELECT aid FROM invites WHERE mail=:mail AND inviter=:id');
            $stmt->execute([':id' => $user->aid(), ':mail' => $post['email']]);
            if ($stmt->fetchColumn() !== false) {
                header('Location: /socials', true, 303);
                return '';
            }
            $id = PasswordGenerator::make();
            $uuid = Uuid::uuid1()->toString();
            $stmt = $this->database->prepare('SELECT aid FROM accounts WHERE mail=:mail');
            $stmt->execute([':mail' => $post['email']]);
            $reciever = intval($stmt->fetchColumn() ?: '0', 10);
            if ($reciever > 0) {
                $this->mailer->send(
                    'friend-request',
                    [
                        'password' => $id,
                        'uuid' => $uuid,
                        'name' => $post['name'],
                        'sender' => $user->display(),
                    ],
                    'Friend request at ' . $this->env->getString('SYSTEM_HOSTNAME'),
                    $post['email'],
                    $post['name']
                );
            } else {
                $this->mailer->send(
                    'invite',
                    [
                        'password' => $id,
                        'uuid' => $uuid,
                        'name' => $post['name'],
                        'sender' => $user->display(),
                    ],
                    'Invite to ' . $this->env->getString('SYSTEM_HOSTNAME'),
                    $post['email'],
                    $post['name']
                );
            }
            $this->database
                ->prepare('INSERT INTO invites (id,mail,secret,inviter) VALUES (:id,:mail,:secret,:inviter)')
                ->execute([
                    ':id' => $uuid,
                    ':mail' => $post['email'],
                    ':secret' => $id,
                    ':inviter' => $user->aid(),
                ]);
        } elseif (isset($post['id']) && isset($post['code'])) {
            $stmt = $this->database->prepare('SELECT aid,inviter
FROM invites
WHERE id=:id AND secret=:secret AND ISNULL(invitee)');
            $stmt->execute([':id' => $post['id'], ':secret' => $post['code']]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invite) {
                header('Location: /socials', true, 303);
                return '';
            }
            $this->database
                ->prepare('UPDATE invitations SET invitee=:invitee WHERE aid=:invite')
                ->execute([':id' => $invite['aid'], ':invitee' => $user->aid()]);
            $this->addKnown($user->aid(), $invite['inviter'], $user->id(), 'Was invited by them.');
            $stmt = $this->database->prepare('SELECT id FROM accounts WHERE aid=:aid');
            $stmt->execute([':aid' => $invite['inviter']]);
            $this->addKnown($invite['inviter'], $user->aid(), $stmt->fetchColumn(), 'Invited them.');
        } elseif (isset($post['organisation'])) {
                $this->database
                    ->prepare('INSERT INTO organisations (`name`,id) VALUES (:name,:uuid)')
                    ->execute([':name' => $post['organisation'], ':uuid' => Uuid::uuid1()->toString()]);
                $this->database
                    ->prepare(
                        'INSERT INTO memberships (organisation,account,role) VALUES (:organisation,:account,"Owner")'
                    )
                    ->execute([':organisation' => $this->database->lastInsertId(), ':account' => $user->aid()]);
        }
        header('Location: /socials', true, 303);
        return '';
    }
}
