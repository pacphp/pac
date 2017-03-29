<?php
declare(strict_types = 1);

namespace Pac;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Pipe implements DelegateInterface
{
    protected $middleware = [];
    protected $position = 0;

    public function __construct($middleware = [])
    {
        array_map([$this, 'pipe'], $middleware);
    }

    public function pipe($middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * Dispatch the next available middleware and return the response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request): ResponseInterface
    {
        if (empty($this->middleware[$this->position])) {
            throw new \RuntimeException('Pipeline ended without returning any Psr\\Http\\Message\\ResponseInterface');
        }
        $middleware = $this->middleware[$this->position];
        $next = clone $this;
        $next->position++;
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $next);
        } elseif (is_callable($middleware)) {
            return call_user_func($middleware, $request, $next);
        }

        throw new \InvalidArgumentException(
            sprintf(
                "Middleware must either be an instance of '%s' or a valid callable; '%s' given",
                'Interop\\Http\\ServerMiddleware\\MiddlewareInterface',
                is_object($middleware) ? get_class($middleware) : gettype($middleware)
            )
        );
    }
}
