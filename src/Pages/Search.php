<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Twig;
use PDO;

class Search
{
    private PDO $database;
    private Twig $twig;

    public function __construct(PDO $database, Twig $twig)
    {
        $this->database = $database;
        $this->twig = $twig;
    }

    public function get()
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        return $this->twig->render('search', [
            'title' => 'Search',
        ]);
    }
    
    public function post(array $post)
    {
        if (!isset($_SESSION['id'])) {
            header ('Location: /', true, 303);
            return '';
        }
        $stmt = $this->database->prepare('SELECT public,id FROM logins WHERE public LIKE CONCAT("%",:term,"%") AND `account`=:id');
        $stmt->execute([':id' => $_SESSION['id'], ':term' => $post['term']]); 
        $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->database->prepare('SELECT public,id FROM notes WHERE public LIKE CONCAT("%",:term,"%") AND `account`=:id');
        $stmt->execute([':id' => $_SESSION['id'], ':term' => $post['term']]); 
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->twig->render('search', [
            'term' => $post['term'],
            'title' => 'Search',
            'logins' => $logins,
            'notes' => $notes,
        ]);
    }
}
