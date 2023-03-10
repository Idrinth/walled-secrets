<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;

class Ping
{
    public function get(User $user): string
    {
        header('Content-Type: text/plain', true, 202);
        return '';
    }
}
