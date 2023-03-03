<?php

namespace De\Idrinth\WalledSecrets\Services;

use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

class SessionHandler implements SessionIdInterface, SessionUpdateTimestampHandlerInterface, SessionHandlerInterface
{
    private static $lock;

    public function open($savePath, $sessionName)
    {
        return true;
    }
    public function close(): bool
    {
        return flock(self::$lock, LOCK_EX) && fclose(self::$lock);
    }
    public function write(string $id, string $data): bool
    {
        $file = dirname(__DIR__, 2) . '/sessions/session_' . md5($_SERVER['REMOTE_ADDR']) . '_' .$id;
        if (preg_match('/^[a-zA-Z0-9]{128}$/') && is_file($file)) {
            return fwrite($this->lock, $data);
        }
        return false;
    }
    public function read(string $id)
    {
        $file = dirname(__DIR__, 2) . '/sessions/session_' . md5($_SERVER['REMOTE_ADDR']) . '_' .$id;
        if (preg_match('/^[a-zA-Z0-9]{128}$/') && is_file($file)) {
            return fread($this->lock, filesize($file));
        }
        return false;
    }
    public function destroy(string $id): bool
    {
        $file = dirname(__DIR__, 2) . '/sessions/session_' . md5($_SERVER['REMOTE_ADDR']) . '_' .$id;
        if (preg_match('/^[a-zA-Z0-9]{128}$/') && is_file($file)) {
            return unlink($file);
        }
        return false;
    }
    public function gc(int $max_lifetime)
    {
        return 0;
    }
    public function create_sid(): string
    {
        $chars = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        $out = '';
        while (strlen($out) < 128) {
            $out .= $chars[rand(0, 61)];
        }
        return $out;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $file = dirname(__DIR__, 2) . '/sessions/session_' . md5($_SERVER['REMOTE_ADDR']) . '_' .$id;
        if (preg_match('/^[a-zA-Z0-9]{128}$/') && is_file($file)) {
            return touch($file);
        }
        return false;
    }

    public function validateId(string $id): bool
    {
        return preg_match('/^[a-zA-Z0-9]{128}$/') && is_file(dirname(__DIR__, 2) . '/sessions/session_' . md5($_SERVER['REMOTE_ADDR']) . '_' .$id);
    }
}