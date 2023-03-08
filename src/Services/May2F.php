<?php

namespace De\Idrinth\WalledSecrets\Services;

use PDO;
use PragmaRX\Google2FAQRCode\Google2FA;

class May2F
{
    private PDO $database;
    private Google2FA $twoFactor;
    private ENV $env;

    public function __construct(PDO $database, Google2FA $twoFactor, ENV $env)
    {
        $this->database = $database;
        $this->twoFactor = $twoFactor;
        $this->env = $env;
    }

    public function may(string $code, int $user, int $organisation = 0): bool
    {
        if ($this->env->getInt('2FA_SECRET_LENGTH') === 0) {
            return true;
        }
        if ($user === 0) {
            return false;
        }
        $stmt = $this->database->prepare('SELECT `2fa` FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $user]);
        $twofactor = $stmt->fetchColumn();
        if ($twofactor) {
            return $this->twoFactor->verify($code, $twofactor, 0);
        }
        if ($organisation === 0) {
            return true;
        }
        $stmt = $this->database->prepare('SELECT `require2fa` FROM organisations WHERE aid=:aid');
        $stmt->execute([':aid' => $organisation]);
        $twofactor = $stmt->fetchColumn();
        return $twofactor !== '1';
    }

    public function can(int $user): bool
    {
        if ($this->env->getInt('2FA_SECRET_LENGTH') === 0) {
            return false;
        }
        if ($user === 0) {
            return false;
        }
        $stmt = $this->database->prepare('SELECT `2fa` FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $user]);
        return (bool) $stmt->fetchColumn();
    }
}
