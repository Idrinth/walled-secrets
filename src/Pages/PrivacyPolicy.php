<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Twig;

class PrivacyPolicy
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function get(User $user)
    {
        return $this->twig->render('privacy', ['title' => 'Privacy Policy']);
    }
}
