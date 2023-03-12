<?php

use De\Idrinth\WalledSecrets\Command;
use De\Idrinth\WalledSecrets\Commands\AuditCleanup;
use De\Idrinth\WalledSecrets\Commands\CreateUser;
use De\Idrinth\WalledSecrets\Commands\DataCleanup;
use De\Idrinth\WalledSecrets\Commands\IPCacheCleanup;
use De\Idrinth\WalledSecrets\Commands\SessionCleanup;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Command())
    ->register(
        new PDO(
            'mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $_ENV['DATABASE_DATABASE'],
            $_ENV['DATABASE_USER'],
            $_ENV['DATABASE_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING]
        )
    )
    ->add('cleanup-session', SessionCleanup::class)
    ->add('cleanup-ipcache', IPCacheCleanup::class)
    ->add('cleanup-database', DataCleanup::class)
    ->add('cleanup-audit', AuditCleanup::class)
    ->add('register', CreateUser::class)
    ->run(...$argv);
