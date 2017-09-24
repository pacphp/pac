<?php
declare(strict_types = 1);

namespace Pac\App;

use DateTime;
use Exception;
use Interop\Http\Server\RequestHandlerInterface;
use Pac\DependencyInjection\Extension\CommandExtension;
use Pac\DependencyInjection\Extension\LoggerExtension;
use Psr\Container\ContainerInterface;
use Pac\DependencyInjection\Extension\MiddlewareExtension;
use Pac\Pipe;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\GlobFileLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Dotenv\Dotenv;

abstract class PacKernel implements RequestHandlerInterface
{
    const VERSION = '0.0.2';

    protected $appDir;
    protected $booted = false;
    /** @var ContainerInterface */
    protected $container;
    protected $debug;
    protected $environment;
    /** @var Extension[] */
    protected $extensions;
    protected $name;
    /** @var Pipe */
    protected $pipe;
    protected $pushedMiddleware = [];
    protected $rootDir;

    public function __construct($dotenvFile = '.env')
    {
        $this->rootDir = $this->getRootDir();
        $this->name = $this->getName();
        $dotenv = new Dotenv();
        $dotenv->load($this->rootDir . '/' . $dotenvFile);

        $this->environment = getenv('ENV') ?: 'prod';
        $this->debug = (bool) getenv('DEBUG');

        if ($this->debug) {
            $this->startTime = microtime(true);
        }

        foreach ($this->appendedExtensions() as $extension) {
            $this->appendExtension($extension);
        }
    }

    public function appendExtension(ExtensionInterface $extension)
    {
        $this->extensions[] = $extension;
    }

    public function getCacheDir()
    {
        return $this->getVarDir() . '/cache/' . $this->environment;
    }

    public function getCharset()
    {
        return 'UTF-8';
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function getName()
    {
        if (null === $this->name) {
            $this->name = preg_replace('/[^a-zA-Z0-9_]+/', '', basename($this->rootDir));
            if (ctype_digit($this->name[0])) {
                $this->name = '_'.$this->name;
            }
        }

        return $this->name;
    }

    public function getConfigDir()
    {
        return $this->getEtcDir() . '/config';
    }

    public function getEtcDir()
    {
        return $this->getRootDir() . '/etc';
    }

    public function getLogDir()
    {
        return $this->getVarDir() . '/logs';
    }

    public function getSrcDir()
    {
        return $this->getRootDir() . '/src';
    }

    public function getVarDir()
    {
        return $this->getRootDir() . '/var';
    }

    public function getRootDir()
    {
        if (null === $this->rootDir) {
            $r = new \ReflectionObject($this);
            $this->rootDir = realpath(dirname($r->getFileName())  . '/..');
        }

        return $this->rootDir;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getConfigDir() . '/config.yml');
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (false === $this->booted) {
            $this->boot();
        }

        try {
            return $this->pipe->handle($request);
        } catch (Exception $e) {
            $this->getContainer()->get('logger')->error($e);
        }
    }

    public function push(/*$kernelClass, $args...*/)
    {
        if (func_num_args() === 0) {
            throw new \InvalidArgumentException("Missing argument(s) when calling push");
        }

        foreach (func_get_args() as $middleware) {
            $this->pushedMiddleware[] = $middleware;
        }

        return $this;
    }

    public function boot()
    {
        if (true === $this->booted) {
            return;
        }

        // init container
        $this->initializeContainer();

        $this->pipe = $this->container->get('kernel.pipe');

        foreach ($this->pushedMiddleware as $middleware) {
            $this->pipe->pipe($middleware);
        }

        $this->booted = true;
    }

    /**
     * Sends HTTP headers and content.
     *
     */
    public function send(ResponseInterface $response)
    {
        $this->sendHeaders($response);
        $this->sendContent($response);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif ('cli' !== PHP_SAPI) {
            static::closeOutputBuffers(0, true);
        }

        return $this;
    }

    /**
     * Cleans or flushes output buffers up to target level.
     *
     * Resulting level can be greater than target level if a non-removable buffer has been encountered.
     *
     * @param int  $targetLevel The target output buffering level
     * @param bool $flush       Whether to flush or clean the buffers
     */
    public static function closeOutputBuffers($targetLevel, $flush)
    {
        $status = ob_get_status(true);
        $level = count($status);
        // PHP_OUTPUT_HANDLER_* are not defined on HHVM 3.3
        $flags = defined('PHP_OUTPUT_HANDLER_REMOVABLE') ? PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE) : -1;

        while ($level-- > $targetLevel && ($s = $status[$level]) && (!isset($s['del']) ? !isset($s['flags']) || $flags === ($s['flags'] & $flags) : $s['del'])) {
            if ($flush) {
                ob_end_flush();
            } else {
                ob_end_clean();
            }
        }
    }

    /**
     * Sends HTTP headers.
     */
    public function sendHeaders(ResponseInterface $response): self
    {
        // headers have already been sent by the developer
        if (headers_sent()) {
            return $this;
        }

        $headers = $response->getHeaders();
        if (!isset($headers['date'])) {
            $date = new DateTime();
            $date->setTimezone(new \DateTimeZone('UTC'));
            $headers['date'][] = $date->format('D, d M Y H:i:s').' GMT';
        }
        $statusCode = $response->getStatusCode();

        // headers
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                header($name.': '.$value, false, $statusCode);
            }
        }

        // status
        header(sprintf('HTTP/%s %s %s', $response->getProtocolVersion(), $statusCode, $response->getReasonPhrase()), true, $statusCode);

        // todo: cookies

        return $this;
    }

    /**
     * Sends content for the current web response.
     *
     * @return $this
     */
    public function sendContent(ResponseInterface $response): self
    {
        echo $response->getBody()->getContents();

        return $this;
    }

    protected function appendedExtensions(): array
    {
        return [];
    }

    /**
     * Use this method to register compiler passes and manipulate the container during the building process.
     */
    protected function build(ContainerBuilder $container)
    {
    }

    protected function buildContainer(): ContainerBuilder
    {
        foreach (array('cache' => $this->getCacheDir(), 'logs' => $this->getLogDir()) as $name => $dir) {
            if (!is_dir($dir)) {
                if (false === @mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new \RuntimeException(sprintf("Unable to create the %s directory (%s)\n", $name, $dir));
                }
            } elseif (!is_writable($dir)) {
                throw new \RuntimeException(sprintf("Unable to write in the %s directory (%s)\n", $name, $dir));
            }
        }

        $container = $this->getContainerBuilder();
        $container->addObjectResource($this);

        $kernelExtensions = $this->getKernelExtensions();
        foreach ($kernelExtensions as $extension) {
            $container->registerExtension($extension);
        }

        foreach ($this->extensions as $extension) {
            $container->registerExtension($extension);
        }

        $this->prepareContainer($container);

        if (null !== $cont = $this->registerContainerConfiguration($this->getContainerLoader($container))) {
            $container->merge($cont);
        }

        return $container;
    }

    /**
     * Dumps the service container to PHP code in the cache.
     *
     * @param ConfigCache      $cache     The config cache
     * @param ContainerBuilder $container The service container
     * @param string           $class     The name of the class to generate
     * @param string           $baseClass The name of the container's base class
     */
    protected function dumpContainer(ConfigCache $cache, ContainerBuilder $container, $class, $baseClass)
    {
        // cache the container
        $dumper = new PhpDumper($container);

        if (class_exists('ProxyManager\Configuration') && class_exists('Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper')) {
            $dumper->setProxyDumper(new ProxyDumper(md5($cache->getPath())));
        }

        $content = $dumper->dump(array('class' => $class, 'base_class' => $baseClass, 'file' => $cache->getPath(), 'debug' => $this->debug));

        $cache->write($content, $container->getResources());
    }

    /**
     * Gets the container's base class.
     *
     * All names except Container must be fully qualified.
     */
    protected function getContainerBaseClass(): string
    {
        return 'Container';
    }

    /**
     * Gets a new ContainerBuilder instance used to build the service container.
     */
    protected function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->getParameterBag()->add($this->getKernelParameters());

        if (class_exists('ProxyManager\Configuration') && class_exists('Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator')) {
            $container->setProxyInstantiator(new RuntimeInstantiator());
        }

        return $container;
    }

    /**
     * Gets the container class.
     */
    protected function getContainerClass(): string
    {
        return $this->name.ucfirst($this->environment).($this->debug ? 'Debug' : '').'ProjectContainer';
    }

    protected function getContainerLoader(ContainerBuilder $containerBuilder): DelegatingLoader
    {
        $locator = new FileLocator();
        $resolver = new LoaderResolver(
            [
                new YamlFileLoader($containerBuilder, $locator),
                new PhpFileLoader($containerBuilder, $locator),
                new GlobFileLoader($locator),
                new DirectoryLoader($containerBuilder, $locator),
                new ClosureLoader($containerBuilder),
            ]
        );

        return new DelegatingLoader($resolver);
    }

    protected function getKernelExtensions(): array
    {
        return [
            new CommandExtension(),
            new LoggerExtension(),
            new MiddlewareExtension(),
        ];
    }

    protected function getKernelParameters(): array
    {
        return [
            'kernel.cache_dir'       => realpath($this->getCacheDir()) ?: $this->getCacheDir(),
            'kernel.charset'         => $this->getCharset(),
            'kernel.config_dir'      => realpath($this->getConfigDir()) ?: $this->getConfigDir(),
            'kernel.container_class' => $this->getContainerClass(),
            'kernel.debug'           => $this->debug,
            'kernel.environment'     => $this->environment,
            'kernel.etc_dir'         => realpath($this->getEtcDir()) ?: $this->getEtcDir(),
            'kernel.logs_dir'        => realpath($this->getLogDir()) ?: $this->getLogDir(),
            'kernel.name'            => $this->name,
            'kernel.root_dir'        => realpath($this->rootDir) ?: $this->rootDir,
            'kernel.src_dir'         => realpath($this->getSrcDir()) ?: $this->getSrcDir(),
        ];
    }

    /**
     * Initializes the service container.
     *
     * The cached version of the service container is used when fresh, otherwise the
     * container is built.
     */
    protected function initializeContainer()
    {
        $class = $this->getContainerClass();
        $cache = new ConfigCache($this->getCacheDir().'/'.$class.'.php', $this->debug);
        $fresh = true;
        if (!$cache->isFresh()) {
            $container = $this->buildContainer();
            $container->compile();
            $this->dumpContainer($cache, $container, $class, $this->getContainerBaseClass());

            $fresh = false;
        }

        require_once $cache->getPath();

        $this->container = new $class();
        $this->container->set('kernel', $this);

        if (!$fresh && $this->container->has('cache_warmer')) {
            $this->container->get('cache_warmer')->warmUp($this->container->getParameter('kernel.cache_dir'));
        }
    }

    /**
     * Prepares the ContainerBuilder before it is compiled.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function prepareContainer(ContainerBuilder $container)
    {
        $this->build($container);
    }
}
