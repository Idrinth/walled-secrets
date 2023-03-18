<?php

namespace De\Idrinth\WalledSecrets\Models;

use PDO;

final class User
{
    private int $aid = 0;
    private string $id = '';
    private string $ip_whitelist = '';
    private string $ip_blacklist = '';
    private string $display = '';
    private string $mail = '';
    private bool $haveibeenpwned = false;

    public function __construct(int $aid, PDO $database)
    {
        $stmt = $database->prepare('SELECT * FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $aid]);
        foreach ($stmt->fetch(PDO::FETCH_ASSOC) ?: [] as $property => $value) {
            $this->{$property} = $value;
        }
    }
    public function ipWhitelist(): string
    {
        return $this->ip_whitelist;
    }
    public function ipBlacklist(): string
    {
        return $this->ip_blacklist;
    }
    public function aid(): int
    {
        return $this->aid;
    }
    public function id(): string
    {
        return $this->id;
    }
    public function display(): string
    {
        return $this->display;
    }
    public function mail(): string
    {
        return $this->mail;
    }
    public function haveibeenpwned(): bool
    {
        return $this->haveibeenpwned;
    }
}
