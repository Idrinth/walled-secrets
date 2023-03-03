<?php

namespace De\Idrinth\WalledSecrets\Commands;

use De\Idrinth\WalledSecrets\Services\ENV;
use PDO;
use phpseclib3\Crypt\RSA;
use Ramsey\Uuid\Uuid;

class CreateUser
{
    private PDO $database;
    private ENV $env;

    public function __construct(PDO $database, ENV $env)
    {
        $this->database = $database;
        $this->env = $env;
    }

    public function run()
    {
        echo "The eMail:\n";
        $email = trim(fgets(STDIN));
        echo "The Master-Password:\n";
        $master = trim(fgets(STDIN));
        echo "The Display Name:\n";
        $display = trim(fgets(STDIN));
        $uuid = Uuid::uuid1();
        echo "Your user id is $uuid, now generating private and public key pair.\n";
        $private = RSA::createKey($this->env->getInt('SYSTEM_KEY_BYTES'));
        echo "Private key generated.\n";
        $private->withPassword($master);
        echo "Master Password set.\n";
        mkdir(__DIR__ . '/../../keys/' . $uuid);
        file_put_contents(__DIR__ . '/../../keys/' . $uuid . '/private', $private->toString('OpenSSH'));
        file_put_contents(__DIR__ . '/../../keys/' . $uuid . '/public', $private->getPublicKey()->toString('OpenSSH'));
        echo "Keys written to filesystem.\n";
        $this->database
            ->prepare('INSERT INTO accounts (id,display,mail) VALUES (:id,:display,:mail)')
            ->execute([':display' => $display, ':id' => $uuid, ':mail' => $email]);
        echo "User added to database.\n";
    }
}
