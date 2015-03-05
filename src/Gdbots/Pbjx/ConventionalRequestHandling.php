<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\Response;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidHandler;

trait ConventionalRequestHandling
{
    /**
     * @param Request $request
     * @param Pbjx $pbjx
     * @param Notifier $notifier
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleRequest(Request $request, Pbjx $pbjx, Notifier $notifier)
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
     * @param Notifier \$notifier
     * @return Response
     *
     * @throws \Exception
     */
    protected function $method($short \$request, Pbjx \$pbjx, Notifier \$notifier)
    {
    }

MSG;
            /** @var RequestHandler $this */
            throw InvalidHandler::forRequest($request, $this, $message);
        }

        return $this->$method($request, $pbjx, $notifier);
    }
}
