<?php

/**
 * @see       https://github.com/mezzio/mezzio for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Middleware;

use Mezzio\Exception\InvalidMiddlewareException;
use Mezzio\Middleware\ControllerMiddleware;
use Mezzio\MiddlewareContainer;
use MezzioTest\Middleware\TestAsset\Foo;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TypeError;

class ControllerMiddlewareTest extends TestCase
{
    /** @var MiddlewareContainer|ObjectProphecy */
    private $container;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var RequestHandlerInterface|ObjectProphecy */
    private $handler;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->request   = $this->prophesize(ServerRequestInterface::class)->reveal();
        $this->response  = $this->prophesize(ResponseInterface::class)->reveal();
        $this->handler   = $this->prophesize(RequestHandlerInterface::class)->reveal();
    }

    public function buildControllerMiddleware(callable $middleware)
    {
        return new ControllerMiddleware($this->container->reveal(), $middleware);
    }

    public function testProcessesControllerIntanceFromArrayDefinition()
    {
        $method = 'bar';

        $controller = $this->getMockBuilder(FooController::class)->setMethods([$method])->getMock();
        $controller->method($method)->with($this->request)->willReturn($this->response);

        $controllerMiddleware = $this->buildControllerMiddleware([$controller, $method]);

        self::assertSame(
            $controller->{$method}($this->request),
            $controllerMiddleware->process($this->request, $this->handler)
        );
    }

    public function testProcessesControllerPulledFromContainerFromArrayDefinition()
    {
        $fqcn = Foo::class;
        $method = 'bar';
        // class MezzioTest\Middleware\Controller\Foo must exist and msut have a callable method 'bar'
        $callable = [$fqcn, $method];

        $controller = $this->getMockBuilder($fqcn)->setMethods([$method])->getMock();
        $controller->method($method)->with($this->request)->willReturn($this->response);

        $this->container->has($fqcn)->willReturn(true);
        $this->container->get($fqcn)->willReturn($controller);

        $controllerMiddleware = $this->buildControllerMiddleware($callable);

        self::assertSame(
            $controller->{$method}($this->request),
            $controllerMiddleware->process($this->request, $this->handler)
        );
    }

    public function testProcessesControllerPulledFromContainerFromStringDefinition()
    {
        $fqcn = Foo::class;
        $method = 'bar';
        // class MezzioTest\Middleware\Controller\Foo must exist and msut have a callable method 'bar'
        $callable = "{$fqcn}::{$method}";

        $controller = $this->getMockBuilder($fqcn)->setMethods([$method])->getMock();
        $controller->method($method)->with($this->request)->willReturn($this->response);

        $this->container->has($fqcn)->willReturn(true);
        $this->container->get($fqcn)->willReturn($controller);

        $controllerMiddleware = $this->buildControllerMiddleware($callable);

        self::assertSame(
            $controller->{$method}($this->request),
            $controllerMiddleware->process($this->request, $this->handler)
        );
    }

    public function testProcessesNewControllerIntanceFromStringDefinition()
    {
        $fqcn = Foo::class;
        $method = 'bar';
        // class MezzioTest\Middleware\Controller\Foo must exist and msut have a callable method 'bar'
        $callable = "{$fqcn}::{$method}";

        $controller = $this->getMockBuilder($fqcn)->setMethods([$method])->getMock();
        $controller->method($method)->with($this->request)->willReturn($this->response);

        $this->container->has($fqcn)->willReturn(false);

        $controllerMiddleware = $this->buildControllerMiddleware($callable);

        self::assertInstanceOf(
            ResponseInterface::class,
            $controllerMiddleware->process($this->request, $this->handler)
        );
    }

    public function testDoesNotCatchTypeError()
    {
        $this->expectException(TypeError::class);
        $controllerMiddleware = $this->buildControllerMiddleware('foo::baz');
    }

    public function testDoesNotCatchContainerExceptionsForUnsupportedCallableString()
    {
        $this->expectException(InvalidMiddlewareException::class);
        $controllerMiddleware = $this->buildControllerMiddleware('strlen');
    }
}
