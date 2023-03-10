<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;

class IP
{
    private Twig $twig;
    private PDO $database;
    private ENV $env;
    private May2F $twoFactor;
    private Audit $audit;

    public function __construct(Audit $audit, Twig $twig, PDO $database, ENV $env, May2F $twoFactor)
    {
        $this->audit = $audit;
        $this->twig = $twig;
        $this->database = $database;
        $this->env = $env;
        $this->twoFactor = $twoFactor;
    }

    public function post(User $user, array $post): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code'], $user->aid())) {
            header('Location: /ip', true, 303);
            return '';
        }
        if (isset($post['ip'])) {
            $this->audit->log('ip', 'modify', $user->aid(), null, $user->id());
            $stmt = $this->database->prepare(
                'UPDATE `accounts` SET `ip_blacklist`=:ipbl,`ip_whitelist`=:ipwl WHERE `aid`=:id'
            );
            $stmt->bindValue(':id', $user->aid());
            $stmt->bindValue(':ipwl', $post['whitelist'] ?? '');
            $stmt->bindValue(':ipbl', $post['blacklist'] ?? '');
            $stmt->execute();
        }
        header('Location: /ip', true, 303);
        return '';
    }

    public function get(User $user): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $this->audit->log('ip', 'read', $user->aid(), null, $user->id());
        return $this->twig->render(
            'ip-settings',
            [
                'title' => 'IP-Settings',
                'server' => [
                    'asn' => $this->env->getString('IP_ASN_BLACKLIST'),
                    'whitelist' => $this->env->getString('IP_WHITELIST'),
                    'blacklist' => $this->env->getString('IP_BLACKLIST_SET'),
                ],
                'account' => [
                    'whitelist' => $user->ipWhitelist(),
                    'blacklist' => $user->ipBlacklist(),
                ],
            ]
        );
    }
}
