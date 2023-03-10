<?php

namespace De\Idrinth\WalledSecrets\Models;

use PDO;

final class User
{
    private int $aid;
    private string $id;
    private string $ip_whitelist;
    private string $ip_blacklist;
    private string $display;
    private bool $haveibeenpwned;

    public function __construct(int $aid, PDO $database)
    {
        $stmt = $database->prepare('SELECT * FROM accounts WHERE aid=:aid');
        $stmt->setFetchMode(PDO::FETCH_INTO, $this);
        $stmt->execute([':aid' => $aid]);
        $stmt->fetch();
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
    public function haveibeenpwned(): bool
    {
        return $this->haveibeenpwned;
    }
}
