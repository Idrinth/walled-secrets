<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Twig;
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

    public function get(User $user, string $id): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT organisations.aid,organisations.name
FROM organisations
INNER JOIN memberships ON memberships.organisation=organisations.aid
WHERE organisations.id=:id AND memberships.account=:user AND memberships.role IN("Owner","Administrator")');
        $stmt->execute([':id' => $id, ':user' => $user->aid()]);
        $organisation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$organisation) {
            header('Location: /organisation/' . $id, true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT audits.*,accounts.display
FROM audits
INNER JOIN accounts ON accounts.aid=audits.`user`
WHERE organisation=:id');
        $stmt->execute([':id' => $organisation['aid']]);
        return $this->twig->render('log', [
            'title' => $organisation . ' Log',
            'entries' => $stmt->fetchAll(),
        ]);
    }
}
