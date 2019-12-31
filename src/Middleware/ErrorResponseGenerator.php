<?php

/**
 * @see       https://github.com/mezzio/mezzio for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Middleware;

use Laminas\Stratigility\Utils;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ErrorResponseGenerator
{
    const TEMPLATE_DEFAULT = 'error::error';

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * @var string
     */
    private $stackTraceTemplate = <<<'EOT'
%s raised in file %s line %d:
Message: %s
Stack Trace:
%s

EOT;

    /**
     * @var string
     */
    private $template;

    public function __construct(
        bool $isDevelopmentMode = false,
        TemplateRendererInterface $renderer = null,
        string $template = self::TEMPLATE_DEFAULT
    ) {
        $this->debug     = (bool) $isDevelopmentMode;
        $this->renderer  = $renderer;
        $this->template  = $template;
    }

    public function __invoke(
        Throwable $e,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        $response = $response->withStatus(Utils::getStatusCode($e, $response));

        if ($this->renderer) {
            return $this->prepareTemplatedResponse($e, $request, $response);
        }

        return $this->prepareDefaultResponse($e, $response);
    }

    private function prepareTemplatedResponse(
        Throwable $e,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        $templateData = [
            'response' => $response,
            'request'  => $request,
            'uri'      => (string) $request->getUri(),
            'status'   => $response->getStatusCode(),
            'reason'   => $response->getReasonPhrase(),
        ];

        if ($this->debug) {
            $templateData['error'] = $e;
        }

        $response->getBody()->write(
            $this->renderer->render($this->template, $templateData)
        );

        return $response;
    }

    private function prepareDefaultResponse(Throwable $e, ResponseInterface $response) : ResponseInterface
    {
        $message = 'An unexpected error occurred';

        if ($this->debug) {
            $message .= "; strack trace:\n\n" . $this->prepareStackTrace($e);
        }

        $response->getBody()->write($message);

        return $response;
    }

    /**
     * Prepares a stack trace to display.
     */
    private function prepareStackTrace(Throwable $e) : string
    {
        $message = '';
        do {
            $message .= sprintf(
                $this->stackTraceTemplate,
                get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage(),
                $e->getTraceAsString()
            );
        } while ($e = $e->getPrevious());

        return $message;
    }
}
