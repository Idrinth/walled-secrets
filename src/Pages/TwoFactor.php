<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Twig;
use PDO;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactor
{
    private PDO $database;
    private Twig $twig;
    private Google2FA $twoFactor;
    private ENV $env;

    public function __construct(PDO $database, Twig $twig, Google2FA $twoFactor, ENV $env)
    {
        $this->database = $database;
        $this->twig = $twig;
        $this->twoFactor = $twoFactor;
        $this->env = $env;
    }

    public function get(): string
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /', true, 303);
            return '';
        }
        if ($this->env->getInt('2FA_SECRET_LENGTH') === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `2fa` FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $_SESSION['id']]);
        $twofactor = $stmt->fetchColumn();
        if (!$twofactor) {
            $_SESSION['2fakey'] = $this->twoFactor->generateSecretKey($this->env->getInt('2FA_SECRET_LENGTH'));
            return $this->twig->render('2fa-activation', [
                'title' => 'Activate 2FA',
                'source' => base64_encode($this->twoFactor->getQRCodeInline(
                    $this->env->getString('2FA_COMPANY_NAME'),
                    $this->env->getString('2FA_COMPANY_EMAIL'),
                    $_SESSION['2fakey']
                )),
            ]);
        }
        return $this->twig->render('2fa-deactivation', [
            'title' => 'Deactivate 2FA',
            'source' => base64_encode($this->twoFactor->getQRCodeInline(
                $this->env->getString('2FA_COMPANY_NAME'),
                $this->env->getString('2FA_COMPANY_EMAIL'),
                $twofactor
            )),
        ]);
    }
    public function post(array $post): string
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `2fa` FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $_SESSION['id']]);
        $twofactor = $stmt->fetchColumn();
        if (!$twofactor && isset($_SESSION['2fakey']) && isset($post['secret'])) {
            if ($this->twoFactor->verifyKey($_SESSION['2fakey'], $post['secret'], 0)) {
                $this->database
                    ->prepare('UPDATE accounts set `2fa`=:fa WHERE aid=:aid')
                    ->execute($_SESSION['id']);
            }
            unset($_SESSION['2fakey']);
        } elseif ($twofactor && isset($post['secret'])) {
            if ($this->twoFactor->verify($post['secret'], $twofactor, 0)) {
                $this->database
                    ->prepare('UPDATE accounts set `2fa`="" WHERE aid=:aid')
                    ->execute($_SESSION['id']);
            }
        }
        header('Location: /2fa', true, 303);
        return '';
    }
}
