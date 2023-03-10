<?php

namespace De\Idrinth\WalledSecrets\Services;

use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;

class MasterPassword
{
    private string $decrypted = '';
    private string $encrypted = '';

    public function __construct(ENV $env, AES $aes, Blowfish $blowfish)
    {
        if (isset($_SESSION['password'])) {
            $this->encrypted = $_SESSION['password'];
        }
        $this->aes = $aes;
        $this->blowfish = $blowfish;
        $this->aes->setKeyLength(256);
        $this->aes->setKey($env->getString('PASSWORD_KEY'));
        $this->aes->setIV($env->getString('PASSWORD_IV'));
        $this->blowfish->setKeyLength(448);
        $this->blowfish->setKey($env->getString('PASSWORD_BLOWFISH_KEY'));
        $this->blowfish->setIV($env->getString('PASSWORD_BLOWFISH_IV'));
    }
    public function has(): bool
    {
        return strlen($this->encrypted) > 0;
    }
    public function get(): string
    {
        if ($this->decrypted) {
            return $this->decrypted;
        }
        return $this->decrypted = $this->aes->decrypt($this->blowfish->decrypt($this->decrypted));
    }
    public function set(string $password): void
    {
        $_SESSION['password'] = $this->blowfish->encrypt($this->aes->encrypt($password));
        $this->encrypted = $_SESSION['password'];
    }
}
