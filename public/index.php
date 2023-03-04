<?php

use De\Idrinth\WalledSecrets\Application;
use De\Idrinth\WalledSecrets\Pages\Folder;
use De\Idrinth\WalledSecrets\Pages\Home;
use De\Idrinth\WalledSecrets\Pages\Importer;
use De\Idrinth\WalledSecrets\Pages\Imprint;
use De\Idrinth\WalledSecrets\Pages\Login;
use De\Idrinth\WalledSecrets\Pages\Logins;
use De\Idrinth\WalledSecrets\Pages\Master;
use De\Idrinth\WalledSecrets\Pages\Notes;
use De\Idrinth\WalledSecrets\Pages\Organisation;
use De\Idrinth\WalledSecrets\Pages\Search;
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
    ->get('/imprint', Imprint::class)
    ->get('/master', Master::class)
    ->post('/master', Master::class)
    ->get('/import', Importer::class)
    ->post('/import', Importer::class)
    ->get('/search', Search::class)
    ->post('/search', Search::class)
    ->get('/logins/{id}', Logins::class)
    ->post('/logins/{id}', Logins::class)
    ->get('/notes/{id}', Notes::class)
    ->post('/notes/{id}', Notes::class)
    ->get('/login/{id}/{pass}', Login::class)
    ->get('/register/{id}/{pass}', SignUp::class)
    ->post('/register/{id}/{pass}', SignUp::class)
    ->get('/folder/{id}', Folder::class)
    ->post('/folder/{id}', Folder::class)
    ->get('/organisation/{id}', Organisation::class)
    ->post('/organisation/{id}', Organisation::class)
    ->run();