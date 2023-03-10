<?php

namespace De\Idrinth\WalledSecrets\Commands;

use PDO;

class DataCleanup
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function run()
    {
        $del1 = $this->database->prepare('DELETE FROM logins
WHERE folder IN (
    SELECT folder FROM folders
    WHERE `type`="Organisation" AND `owner`=:org
) AND `account` NOT IN (
    SELECT `account` FROM memberships
    WHERE organisation=:org AND `role`<>"Proposed"
)');
        $del2 = $this->database->prepare('DELETE FROM notes
WHERE folder IN (
    SELECT folder FROM folders
    WHERE `type`="Organisation" AND `owner`=:org
) AND `account` NOT IN (
    SELECT `account` FROM memberships
    WHERE organisation=:org AND `role`<>"Proposed"
)');
        $stmt = $this->database->query('SELECT aid FROM organisations');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $org) {
            $del1->closeCursor();
            $del1->execute([':org' => $org['aid']]);
            $del2->closeCursor();
            $del2->execute([':org' => $org['aid']]);
        }
    }
}
