<?php

namespace De\Idrinth\WalledSecrets\Commands;

use De\Idrinth\WalledSecrets\Services\ENV;
use PDO;

class AuditCleanup
{
    private ENV $env;
    private PDO $database;

    public function __construct(ENV $env, PDO $database)
    {
        $this->env = $env;
        $this->database = $database;
    }

    public function run()
    {
        if (!$this->env->getBool('SYSTEM_AUDIT_DELETE')) {
            return;
        }
        $toDelete = date('Y-m-d H:i:s', time() - $this->env->getInt('SYSTEM_AUDIT_MAX_DAYS') * 86400);
        $this->database
            ->prepare('DELETE FROM audits WHERE created < :date')
            ->execute([':date' => $toDelete]);
    }
}
