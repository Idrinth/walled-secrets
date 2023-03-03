<?php

namespace De\Idrinth\WalledSecrets\Commands;

class CreateUser
{
    private PDO $database;
    private ENV $env;

    public function __construct(PDO $database, ENV $env)
    {
        $this->database = $database;
        $this->env = $env;
    }

    public function run($email, $display, $master)
    {
        $uuid = Uuid::uuid1();
        $private = RSA::createKey($this->env->getInt('SYSTEM_KEY_BYTES'));
        $private->withPassword($master);
        mkdir(__DIR__ . '/../../keys/' . $uuid);
        file_put_contents(__DIR__ . '/../../keys/' . $uuid . '/private', $private->toString('openssl'));
        file_put_contents(__DIR__ . '/../../keys/' . $uuid . '/public', $private->getPublicKey()->toString('openssl'));
        $this->database
            ->prepare('INSERT INTO accounts (id,display,mail) VALUES (:id,:display,:mail)')
            ->execute([':display' => $display, ':id' => $uuid, ':mail' => $email]);
    }
}
