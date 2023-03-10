<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Twig;
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

    public function get(User $user): string
    {
        if ($user->aid() === 0) {
            header('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT audits.*,accounts.display
FROM audits
INNER JOIN accounts ON accounts.aid=audits.`user`
WHERE audits.`user`=:id');
        $stmt->execute([':id' => $user->aid()]);
        return $this->twig->render('log', [
            'title' => 'Log',
            'entries' => $stmt->fetchAll(),
        ]);
    }
}
