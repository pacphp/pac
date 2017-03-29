<?php
declare(strict_types = 1);

namespace Pac\App;

use DateTime;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Pac\Pipe;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

abstract class PacKernel extends Kernel implements DelegateInterface
{
    protected $container;
    protected $environment;
    /** @var Pipe */
    protected $pipe;
    protected $pushedMiddleware = [];
    protected $rootDir;

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        // TODO: use DOTENV
        $loader->load($this->rootDir . '/config/config_' . $this->environment . '.yml');
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request): ResponseInterface
    {
        if (false === $this->booted) {
            $this->boot();
        }

        return $this->pipe->process($request);
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

    /**
     * Returns an array of bundles to register.
     *
     * @return BundleInterface[] An array of bundle instances
     */
    public function registerBundles()
    {
        // TODO: Remove
    }

    public function boot()
    {
        if (true === $this->booted) {
            return;
        }

        if ($this->loadClassCache) {
            $this->doLoadClassCache($this->loadClassCache[0], $this->loadClassCache[1]);
        }

        // init container
        $this->initializeContainer();

        $this->pipe = new Pipe(); // todo: load middleware from config

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
}
