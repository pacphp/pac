<?php
declare(strict_types=1);

namespace Pac\DependencyInjection\Extension;

use Pac\Factory\CommandFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class CommandExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {
        $config = call_user_func_array('array_merge', $configs);

        $commands = [];
        foreach ($config as $command) {
            $commandDefinition = (new Definition($command));
            $containerBuilder->setDefinition($command, $commandDefinition);
            $commands[] = $command;
        }
        $definition = (new Definition(CommandFactory::class))
            ->setFactory([CommandFactory::class, 'create'])
            ->setArguments([$commands])
        ;
        $containerBuilder->setDefinition('console.commands', $definition);

        return $containerBuilder;
    }

    public function getAlias()
    {
        return 'commands';
    }

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        // TODO: Implement getNamespace() method.
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        // TODO: Implement getXsdValidationBasePath() method.
    }
}
