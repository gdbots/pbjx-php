<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Schemas\Pbjx\Request\EchoRequestV1;
use Gdbots\Schemas\Pbjx\Request\EchoResponseV1;

class EchoRequestHandler implements RequestHandler
{
    public static function handlesCuries(): array
    {
        return [EchoRequestV1::SCHEMA_CURIE];
    }

    public function handleRequest(Message $request, Pbjx $pbjx): Message
    {
        if ('fail' === $request->get(EchoRequestV1::MSG_FIELD)) {
            throw new \Exception('test fail');
        }

        return EchoResponseV1::create()->set(
            EchoResponseV1::MSG_FIELD,
            $request->get(EchoRequestV1::OCCURRED_AT_FIELD)->toDateTime()->format('U.u')
        );
    }
}
