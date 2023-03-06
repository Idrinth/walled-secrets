<?php

namespace De\Idrinth\WalledSecrets\Pages;

class Eula
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function get()
    {
        return $this->twig->render('eula', ['title' => 'End User License Agreement']);
    }
}
