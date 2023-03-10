<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;
use PDO;

class Log
{
    private PDO $database;
    private Twig $twig;

    public function __construct(PDO $database, Twig $twig)
    {
        $this->database = $database;
        $this->twig = $twig;
    }

    public function get():string
    {
        if (!isset($_SESSION['id'])) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT audits.created,accounts.display,audits.`action`,audits.`type`,audits.ip,audits.target
FROM audits
INNER JOIN accounts ON accounts.aid=audits.`user`
WHERE audits.`user`=:id');
        $stmt->execute([':id' => $_SESSION['id']]);
        return $this->twig->render('log', [
            'title' => 'Log',
            'entries' => $stmt->fetchAll(),
        ]);
    }
}
