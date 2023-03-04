<?php

namespace De\Idrinth\WalledSecrets\Services;

use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

class SessionHandler implements SessionIdInterface, SessionUpdateTimestampHandlerInterface, SessionHandlerInterface
{
    public function open($savePath, $sessionName)
    {
        return true;
    }
    public function close(): bool
    {
        return true;
    }
    private function getFile(string $id): string
    {
        return dirname(__DIR__, 2) . '/sessions/session_' . md5($_SERVER['REMOTE_ADDR']) . '_' .$id;
    }
    private function exists(string $id): bool
    {
        return preg_match('/^[a-zA-Z0-9]{128}$/', $id) && touch($this->getFile($id));
    }
    public function write($id, $data)
    {
        if (preg_match('/^[a-zA-Z0-9]{128}$/', $id)) {
            return (bool) file_put_contents($this->getFile($id), $data);
        }
        return false;
    }
    public function read($id)
    {
        if ($this->exists($id)) {
            return file_get_contents($this->getFile($id)) ?: '';
        }
        if (preg_match('/^[a-zA-Z0-9]{128}$/', $id)) {
            return touch($this->getFile($id));
        }
        return '';
    }
    public function destroy($id)
    {
        if ($this->exists($id)) {
            return unlink($this->getFile($id));
        }
        return false;
    }
    public function gc($max_lifetime)
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

    public function updateTimestamp($id, $data)
    {
        if ($this->exists($id)) {
            return touch($this->getFile($id));
        }
        return false;
    }

    public function validateId($id)
    {
        return $this->exists($id);
    }
}
