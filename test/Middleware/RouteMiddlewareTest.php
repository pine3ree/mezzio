<?php

/**
 * @see       https://github.com/mezzio/mezzio for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Middleware;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Mezzio\Middleware\RouteMiddleware;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RouteMiddlewareTest extends TestCase
{
    /** @var RouterInterface|ObjectProphecy */
    private $router;

    /** @var ResponseInterface|ObjectProphecy */
    private $response;

    /** @var RouteMiddleware */
    private $middleware;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var RequestHandlerInterface|ObjectProphecy */
    private $handler;

    public function setUp()
    {
        $this->router     = $this->prophesize(RouterInterface::class);
        $this->response   = $this->prophesize(ResponseInterface::class);
        $this->middleware = new RouteMiddleware(
            $this->router->reveal(),
            $this->response->reveal()
        );

        $this->request  = $this->prophesize(ServerRequestInterface::class);
        $this->handler = $this->prophesize(RequestHandlerInterface::class);
    }

    public function testRoutingFailureDueToHttpMethodCallsNextWithNotAllowedResponseAndError()
    {
        $result = RouteResult::fromRouteFailure(['GET', 'POST']);

        $this->router->match($this->request->reveal())->willReturn($result);
        $this->handler->handle()->shouldNotBeCalled();
        $this->request->withAttribute()->shouldNotBeCalled();
        $this->response->withStatus(StatusCode::STATUS_METHOD_NOT_ALLOWED)->will([$this->response, 'reveal']);
        $this->response->withHeader('Allow', 'GET,POST')->will([$this->response, 'reveal']);

        $response = $this->middleware->process($this->request->reveal(), $this->handler->reveal());
        $this->assertSame($response, $this->response->reveal());
    }

    public function testGeneralRoutingFailureInvokesDelegateWithSameRequest()
    {
        $result = RouteResult::fromRouteFailure(Route::HTTP_METHOD_ANY);

        $this->router->match($this->request->reveal())->willReturn($result);
        $this->response->withStatus()->shouldNotBeCalled();
        $this->response->withHeader()->shouldNotBeCalled();
        $this->request->withAttribute()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->handler->handle($this->request->reveal())->willReturn($expected);

        $response = $this->middleware->process($this->request->reveal(), $this->handler->reveal());
        $this->assertSame($expected, $response);
    }

    public function testRoutingSuccessDelegatesToNextAfterFirstInjectingRouteResultAndAttributesInRequest()
    {
        $middleware = $this->prophesize(MiddlewareInterface::class)->reveal();
        $parameters = ['foo' => 'bar', 'baz' => 'bat'];
        $result = RouteResult::fromRoute(
            new Route('/foo', $middleware),
            $parameters
        );

        $this->router->match($this->request->reveal())->willReturn($result);

        $this->request
            ->withAttribute(RouteResult::class, $result)
            ->will([$this->request, 'reveal']);
        $this->request
            ->withAttribute(\Zend\Expressive\Router\RouteResult::class, $result)
            ->will([$this->request, 'reveal']);
        foreach ($parameters as $key => $value) {
            $this->request->withAttribute($key, $value)->will([$this->request, 'reveal']);
        }

        $this->response->withStatus()->shouldNotBeCalled();
        $this->response->withHeader()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->handler
            ->handle($this->request->reveal())
            ->willReturn($expected);

        $response = $this->middleware->process($this->request->reveal(), $this->handler->reveal());
        $this->assertSame($expected, $response);
    }
}
