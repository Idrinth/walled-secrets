<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;

class FAQ
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function get()
    {
        return $this->twig->render('faq', ['title' => 'FAQ']);
    }
}
