<?php

use De\Idrinth\WalledSecrets\Command;
use De\Idrinth\WalledSecrets\Commands\CreateUser;
use De\Idrinth\WalledSecrets\Commands\SessionCleanup;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Command())
    ->register(new FilesystemLoader(dirname(__DIR__) . '/templates'))
    ->add('cleanup', SessionCleanup::class)
    ->add('register', CreateUser::class)
    ->run(...$argv);