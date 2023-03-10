<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\ENV;
use De\Idrinth\WalledSecrets\Services\Twig;

class Mailed
{
    private ENV $env;
    private Twig $twig;

    public function __construct(ENV $env, Twig $twig)
    {
        $this->env = $env;
        $this->twig = $twig;
    }

    public function get(User $user): string
    {
        return $this->twig->render(
            'mailed',
            [
            'title' => 'Login',
            'minutes' => ceil($this->env->getInt('SYSTEM_SESSION_DURATION') / 60),
            'disableRefresh' => true
            ]
        );
    }
}
