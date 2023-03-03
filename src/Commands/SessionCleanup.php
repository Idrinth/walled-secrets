<?php

namespace De\Idrinth\WalledSecrets\Commands;

use De\Idrinth\WalledSecrets\Services\ENV;

class SessionCleanup
{
    private ENV $env;

    public function __construct(ENV $env)
    {
        $this->env = $env;
    }

    public function run()
    {
        $toDelete = time() - $this->env->getInt('SYSTEM_SESSION_DURATION');
        foreach (scandir(__DIR__ . '/../../sessions') as $file) {
            if (substr($file, 0, 1) !== '.') {
                if (filemtime(__DIR__ . '/../../sessions/' . $file) < $toDelete) {
                    unlink(__DIR__ . '/../../sessions/' . $file);
                }
            }
        }
    }
}
