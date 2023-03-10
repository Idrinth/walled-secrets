<?php

namespace De\Idrinth\WalledSecrets\Services;

use PDO;

class Audit
{
    private PDO $database;
    private ENV $env;

    public function __construct(PDO $database, ENV $env)
    {
        $this->database = $database;
        $this->env = $env;
    }

    public function log(string $type, string $action, int $user, ?int $organisation, string $target): void
    {
        if (!$this->env->getBool('SYSTEM_AUDIT')) {
            return;
        }
        if (is_null($organisation)) {
            $this->database
                ->prepare('INSERT INTO audits (action,user,target,organisation,ip,type)
VALUES (:action,:user,:target,NULL,:ip,:type)')
                ->execute([
                    ':action' => $action,
                    ':user' => $user,
                    ':target' => $target,
                    ':ip' => $_SERVER['REMOTE_ADDR'],
                    ':type' => $type
                ]);
            return;
        }
        $this->database
            ->prepare('INSERT INTO audits (action,user,target,organisation,ip,type)
VALUES (:action,:user,:target,:organisation,:ip,:type)')
            ->execute([
                ':action' => $action,
                ':user' => $user,
                ':target' => $target,
                ':organisation' => $organisation,
                ':ip' => $_SERVER['REMOTE_ADDR'],
                ':type' => $type
            ]);
    }
}
