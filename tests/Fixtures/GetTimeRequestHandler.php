<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;

class GetTimeRequestHandler implements RequestHandler
{
    public static function handlesCuries(): array
    {
        return ['gdbots:tests.pbjx:fixtures:get-time-request'];
    }

    public function handleRequest(Message $request, Pbjx $pbjx): Message
    {
        if ($request->get('test_fail')) {
            throw new \Exception('test fail');
        }

        return GetTimeResponse::create()->set('time', $request->get('occurred_at')->toDateTime());
    }
}
