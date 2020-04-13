<?php

/**
 * @see       https://github.com/mezzio/mezzio for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Middleware;

use Mezzio\Exception\InvalidMiddlewareException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_array;
use function is_string;
use function explode;
use function strpos;

class ControllerMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var string|object
     */
    private $controller;

    /**
     * @var string
     */
    private $method;

    public function __construct(
        ContainerInterface $container,
        callable $middleware
    ) {
        $this->container = $container;
        $this->resolve($middleware);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        if (is_string($this->controller)) {
            // try pulling from the container and fallback to direct instantiation
            if ($this->container->has($this->controller)) {
                $this->controller = $this->container->get($this->controller);
            } else {
                $this->controller = new $this->controller;
            }
        }

        return $this->controller->{$this->method}($request);
    }

    /**
     * Resolve the middleware definition into class/object and method properties
     *
     * @throws InvalidMiddlewareException for invalid controller/method pair
     */
    private function resolve(callable $middleware)
    {
        if (is_string($middleware) && strpos($middleware, '::')) {
            $middleware = explode('::', $middleware);
        }

        if (! is_array($middleware)) {
            throw new InvalidMiddlewareException(
                "A callable controller-middleware must defined either in array "
                . "form or in string form with FQCN and method name separated "
                . "by a double colon!"
            );
        }

        $this->controller = $middleware[0];
        $this->method     = $middleware[1];
    }
}
