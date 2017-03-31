<?php
declare(strict_types=1);

namespace Pac\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

class MiddlewareExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {
        $config = call_user_func_array('array_merge', $configs);
        $config += ['class' => 'Pac\Pipe'];

        $pipeReferences = [];
        foreach( $config['middlewares'] as $middlewareId) {
            $pipeReferences[] = new Reference(implode('', explode('@', $middlewareId, 2)));
        }
        $containerBuilder
            ->register('kernel.pipe', $config['class'])
            ->addArgument($pipeReferences);

        return $containerBuilder;
    }

    public function getAlias()
    {
        return 'middleware_pipe';
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
