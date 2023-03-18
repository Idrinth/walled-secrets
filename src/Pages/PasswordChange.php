<?php

namespace De\Idrinth\WalledSecrets\Pages;

use De\Idrinth\WalledSecrets\Models\User;
use De\Idrinth\WalledSecrets\Services\Twig;
use PDO;

class PasswordChange
{
    private PDO $database;
    private Twig $twig;

    public function __construct(PDO $database, Twig $twig)
    {
        $this->database = $database;
        $this->twig = $twig;
    }

        public function get(User $user, string $userId, string $uuid, string $key)
    {
        $stmt = $this->database->prepare('SELECT * FROM master WHERE `id`=:id AND `user`=:user');
        $stmt->execute([':id' => $uuid, ':user' => $userId]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$case) {
            return $this->twig->render('password-change-fail', [
                'title' => 'Password Change',
                'disableRefresh' => true,
            ]);
        }
        if ($key === $case['deny']) {
            $this->database
                ->prepare('DELETE FROM master WHERE `id`=:id AND `user`=:user')
                ->execute([':id' => $uuid, ':user' => $userId]);
            return $this->twig->render('password-change-unchanged', [
                'title' => 'Password Change',
                'disableRefresh' => true,
            ]);
        }
        if ($key === $case['confirm']) {
            $this->database
                ->prepare('DELETE FROM master WHERE `id`=:id AND `user`=:user')
                ->execute([':id' => $uuid, ':user' => $userId]);
            file_put_contents(dirname(__DIR__, 2) . '/keys/' . $userId . '/private', $case['private']);
            return $this->twig->render('password-change-changed', [
                'title' => 'Password Change',
                'disableRefresh' => true,
            ]);
        }
        return $this->twig->render('password-change-fail', [
            'title' => 'Password Change',
            'disableRefresh' => true,
        ]);
    }
}
