<?php
declare(strict_types=1);

namespace Pac\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class MiddlewareExtension extends ConfigurableExtension
{
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {
        $configuration = $this->getConfiguration($configs, $containerBuilder);
        $config = $this->processConfiguration($configuration, $configs);

        $config = call_user_func_array('array_merge', $configs);

        $containerBuilder->setDefinition('project.service.bar', new Definition('FooClass'));
        $containerBuilder->setParameter('project.parameter.bar', isset($config['foo']) ? $config['foo'] : 'foobar');

        $containerBuilder->setDefinition('project.service.foo', new Definition('FooClass'));
        $containerBuilder->setParameter('project.parameter.foo', isset($config['foo']) ? $config['foo'] : 'foobar');

        return $containerBuilder;
    }

    public function getAlias()
    {
        return 'middleware_pipe';
    }

    /**
     * Configures the passed container according to the merged configuration.
     *
     * @param array            $mergedConfig
     * @param ContainerBuilder $container
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        // TODO: Implement loadInternal() method.
    }
}
