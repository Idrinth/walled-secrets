<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;
use PDO;

class OrganisationLog
{
    private PDO $database;
    private Twig $twig;

    public function __construct(PDO $database, Twig $twig)
    {
        $this->database = $database;
        $this->twig = $twig;
    }

    public function get(string $id):string
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT organisations.aid,organisations.name FROM organisations INNER JOIN memberships ON memberships.organisation=organisations.aid WHERE organisations.id=:id AND memberships.account=:user AND memberships.role IN("Owner","Administrator")');
        $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
        $organisation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$organisation) {
            header('Location: /organisation/' . $id, true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT audits.created,accounts.display,audits.`action`,audits.`type`,audits.ip
FROM audits
INNER JOIN accounts ON accounts.aid=audits.`user`
WHERE organisation=:id');
        $stmt->execute([':id' => $organisation['aid']]);
        $this->twig->render('log', [
            'title' => $organisation . ' Log',
            'entries' => $stmt->fetchAll(),
        ]);
    }
}
