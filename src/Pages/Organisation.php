<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;
use PDO;

class Organisation
{
    private PDO $database;
    private Twig $twig;

    public function get(string $id): string
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT organisation.*,memberships.role FROM organisations INNER JOIN memberships ON memberships.organisation=organisation.aid WHERE organisation.id=:id AND memberschips.account=:user AND memberships.role<>"Proposed"');
        $stmt->execute([':id' => $id, ':user' => $_SESSION['id']]);
        $organisation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$organisation) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT memberships.role,accounts.id,accounts.display FROM accounts INNER JOIN memberships ON memberships.account=account.aid WHERE membership.organisation=:org');
        $stmt->execute([':org' => $organisation['aid']]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT target FROM knowns WHERE owner=:id AND target NOT IN (SELECT account FROM memberships WHERE organisation=:org)');
        $stmt->execute([':org' => $organisation['aid'], ':id' => $_SESSION['id']]);
        $knowns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->twig->render('organisation', [
            'members' => $members,
            'knowns' => $knowns,
            'organisation' => $organisation,
            'title' => $organisation['name'],
        ]);
    }
}
