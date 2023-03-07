<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\Mailer;
use De\Idrinth\WalledSecrets\Services\PasswordGenerator;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use Ramsey\Uuid\Uuid;

class Socials
{
    private Twig $twig;
    private PDO $database;
    private Mailer $mailer;
    private ENV $env;

    public function __construct(Twig $twig, PDO $database, Mailer $mailer, ENV $env)
    {
        $this->env = $env;
        $this->twig = $twig;
        $this->database = $database;
        $this->mailer = $mailer;
    }
    public function get(): string
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt->execute([':id' => $_SESSION['id']]);
        $knowns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->twig->render('socials', [
            'title' => 'Home',
            'knowns' => $knowns,
        ]);
    }
    public function post(array $post): string
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /', true, 303);
            return '';
        }
        if (isset($post['email']) && isset($post['name'])) {
            $stmt = $this->database->prepare('SELECT aid FROM invites WHERE mail=:mail AND inviter=:id');
            $stmt->execute([':id' => $_SESSION['id'], ':mail' => $post['email']]);
            if ($stmt->fetchColumn() !== false) {
                header('Location: /socials', true, 303);
                return '';
            }
            $id = PasswordGenerator::make();
            $uuid = Uuid::uuid1()->toString();
            $stmt = $this->database->prepare('SELECT display FROM accounts WHERE aid=:id');
            $stmt->execute([':id' => $_SESSION['id']]);
            $sender = $stmt->fetchColumn();
            $stmt = $this->database->prepare('SELECT aid FROM accounts WHERE mail=:mail');
            $stmt->execute([':mail' => $post['email']]);
            $reciever = intval($stmt->fetchColumn() ?: '0', 10);
            if ($reciever > 0) {
                $this->mailer->send(
                    $reciever,
                    'friend-request',
                    [
                        'hostname' => $this->env->getString('SYSTEM_HOSTNAME'),
                        'password' => $id,
                        'uuid' => $uuid,
                        'name' => $post['name'],
                        'sender' => $sender,
                    ],
                    'Friend request at ' . $this->env->getString('SYSTEM_HOSTNAME'),
                    $post['email'],
                    $post['name']
                );
            } else {
                $this->mailer->send(
                    0,
                    'invite',
                    [
                        'hostname' => $this->env->getString('SYSTEM_HOSTNAME'),
                        'password' => $id,
                        'uuid' => $uuid,
                        'name' => $post['name'],
                        'sender' => $sender,
                    ],
                    'Invite to ' . $this->env->getString('SYSTEM_HOSTNAME'),
                    $post['email'],
                    $post['name']
                );
            }
            $this->database
                ->prepare('INSERT INTO invites (id,mail,secret,inviter) VALUES (:id,:mail,:secret,:inviter)')
                ->execute([':id' => $uuid, ':mail' => $post['email'], ':secret' => $id, ':inviter' => $_SESSION['id']]);
        } elseif (isset($post['id']) && isset($post['code'])) {
            
        }
        header('Location: /socials', true, 303);
        return '';
    }
}
