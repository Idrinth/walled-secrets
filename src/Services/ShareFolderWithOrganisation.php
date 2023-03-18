<?php

namespace De\Idrinth\WalledSecrets\Services;

use PDO;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\RSA\PrivateKey;

class ShareFolderWithOrganisation
{
    private PDO $database;
    private int $organisation = 0;
    private int $user = 0;
    private array $folders = [];
    private PrivateKey $private;
    private SecretHandler $share;

    public function __construct(MasterPassword $master, PDO $database, SecretHandler $share)
    {
        $this->share = $share;
        $this->database = $database;
        if (isset($_SESSION['id']) && isset($_SESSION['uuid']) && $master->has()) {
            $this->user = $_SESSION['id'];
            $this->private = KeyLoader::private($_SESSION['uuid'], $master->get());
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
            $note['content'] = AESCrypter::decrypt($this->private, $note['content'], $note['iv'], $note['key']);
            foreach ($members as $row) {
                $this->share->updateNote(
                    $row['aid'],
                    $row['id'],
                    $folder,
                    $note['id'],
                    $note['content'],
                    $note['public']
                );
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
            $login['note'] = AESCrypter::decrypt($this->private, $login['note'], $login['iv'], $login['key']);
            foreach ($members as $row) {
                $this->share->updateLogin(
                    $row['aid'],
                    $row['id'],
                    $folder,
                    $login['id'],
                    $login['user'],
                    $login['password'],
                    $login['note'],
                    $login['identifier']
                );
            }
        }
    }
    public function share(): void
    {
        if ($this->organisation === 0 || $this->user === 0) {
            return;
        }
        $stmt = $this->database->prepare('SELECT accounts.id, accounts.aid
FROM accounts
INNER JOIN memberships ON memberships.account=accounts.aid
WHERE memberships.organisation=:org AND memberships.`role`<>"Proposed"');
        $stmt->execute([':org' => $this->organisation]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($this->folders as $folder) {
            $this->updateLogins($members, $folder);
            $this->updateNotes($members, $folder);
        }
    }
}
