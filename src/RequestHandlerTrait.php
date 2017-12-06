<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidHandler;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;

trait RequestHandlerTrait
{
    /**
     * @param Request $request
     * @param Pbjx    $pbjx
     *
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleRequest(Request $request, Pbjx $pbjx): Response
    {
        /** @var RequestHandlerTrait|RequestHandler $this */
        $method = $this->getMethodForRequest($request);

        if (empty($method)) {
            throw InvalidHandler::forRequest($request, $this, 'getMethodForRequest returned an empty string');
        }

        if (!is_callable([$this, $method])) {
            $message = <<<MSG
The RequestHandler needs the following code to operate:

    /**
     * @param Request \$request
     * @param Pbjx    \$pbjx
     *
     * @return Response
     *
     * @throws \Exception
     */
    protected function $method(Request \$request, Pbjx \$pbjx): Response
    {
    }

MSG;
            throw InvalidHandler::forRequest($request, $this, $message);
        }

        return $this->$method($request, $pbjx);
    }

    /**
     * Returns the method that should be called for the given request.
     *
     * @param Request $request
     *
     * @return string
     */
    protected function getMethodForRequest(Request $request): string
    {
        return 'handle';
    }

    /**
     * @see PbjxHandler::handlesCuries()
     *
     * @return SchemaCurie[]
     */
    public static function handlesCuries(): array
    {
        return [];
    }
}
