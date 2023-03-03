<?php

use De\Idrinth\WalledSecrets\Application;
use De\Idrinth\WalledSecrets\Pages\Folder;
use De\Idrinth\WalledSecrets\Pages\Home;
use De\Idrinth\WalledSecrets\Pages\Login;
use De\Idrinth\WalledSecrets\Pages\Master;
use De\Idrinth\WalledSecrets\Pages\Organisation;
use De\Idrinth\WalledSecrets\Pages\SignUp;
use De\Idrinth\WalledSecrets\Services\Database;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/../vendor/autoload.php';

(new Application())
    ->register(new Database())
    ->register(new FilesystemLoader(__DIR__ . '/../templates'))
    ->register(new AES('ctr'))
    ->register(new Blowfish('ctr'))
    ->get('/', Home::class)
    ->post('/', Home::class)
    ->get('/master', Master::class)
    ->post('/master', Master::class)
    ->get('/login/{id}/{pass}', Login::class)
    ->get('/register/{id}/{pass}', SignUp::class)
    ->post('/register/{id}/{pass}', SignUp::class)
    ->get('/folder/{id}', Folder::class)
    ->post('/folder/{id}', Folder::class)
    ->get('/organisation/{id}', Organisation::class)
    ->post('/organisation/{id}', Organisation::class)
    ->run();