<?php

namespace De\Idrinth\WalledSecrets\Services;

use PDO;

class Database extends PDO
{
    public function __construct() {
        parent::__construct(
            'mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $_ENV['DATABASE_DATABASE'],
            $_ENV['DATABASE_USER'],
            $_ENV['DATABASE_PASSWORD']
        );
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    }
}
