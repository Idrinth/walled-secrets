<?php

namespace De\Idrinth\WalledSecrets\Services;

use InvalidArgumentException;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;
use phpseclib3\Crypt\RSA;

class KeyLoader
{
    public static function private(string $uuid, string $password): PrivateKey
    {
        $file = dirname(__DIR__, 2) . '/keys/' . $uuid . '/private';
        if (!is_file($file)) {
            throw new InvalidArgumentException('No private key for user ' . $uuid);
        }
        if (empty($password)) {
            throw new InvalidArgumentException('No password given for user ' . $uuid);
        }
        try {
            $key = RSA::loadPrivateKey(file_get_contents($file));
            error_log("$uuid had no password on their private key.");
            file_put_contents($file, $key->withPassword($password)->toString('PKCS1'));
        } catch (Exception $ex) {
            //ignore, everything fine
        }
        return RSA::loadPrivateKey(file_get_contents($file), $password);
    }
    public static function public(string $uuid): PublicKey
    {
        $file = dirname(__DIR__, 2) . '/keys/' . $uuid . '/public';
        if (!is_file($file)) {
            throw new InvalidArgumentException('No public key for user ' . $uuid);
        }
        return RSA::loadPublicKey(file_get_contents($file));
    }
}
