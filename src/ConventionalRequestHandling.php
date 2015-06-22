<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidHandler;

trait ConventionalRequestHandling
{
    /**
     * @param DomainRequest $request
     * @param Pbjx $pbjx
     * @return DomainResponse
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleRequest(DomainRequest $request, Pbjx $pbjx)
    {
        $schema = $request::schema();
        $short = $schema->getClassShortName();
        $method = $schema->getHandlerMethodName();

        if (!is_callable([$this, $method])) {
            $message = <<<MSG
The RequestHandler needs the following code to operate:

    /**
     * @param $short \$request
     * @param Pbjx \$pbjx
     * @return Response
     *
     * @throws \Exception
     */
    protected function $method($short \$request, Pbjx \$pbjx)
    {
    }

MSG;
            /** @var RequestHandler $this */
            throw InvalidHandler::forRequest($request, $this, $message);
        }

        return $this->$method($request, $pbjx);
    }
}
