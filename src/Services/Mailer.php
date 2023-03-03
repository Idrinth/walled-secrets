<?php

namespace De\Idrinth\WalledSecrets\Services;

use De\Idrinth\WalledSecrets\Twig;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private Twig $twig;
    private PDO $database;
    
    public function __construct(Twig $twig, PDO $database)
    {
        $this->twig = $twig;
        $this->database = $database;
    }

    public function send(int $tagetUser, string $template, array $templateContext, string $subject, string $toMail, string $toName)
    {
        $stmt = $this->database->prepare('SELECT 1 FROM email_blacklist WHERE email=:email');
        $stmt->execute([':email' => $toMail]);
        if ($stmt->fetchColumn() === '1') {
            error_log("$toMail is blacklisted");
            return false;
        }
        $mailer = new PHPMailer();
        $mailer->setFrom($_ENV['MAIL_FROM_MAIL'], $_ENV['MAIL_FROM_NAME']);
        $mailer->addAddress($toMail, $toName);
        $mailer->Host = $_ENV['MAIL_HOST'];
        $mailer->Username = $_ENV['MAIL_USER'];
        $mailer->Password = $_ENV['MAIL_PASSWORD'];
        $mailer->Port = intval($_ENV['MAIL_PORT_SMTP'], 10);
        $mailer->CharSet = 'utf-8';
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Timeout = 60;
        $mailer->isHTML(true);
        $mailer->Mailer ='smtp';
        $mailer->Subject = $subject;
        $mailer->Body = $this->twig->render(
            "mails/$template-html",
            $templateContext
        );
        $mailer->AltBody = $this->twig->render(
            "mails/$template-text",
            $templateContext
        );
        $mailer->SMTPAuth = true;
        if (!$mailer->smtpConnect()) {
            error_log('Mailer failed smtp connect.');
            return false;
        }
        if (!$mailer->send()) {
            error_log('Mailer failed sending mail.');
            return false;
        }
        return true;
    }
}
