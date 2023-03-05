<?php

namespace De\Idrinth\WalledSecrets\API;

class FirefoxExtension
{
    public function get(): string
    {
        header('Content-Type: application/x-xpinstall', true, 200);
        #header('Content-Disposition: attachment; filename="idrinths-walled-secrets.xpi"');
        $name = tempnam(sys_get_temp_dir(), 'mozilla-');
        $zip = new \ZipArchive();
        $zip->open($name, ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addGlob(dirname(__DIR__, 2) . '/mozilla-addon/*/*', 0, ['remove_path' => dirname(__DIR__, 2) . '/mozilla-addon']);
        $zip->addFromString('manifest.json', str_replace('##HOSTNAME##', $_ENV['SYSTEM_HOSTNAME'], file_exists(dirname(__DIR__, 2) . '/mozilla-addon/manifest.json')));
        $zip->close();
        return file_get_contents($name);
    }
}
