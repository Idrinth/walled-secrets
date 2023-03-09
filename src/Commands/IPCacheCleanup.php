<?php

namespace De\Idrinth\WalledSecrets\Commands;

use De\Idrinth\WalledSecrets\Services\ENV;

class IPCacheCleanup
{
    private ENV $env;

    public function __construct(ENV $env)
    {
        $this->env = $env;
    }

    public function run()
    {
        $toDelete = time() - $this->env->getInt('IP_CACHE_DURATION');
        $dir = __DIR__ . '/../../ipcache';
        foreach (scandir($dir) as $file) {
            if (substr($file, 0, 1) !== '.' && $file !== 'asn') {
                if (filemtime("$dir/$file") < $toDelete) {
                    unlink("$dir/$file");
                }
            }
        }
        if (is_file("$dir/asn") && filemtime("$dir/asn") < time() - $this->env->getInt('IP_ASN_CACHE_DURATION')) {
            unlink("$dir/asn");
        }
    }
}
