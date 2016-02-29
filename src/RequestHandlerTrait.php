<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidHandler;
use Gdbots\Schemas\Pbj\Request\Request;
use Gdbots\Schemas\Pbj\Request\Response;

trait RequestHandlerTrait
{
    /**
     * @param Request $request
     * @param Pbjx $pbjx
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleRequest(Request $request, Pbjx $pbjx)
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
     * @param Pbjx \$pbjx
     * @return Response
     *
     * @throws \Exception
     */
    protected function $method(Request \$request, Pbjx \$pbjx)
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
     * @return string
     */
    protected function getMethodForRequest(Request $request)
    {
        return 'handle';
    }
}
