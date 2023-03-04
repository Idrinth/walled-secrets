<?php

namespace De\Idrinth\WalledSecrets;

use PDO;
use Twig\Environment;

class Twig
{
    private Environment $twig;
    private PDO $database;

    public function __construct(Environment $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }
    public function render(string $template, array $context = []): string
    {
        $context['website_name'] = $_ENV['SYSTEM_NAME'];
        $context['session_duration'] = $_ENV['SYSTEM_SESSION_DURATION'];
        $context['logged_in'] = isset($_SESSION['id']);
        return $this->twig->render("$template.twig", $context);
    }
}
