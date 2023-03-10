<?php

namespace De\Idrinth\WalledSecrets;

use De\Idrinth\WalledSecrets\Services\May2F;
use PDO;
use Twig\Environment;

class Twig
{
    private Environment $twig;
    private PDO $database;
    private May2F $may;

    public function __construct(Environment $twig, PDO $database, May2F $may)
    {
        $this->twig = $twig;
        $this->database = $database;
        $this->may = $may;
    }
    /**
     * @suppress PhanPluginUnknownArrayMethodParamType
     */
    public function render(string $template, array $context = []): string
    {
        $context['website_name'] = $_ENV['SYSTEM_NAME'];
        $context['session_duration'] = $_ENV['SYSTEM_SESSION_DURATION'];
        $context['logged_in'] = isset($_SESSION['id']);
        $context['contact_email'] = $_ENV['SYSTEM_CONTACT_EMAIL'];
        $context['contact_name'] = $_ENV['SYSTEM_CONTACT_NAME'];
        $context['contact_phone'] = $_ENV['SYSTEM_CONTACT_PHONE'];
        $context['contact_street'] = $_ENV['SYSTEM_CONTACT_STREET'];
        $context['contact_city'] = $_ENV['SYSTEM_CONTACT_CITY'];
        $context['twoFactor'] = $this->may->can($_SESSION['id'] ?? 0);
        return $this->twig->render("$template.twig", $context);
    }
}
