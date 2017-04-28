<?php
declare(strict_types=1);

namespace Pac\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

class CommandExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {
        $config = call_user_func_array('array_merge', $configs);


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
