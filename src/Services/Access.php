<?php

namespace De\Idrinth\WalledSecrets\Services;

use De\Idrinth\WalledSecrets\Models\User;
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
    private function getCachedResult(string $filename): ?bool
    {
        $file = dirname(__DIR__, 2) . '/ipcache/' . $filename;
        if (!is_file($file)) {
            return null;
        }
        return json_decode(file_get_contents($file) ?: 'null');
    }
    private function setCachedResult(string $filename, bool $status): bool
    {
        $file = dirname(__DIR__, 2) . '/ipcache/' . $filename;
        file_put_contents($file, json_encode($status));
        return $status;
    }
    /**
     * @param string[] $routes
     */
    private function byASN(array &$routes): void
    {
        $file = dirname(__DIR__, 2) . '/ipcache/asn';
        if (is_file($file)) {
            foreach (json_decode(file_get_contents($file) ?: '[]') as $ip) {
                $routes[] = $ip;
            }
            return;
        }
        $whois = Factory::get()->createWhois();
        $asnRoutes = [];
        foreach ($this->env->getStringList('IP_ASN_BLACKLIST') as $asn) {
            foreach ($whois->loadAsnInfo($asn)->routes as $route) {
                if (!empty($route->route)) {
                    $routes[] = $route->route;
                    $asnRoutes[] = $route->route;
                }
                if (!empty($route->route6)) {
                    $routes[] = $route->route6;
                    $asnRoutes[] = $route->route6;
                }
            }
        }
        file_put_contents($file, json_encode($asnRoutes));
    }
    private function byServer(string $ip): bool
    {
        $file = md5($ip) . '_' . strlen($ip);
        $previous = $this->getCachedResult($file);
        if ($previous !== null) {
            return $previous;
        }
        $whitelist = new IPSet($this->env->getStringList('IP_WHITELIST'));
        if ($whitelist->match($ip)) {
            return $this->setCachedResult($file, true);
        }
        $routes = $this->env->getStringList('IP_BLACKLIST_SET');
        $this->byASN($routes);
        $blacklist = new IPSet($routes);
        return $this->setCachedResult($file, !$blacklist->match($ip));
    }
    private function byUser(int $user, string $ip): bool
    {
        $file = md5($ip) . '_' . strlen($ip) . '_' . $user;
        $previous = $this->getCachedResult($file);
        if ($previous !== null) {
            return $previous;
        }
        $stmt = $this->database->prepare('SELECT ip_whitelist,ip_blacklist FROM accounts WHERE aid=:aid');
        $stmt->execute([':aid' => $user]);
        $ips = $stmt->fetch(PDO::FETCH_ASSOC);
        $whitelist = new IPSet($this->stringToList($ips['ip_whitelist']));
        if ($whitelist->match($ip)) {
            return $this->setCachedResult($file, true);
        }
        $blacklist = new IPSet($this->stringToList($ips['ip_blacklist']));
        return $this->setCachedResult($file, !$blacklist->match($ip));
    }
    public function may(string $ip, User $user): bool
    {
        if (!$this->byServer($ip)) {
            error_log("$ip blocked by server blacklist.");
            return false;
        }
        if ($user->aid() === 0) {
            return true;
        }
        if (!$this->byUser($user->aid(), $ip)) {
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
