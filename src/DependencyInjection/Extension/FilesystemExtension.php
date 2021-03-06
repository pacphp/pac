<?php
declare(strict_types=1);

namespace Pac\DependencyInjection\Extension;

use League\Flysystem\Filesystem;
use Pac\Factory\Filesystem\FilesystemFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class FilesystemExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = call_user_func_array('array_merge', $configs);

        foreach ($config as $label => $arguments) {
            $definition = (new Definition(Filesystem::class))
                ->setFactory([FilesystemFactory::class, 'create'])
                ->setArguments([$arguments]);
            if ($label !== 'default') {
                $definitionName = 'filesystem.' . $label;
            } else {
                $definitionName = 'filesystem';
            }
            $container->setDefinition($definitionName, $definition);
        }

        return $container;
    }

    public function getNamespace()
    {
    }

    public function getXsdValidationBasePath()
    {
    }

    public function getAlias()
    {
        return 'filesystem';
    }
}
