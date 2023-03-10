<?php

namespace De\Idrinth\WalledSecrets\Factories;

class Superglobals
{
    public static function post(): array
    {
        return self::filtered($_POST);
    }
    public static function session(): array
    {
        return self::filtered($_SESSION);
    }
    private static function filtered(array $in): array
    {
        $out = [];
        foreach ($in as $key => $value) {
            if (!empty($value)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
