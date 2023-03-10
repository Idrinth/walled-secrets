<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Audit;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\PasswordGenerator;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactor
{
    private PDO $database;
    private Twig $twig;
    private Google2FA $twoFactor;
    private ENV $env;
    private Audit $audit;

    public function __construct(Audit $audit, PDO $database, Twig $twig, Google2FA $twoFactor, ENV $env)
    {
        $this->database = $database;
        $this->twig = $twig;
        $this->twoFactor = $twoFactor;
        $this->env = $env;
        $this->audit = $audit;
    }

    public function get(User $user): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        if ($this->env->getInt('2FA_SECRET_LENGTH') === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $this->audit->log('2fa', 'read', $user->aid(), null, $user->id());
        $stmt = $this->database->prepare('SELECT `2fa` FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $user->aid()]);
        $twofactor = $stmt->fetchColumn();
        if (!$twofactor) {
            $_SESSION['2fakey'] = $this->twoFactor->generateSecretKey($this->env->getInt('2FA_SECRET_LENGTH'));
            return $this->twig->render(
                '2fa-activation',
                [
                'title' => 'Activate 2FA',
                'source' => base64_encode(
                    $this->twoFactor->getQRCodeInline(
                        $this->env->getString('2FA_COMPANY_NAME'),
                        $this->env->getString('2FA_COMPANY_EMAIL'),
                        $_SESSION['2fakey']
                    )
                ),
                ]
            );
        }
        $reset = $_SESSION['2fareset'] ?? '';
        unset($_SESSION['2fareset']);
        return $this->twig->render(
            '2fa-deactivation',
            [
                'title' => 'Deactivate 2FA',
                'reset' => $reset,
            ]
        );
    }
    public function post(User $user, array $post): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        if ($this->env->getInt('2FA_SECRET_LENGTH') === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT `2fa` FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $user->aid()]);
        $twofactor = $stmt->fetchColumn();
        if (!$twofactor && isset($_SESSION['2fakey']) && isset($post['secret'])) {
            if ($this->twoFactor->verifyKey($_SESSION['2fakey'], $post['secret'], 0)) {
                $this->audit->log('2fa', 'create', $user->aid(), null, $user->id());
                $_SESSION['2fareset'] = PasswordGenerator::make();
                $this->database
                    ->prepare('UPDATE accounts set `2fa`=:fa,`2fareset`=:reset WHERE aid=:aid')
                    ->execute([
                        ':aid' => $user->aid(),
                        ':fa' => $_SESSION['2fakey'],
                        ':reset' => $_SESSION['2fareset']
                    ]);
            }
            unset($_SESSION['2fakey']);
        } elseif ($twofactor && isset($post['secret'])) {
            if ($this->twoFactor->verify($post['secret'], $twofactor, 0)) {
                $this->audit->log('2fa', 'delete', $user->aid(), null, $user->id());
                $this->database
                    ->prepare('UPDATE accounts set `2fa`="",`2fareset`="" WHERE aid=:aid')
                    ->execute([':aid' => $user->aid()]);
            }
        } elseif ($twofactor && isset($post['reset'])) {
            $this->audit->log('2fa', 'delete', $user->aid(), null, $user->id());
            $this->database
                ->prepare('UPDATE accounts set `2fa`="",`2fareset`="" WHERE aid=:aid AND `2fareset`=:reset')
                ->execute([':aid' => $user->aid(), ':reset' => $post['reset']]);
        }
        header('Location: /2fa', true, 303);
        return '';
    }
}
