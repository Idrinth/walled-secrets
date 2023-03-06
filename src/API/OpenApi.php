<?php

namespace De\Idrinth\WalledSecrets\API;

class OpenApi
{
    public function get()
    {
        header('Content-Type: application/json');
        return str_replace(
            '###HOST###',
            $_ENV['SYSTEM_HOSTNAME'],
            file_get_contents(dirname(__DIR__, 2) . '/resources/openapi.json')
        );
    }
}
