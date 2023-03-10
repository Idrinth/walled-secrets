<?php

use De\Idrinth\WalledSecrets\API\ListSecrets;
use De\Idrinth\WalledSecrets\API\Login as Login2;
use De\Idrinth\WalledSecrets\API\Note;
use De\Idrinth\WalledSecrets\API\OpenApi;
use De\Idrinth\WalledSecrets\Application;
use De\Idrinth\WalledSecrets\Pages\Eula;
use De\Idrinth\WalledSecrets\Pages\FAQ;
use De\Idrinth\WalledSecrets\Pages\Folder;
use De\Idrinth\WalledSecrets\Pages\Home;
use De\Idrinth\WalledSecrets\Pages\Importer;
use De\Idrinth\WalledSecrets\Pages\Imprint;
use De\Idrinth\WalledSecrets\Pages\IP;
use De\Idrinth\WalledSecrets\Pages\Knowns;
use De\Idrinth\WalledSecrets\Pages\Log;
use De\Idrinth\WalledSecrets\Pages\Login;
use De\Idrinth\WalledSecrets\Pages\Logins;
use De\Idrinth\WalledSecrets\Pages\Mailed;
use De\Idrinth\WalledSecrets\Pages\Master;
use De\Idrinth\WalledSecrets\Pages\Notes;
use De\Idrinth\WalledSecrets\Pages\Organisation;
use De\Idrinth\WalledSecrets\Pages\OrganisationLog;
use De\Idrinth\WalledSecrets\Pages\Ping;
use De\Idrinth\WalledSecrets\Pages\PrivacyPolicy;
use De\Idrinth\WalledSecrets\Pages\Search;
use De\Idrinth\WalledSecrets\Pages\SignUp;
use De\Idrinth\WalledSecrets\Pages\Socials;
use De\Idrinth\WalledSecrets\Pages\TwoFactor;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/../vendor/autoload.php';

(new Application())
    ->register(
        new PDO(
            'mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $_ENV['DATABASE_DATABASE'],
            $_ENV['DATABASE_USER'],
            $_ENV['DATABASE_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING]
        )
    )
    ->register(new FilesystemLoader(__DIR__ . '/../templates'))
    ->register(new AES('ctr'))
    ->register(new Blowfish('ctr'))
    ->get('/', Home::class)
    ->post('/', Home::class)
    ->get('/privacy', PrivacyPolicy::class)
    ->get('/api/ping', Ping::class)
    ->get('/imprint', Imprint::class)
    ->get('/faq', FAQ::class)
    ->get('/mailed', Mailed::class)
    ->get('/eula', Eula::class)
    ->get('/socials', Socials::class)
    ->post('/socials', Socials::class)
    ->get('/master', Master::class)
    ->post('/master', Master::class)
    ->get('/import', Importer::class)
    ->post('/import', Importer::class)
    ->get('/search', Search::class)
    ->post('/search', Search::class)
    ->get('/logins/{id}', Logins::class)
    ->post('/logins/{id}', Logins::class)
    ->post('/api/logins/{id}', Login2::class)
    ->get('/notes/{id}', Notes::class)
    ->post('/notes/{id}', Notes::class)
    ->post('/api/notes/{id}', Note::class)
    ->get('/knowns/{id}', Knowns::class)
    ->post('/knowns/{id}', Knowns::class)
    ->get('/login/{id}/{pass}', Login::class)
    ->get('/register/{id}/{pass}', SignUp::class)
    ->post('/register/{id}/{pass}', SignUp::class)
    ->get('/folder/{id}', Folder::class)
    ->post('/folder/{id}', Folder::class)
    ->get('/organisation/{id}', Organisation::class)
    ->post('/organisation/{id}', Organisation::class)
    ->get('/organisation/{id}/log', OrganisationLog::class)
    ->post('/api/list-secrets', ListSecrets::class)
    ->get('/api/open-api.json', OpenApi::class)
    ->post('/2fa', TwoFactor::class)
    ->get('/2fa', TwoFactor::class)
    ->post('/ip', IP::class)
    ->get('/ip', IP::class)
    ->get('/log', Log::class)
    ->run();
