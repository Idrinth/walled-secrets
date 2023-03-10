<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Twig;

class Imprint
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }
    public function get(User $user): string
    {
        return $this->twig->render('imprint', ['title' => 'Imprint', 'disableRefresh' => true]);
    }
}
