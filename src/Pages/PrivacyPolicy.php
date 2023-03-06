<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;

class PrivacyPolicy
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function get()
    {
        return $this->twig->render('privacy', ['title' => 'Privacy Policy']);
    }
}
