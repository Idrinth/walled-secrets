<?php

use De\Idrinth\WalledSecrets\Command;
use De\Idrinth\WalledSecrets\Commands\CreateUser;
use De\Idrinth\WalledSecrets\Commands\SessionCleanup;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Command())
    ->register(new PDO('mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $_ENV['DATABASE_DATABASE'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']))
    ->register(new FilesystemLoader(dirname(__DIR__) . '/templates'))
    ->add('cleanup', SessionCleanup::class)
    ->add('register', CreateUser::class)
    ->run(...$argv);