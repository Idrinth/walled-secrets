<?php

namespace De\Idrinth\WalledSecrets\Services;

use InvalidArgumentException;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\Common\PublicKey;
use phpseclib3\Crypt\RSA;

class KeyLoader
{
    public static function private($uuid, $password): PrivateKey
    {
        $file = dirname(__DIR__, 2) . '/keys/' . $uuid . '/private';
        if (!is_file($file)) {
            throw InvalidArgumentException('No private key for user '.$uuid);
        }
        return RSA::loadPrivateKey(file_get_contents($file), $password);
    }
    public static function public($uuid): PublicKey
    {
        $file = dirname(__DIR__, 2) . '/keys/' . $uuid . '/public';
        if (!is_file($file)) {
            throw InvalidArgumentException('No public key for user '.$uuid);
        }
        return RSA::loadPublicKey(file_get_contents($file));
    }
}