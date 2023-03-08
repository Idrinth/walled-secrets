<?php

namespace De\Idrinth\WalledSecrets\Services;

use Iodev\Whois\Factory;
use PDO;
use Wikimedia\IPSet;

class Access
{
    private PDO $database;
    private ENV $env;
    
    public function __construct(PDO $database, ENV $env)
    {
        $this->database = $database;
        $this->env = $env;
    }

    public function may($ip): bool
    {
        $whitelist = new IPSet($this->env->getStringList('IP_WHITELIST'));
        if ($whitelist->match($ip)) {
            return true;
        }
        $routes = $this->env->getStringList('IP_BLACKLIST_SET');
        $whois = Factory::get()->createWhois();
        foreach($this->env->getStringList('IP_ASN_BLACKLIST') as  $asn) {
            foreach ($whois->loadAsnInfo($asn)->routes as $route) {
                if (!empty($route->route)) {
                    $routes[] = $route->route;
                }
                if (!empty($route->route6)) {
                    $routes[] = $route->route6;
                }
            }
        }
        $blacklist = new IPSet($routes);
        if ($blacklist->match($ip)) {
            error_log("$ip blocked by server blacklist.");
            return false;
        }
        if (!isset($_SESSION['id'])) {
            return true;
        }
        $stmt = $this->database->prepare('SELECT ip_whitelist,ip_blacklist FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $_SESSION['id']]);
        $ips = $stmt->fetch(PDO::FETCH_ASSOC);
        $personalWhitelist = new IPSet($this->stringToList($ips['ip_whitelist']));
        if ($personalWhitelist->match($ip)) {
            return true;
        }
        $personalBlacklist = new IPSet($this->stringToList($ips['ip_blacklist']));
        if ($personalBlacklist->match($ip)) {
            error_log("$ip blocked by user blacklist.");
            return false;
        }
        return true;
    }
    private function stringToList(string $string): array
    {
        return array_filter(array_map('trim', explode(',', $string)));
    }
}
