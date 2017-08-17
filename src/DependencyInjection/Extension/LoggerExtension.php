<?php
declare(strict_types=1);

namespace Pac\DependencyInjection\Extension;

use Monolog\Logger;
use Pac\Factory\LoggerFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class LoggerExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {
        $config = call_user_func_array('array_merge', $configs);
        $defaults = [
            'file_name' => 'app.log',
            'logs_dir' => $containerBuilder->getParameter('kernel.logs_dir'),
            'log_name' => 'app_logger',
        ];
        $arguments = array_merge($config, $defaults);

        $definition = (new Definition(Logger::class))
            ->setFactory([LoggerFactory::class, 'buildLogger'])
            ->setArguments([$arguments])
        ;
        $containerBuilder->setDefinition('logger', $definition);

        return $containerBuilder;
    }

    public function getAlias()
    {
        return 'logger';
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
