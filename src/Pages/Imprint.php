<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;

class Imprint
{
    private Twig $twig;

    public function __construct(Twig $twig) {
        $this->twig = $twig;
    }
    public function get(): string
    {
        return $this->twig->render('imprint', ['title' => 'Imprint', 'disableRefresh' => true]);
    }
}
