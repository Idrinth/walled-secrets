<?php

use De\Idrinth\WalledSecrets\Command;
use De\Idrinth\WalledSecrets\Commands\CreateUser;
use De\Idrinth\WalledSecrets\Commands\DataCleanup;
use De\Idrinth\WalledSecrets\Commands\SessionCleanup;
use De\Idrinth\WalledSecrets\Services\Database;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Command())
    ->register(new Database())
    ->register(new FilesystemLoader(dirname(__DIR__) . '/templates'))
    ->add('cleanup-session', SessionCleanup::class)
    ->add('cleanup-database', DataCleanup::class)
    ->add('register', CreateUser::class)
    ->run(...$argv);