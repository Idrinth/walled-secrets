<?php

namespace De\Idrinth\WalledSecrets\API;

use De\Idrinth\WalledSecrets\Models\User;

class OpenApi
{
    public function options(User $user): string
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        return '';
    }
    public function get(User $user)
    {
        header('Content-Type: application/json');
        return str_replace(
            '###HOST###',
            $_ENV['SYSTEM_HOSTNAME'],
            file_get_contents(dirname(__DIR__, 2) . '/resources/openapi.json')
        );
    }
}
