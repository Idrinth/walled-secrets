<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\KeyLoader;
use De\Idrinth\WalledSecrets\Services\Mailer;
use De\Idrinth\WalledSecrets\Services\MasterPassword;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\PasswordGenerator;
use De\Idrinth\WalledSecrets\Services\Twig;
use Exception;
use PDO;
use Ramsey\Uuid\Uuid;

class Home
{
    private Twig $twig;
    private PDO $database;
    private Mailer $mailer;
    private ENV $env;
    private May2F $twoFactor;
    private Audit $audit;
    private MasterPassword $master;

    public function __construct(
        Audit $audit,
        May2F $twoFactor,
        Twig $twig,
        PDO $database,
        Mailer $mailer,
        ENV $env,
        MasterPassword $master
    ) {
        $this->master = $master;
        $this->audit = $audit;
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
        $stmt = $this->database->prepare('SELECT * FROM folders
WHERE (`owner`=:id AND `type`="Account")
OR (`type`="Organisation" AND `owner` IN (
SELECT organisation FROM memberships WHERE `role`<>"Proposed" AND `account`=:id
))');
        $stmt->execute([':id' => $user->aid()]);
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $organisations = [];
        $stmt = $this->database->prepare('SELECT organisations.aid,organisations.name
FROM organisations
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE memberships.`role`<>"Proposed" AND memberships.`account`=:id');
        $stmt->execute([':id' => $user->aid()]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $org) {
            $organisations[$org['aid']] = $org['name'];
        }
        return $this->twig->render(
            'home',
            [
                'title' => 'Home',
                'user' => $user,
                'folders' => $folders,
                'organisations' => $organisations,
            ]
        );
    }
    public function post(User $user, array $post): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code'] ?? '', $user->aid())) {
            header('Location: /home', true, 303);
            return '';
        }
        if (isset($post['haveibeenpwned'])) {
            $stmt = $this->database
                ->prepare('UPDATE `accounts` SET `haveibeenpwned`=:haveibeenpwned WHERE `aid`=:id');
            $stmt->bindValue(':id', $user->aid());
            $stmt->bindValue(':haveibeenpwned', $post['haveibeenpwned']);
            $stmt->execute();
            $this->audit->log('account', 'modify', $user->aid(), null, $user->id());
        } elseif (isset($post['regenerate'])) {
            $stmt = $this->database
                ->prepare('UPDATE `accounts` SET `apikey`=:ak WHERE `aid`=:id');
            $stmt->bindValue(':id', $user->aid());
            $stmt->bindValue(':ak', PasswordGenerator::make());
            $stmt->execute();
            $this->audit->log('account', 'modify', $user->aid(), null, $user->id());
        } elseif (isset($post['folder'])) {
            $stmt = $this->database->prepare('INSERT INTO folders (`name`,`owner`,id) VALUES (:name, :owner,:id)');
            $stmt->bindValue(':name', $post['folder']);
            $stmt->bindValue(':owner', $user->aid());
            $folder = Uuid::uuid1()->toString();
            $stmt->bindValue(':id', $folder);
            $stmt->execute();
            $this->audit->log('folder', 'create', $user->aid(), null, $folder);
        } elseif (isset($post['default'])) {
            $this->database
                ->prepare('UPDATE folders SET `default`=0 WHERE `owner`=:owner')
                ->execute([':owner' => $user->aid()]);
            $this->database
                ->prepare('UPDATE folders SET `default`=1 WHERE `type`="Account" AND `owner`=:owner AND id=:id')
                ->execute([':owner' => $user->aid(), ':id' => $post['default']]);
            $this->audit->log('folder', 'modify', $user->aid(), null, $post['default']);
        } elseif (isset($post['old-password']) && isset($post['new-password']) && isset($post['repeat-password'])) {
            if ($post['old-password'] === $this->master->get() && $post['new-password'] === $post['repeat-password']) {
                if (strlen($post['new-password']) >= $this->env->getInt('SYSTEM_MIN_PASSWORD_LENGTH')) {
                    try {
                        $key = KeyLoader::private($user->id(), $post['old-password']);
                        $password = Uuid::uuid1();
                        $allow = PasswordGenerator::make();
                        $deny = PasswordGenerator::make();
                        $this->database
                            ->prepare(
                                'INSERT INTO master (`id`,`user`,`deny`,`confirm`,`private`)
VALUES (:id,:user,:deny,:confirm,:private)'
                            )
                            ->execute([
                                 ':id' => $password,
                                 ':user' => $user->id(),
                                 ':deny' => $deny,
                                 ':confirm' => $allow,
                                 ':private' => $key->withPassword($post['new-password'])->toString('PKCS1')
                            ]);
                        $this->mailer->send(
                            'password-change',
                            [
                                'uuid' => $password,
                                'allow' => $allow,
                                'deny' => $deny,
                                'name' => $user->display(),
                                'user' => $user->id()
                            ],
                            'Password Change at ' . $this->env->getString('SYSTEM_HOSTNAME'),
                            $user->mail(),
                            $user->display()
                        );
                        $this->audit->log('account', 'modify', $user->aid(), null, $user->id());
                    } catch (Exception $ex) {
                        // nothing to do yet?
                    }
                }
            }
        }
        header('Location: /home', true, 303);
        return '';
    }
}
