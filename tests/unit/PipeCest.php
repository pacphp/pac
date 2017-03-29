<?php
declare(strict_types = 1);

use Codeception\Example;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Pac\Pipe;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class PipeCest
{
    public function emptyMiddlewareCest(UnitTester $I)
    {
        $I->expectException(
            'RuntimeException',
            function () {
                (new Pipe())->process(new ServerRequest());
            }
        );
    }

    public function invalidMiddlewareCest(UnitTester $I)
    {
        $I->expectException(
            'InvalidArgumentException',
            function () {
                $pipe = new Pipe();
                $pipe->pipe('some_invalid_func');
                $pipe->process(new ServerRequest());
            }
        );
    }

    /**
     * @dataprovider provideProcessRequests
     */
    public function processCest(UnitTester $I, Example $example)
    {
        $request = $example['request'];

        $pipe = new Pipe();
        $pipe->pipe(
            function (ServerRequestInterface $request, DelegateInterface $delegate) {
                if ($request->getUri()->getPath() === '/login') {
                    $response = new Response();
                    $response->getBody()->write('Login');

                    return $response;
                }

                return $delegate->process($request);
            }
        );
        $pipe->pipe(
            function (ServerRequestInterface $request, DelegateInterface $delegate) {
                if ($request->getUri()->getPath() === '/logout') {
                    $response = new Response();
                    $response->getBody()->write('Logout');

                    return $response;
                }

                return $delegate->process($request);
            }
        );
        $pipe->pipe(
            function () {
                $response = new Response();
                $response->getBody()->write('Not Found');

                return $response->withStatus(404);
            }
        );
        $response = $pipe->process($request);
        $I->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        if ($request->getUri()->getPath() === '/login') {
            $I->assertEquals(200, $response->getStatusCode());
            $I->assertEquals('Login', (string)$response->getBody());
        } elseif ($request->getUri()->getPath() === '/logout') {
            $I->assertEquals(200, $response->getStatusCode());
            $I->assertEquals('Logout', (string)$response->getBody());
        } else {
            $I->assertEquals(404, $response->getStatusCode());
            $I->assertEquals('Not Found', (string)$response->getBody());
        }
    }

    public function testMiddlewareViaConstructor(UnitTester $I)
    {
        $pipe = new Pipe(
            [
                function (ServerRequestInterface $request, DelegateInterface $delegate) {
                    $response = $delegate->process($request);
                    $response->getBody()->write('!');

                    return $response;
                },
                function () {
                    $response = new Response();
                    $response->getBody()->write('Hello');

                    return $response;
                },
            ]
        );
        $response = $pipe->process(new ServerRequest());
        $I->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $I->assertEquals(200, $response->getStatusCode());
        $I->assertEquals('Hello!', (string)$response->getBody());
    }


    public function provideProcessRequests()
    {
        return [
            ['request' => new ServerRequest([], [], '/login', 'GET')],
            ['request' => new ServerRequest([], [], '/logout', 'GET')],
            ['request' => new ServerRequest([], [], '/dashboard', 'GET')],
            ['request' => new ServerRequest([], [], '/', 'GET')],
        ];
    }
}
