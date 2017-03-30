<?php
declare(strict_types=1);

namespace Pac\Middleware;

use Exception;
use GraphQL\GraphQL;
use GraphQL\Schema;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Oscar\GraphQL\AppContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

class GraphQLMiddleware implements MiddlewareInterface
{
    protected $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        try {
            // TODO: this needs to be a closure
            $appContext = new AppContext();
            $appContext->request = $request;
            $appContext->user = null; //active user

            $content = json_decode($request->getBody()->getContents(), true);
            $content += ['query' => null, 'variables' => null];
            $result = GraphQL::execute(
                $this->schema,
                $content['query'],
                null,
                $appContext,
                (array) $content['variables']
            );

        } catch (Exception $e) {
            $result = [
                'error' => [
                    'message' => $e->getMessage()
                ]
            ];
        }

        $response = new JsonResponse($result);

        return $response;
    }
}
