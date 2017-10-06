<?php
declare(strict_types=1);

namespace Pac\Factory\Filesystem;

use Codeception\Exception\ConfigurationException;
use League\Flysystem\Filesystem;

class FilesystemFactory
{
    public static function create($config): Filesystem
    {
        $factoryClass = 'Pac\\Factory\\Filesystem\\' . $config['adapter'] . 'Factory';

        if (!class_exists($factoryClass)) {
           throw new ConfigurationException('There is no factory for the adapter ' . $config['adapter'] . ' filesystem.');
        }

        return (new $factoryClass())->create($config['config']);
    }
}
