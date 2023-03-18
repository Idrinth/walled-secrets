<?php

namespace De\Idrinth\WalledSecrets\Services;

use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Random;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;

class AESCrypter
{
    public static function encrypt(PublicKey $public, string $text): array
    {
        if (!$text) {
            return ['', '', ''];
        }
        $iv = Random::string(16);
        $key = Random::string(32);
        $shared = new AES('ctr');
        $shared->setKeyLength(256);
        $shared->setKey($key);
        $shared->setIV($iv);
        return [
            $shared->encrypt($text),
            $public->encrypt($key),
            $public->encrypt($iv),
        ];
    }
    public static function decrypt(PrivateKey $private, string $text, string $iv, string $key): string
    {
        if (!$text) {
            return '';
        }
        $shared = new AES('ctr');
        $shared->setIV($private->decrypt($iv));
        $shared->setKeyLength(256);
        $shared->setKey($private->decrypt($key));
        return $shared->decrypt($text);
    }
}
