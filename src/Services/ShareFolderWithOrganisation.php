<?php

namespace De\Idrinth\WalledSecrets\Services;

use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Blowfish;
use phpseclib3\Crypt\RSA\PrivateKey;

class ShareFolderWithOrganisation
{
    private PDO $database;
    private int $organisation = 0;
    private int $user = 0;
    private array $folders = [];
    private PrivateKey $private;
    private ShareWithOrganisation $share;

    public function __construct(AES $aes, Blowfish $blowfish, PDO $database, ENV $env, ShareWithOrganisation $share)
    {
        $this->share = $share;
        $aes->setKeyLength(256);
        $aes->setKey($env->getString('PASSWORD_KEY'));
        $aes->setIV($env->getString('PASSWORD_IV'));
        $blowfish->setKeyLength(448);
        $blowfish->setKey($env->getString('PASSWORD_BLOWFISH_KEY'));
        $blowfish->setIV($env->getString('PASSWORD_BLOWFISH_IV'));
        $this->database = $database;
        if (isset($_SESSION['id']) && isset($_SESSION['uuid'])) {
            $this->user = $_SESSION['id'];
            $master = $aes->decrypt($blowfish->decrypt($_SESSION['password']));
            $this->private = KeyLoader::private($_SESSION['uuid'], $master);
            register_shutdown_function([$this, 'share']);
        }
    }

    public function setFolder(int $folder): void
    {
        $this->folders[] = $folder;
    }
    public function setOrganisation(int $organisation): void
    {
        $this->organisation = $organisation;
    }
    /**
     * @param string[][] $members
     */
    private function updateNotes(array $members, int $folder): void
    {
        $stmt = $this->database->prepare('SELECT * FROM notes WHERE folder=:folder AND `account`=:id');
        $stmt->execute([':folder' => $folder, ':id' => $this->user]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $note) {
            if ($note['content']) {
                $note['iv'] = $this->private->decrypt($note['iv']);
                $note['key'] = $this->private->decrypt($note['key']);
                $shared = new AES('ctr');
                $shared->setIV($note['iv']);
                $shared->setKeyLength(256);
                $shared->setKey($note['key']);
                $note['content'] = $shared->decrypt($note['content']);
            }
            foreach ($members as $row) {
                $this->share->updateNote($row['aid'], $row['id'], $folder, $note['id'], $note['content'], $note['public']);
            }
        }
    }
    /**
     * @param string[][] $members
     */
    private function updateLogins(array $members, int $folder): void
    {
        $stmt = $this->database->prepare('SELECT * FROM logins WHERE folder=:folder AND `account`=:id');
        $stmt->execute([':folder' => $folder, ':id' => $this->user]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $login) {
            $login['login'] = $this->private->decrypt($login['login']);
            $login['pass'] = $this->private->decrypt($login['pass']);
            if ($login['note']) {
                $login['iv'] = $this->private->decrypt($login['iv']);
                $login['key'] = $this->private->decrypt($login['key']);
                $shared = new AES('ctr');
                $shared->setIV($login['iv']);
                $shared->setKeyLength(256);
                $shared->setKey($login['key']);
                $login['note'] = $shared->decrypt($login['note']);
            }
            foreach ($members as $row) {
                $this->share->updateLogin($row['aid'], $row['id'], $folder, $login['id'], $login['user'], $login['password'], $login['note'], $login['identifier']);
            }
        }
    }
    public function share(): void
    {
        if ($this->organisation === 0 || $this->user === 0) {
            return;
        }
        $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid FROM accounts INNER JOIN memberships ON memberships.account=accounts.aid WHERE memberships.organisation=:org');
        $stmt->execute([':org' => $this->organisation]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($this->folders as $folder) {
            $this->updateLogins($members, $folder);
            $this->updateNotes($members, $folder);
        }
    }
}
