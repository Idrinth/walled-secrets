<?php

namespace De\Idrinth\WalledSecrets\API;

class Ping
{
    public function get(): string
    {
        header('Content-Type: text/plain', true, 202);
        return '';
    }
}
