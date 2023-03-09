<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\May2F;
use De\Idrinth\WalledSecrets\Twig;
use PDO;

class IP
{
    private Twig $twig;
    private PDO $database;
    private ENV $env;
    private May2F $twoFactor;

    public function __construct(Twig $twig, PDO $database, ENV $env, May2F $twoFactor)
    {
        $this->twig = $twig;
        $this->database = $database;
        $this->env = $env;
        $this->twoFactor = $twoFactor;
    }

    public function post($post): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        if (!$this->twoFactor->may($post['code'], $_SESSION['id'])) {
            header ('Location: /ip', true, 303);
            return '';
        }
        if (isset($post['ip'])) {
            $stmt = $this->database->prepare('UPDATE `accounts` SET `ip_blacklist`=:ipbl,`ip_whitelist`=:ipwl WHERE `aid`=:id');
            $stmt->bindValue(':id', $_SESSION['id']);
            $stmt->bindValue(':ipwl', $post['whitelist'] ?? '');
            $stmt->bindValue(':ipbl', $post['blacklist'] ?? '');
            $stmt->execute();
        }
        header ('Location: /ip', true, 303);
        return '';
    }

    public function get(): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT ip_whitelist,ip_blacklist FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $_SESSION['id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->twig->render('ip-settings', [
            'title' => 'IP-Settings',
            'server' => [
                'asn' => $this->env->getString('IP_ASN_BLACKLIST'),
                'whitelist' => $this->env->getString('IP_WHITELIST'),
                'blacklist' => $this->env->getString('IP_BLACKLIST_SET'),
            ],
            'account' => [
                'whitelist' => $account['ip_whitelist'],
                'blacklist' => $account['ip_blacklist'],
            ],
        ]);
    }
}
