<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Schemas\Pbjx\Enum\Code;

final class InvalidHandler extends \UnexpectedValueException implements GdbotsPbjxException
{
    public static function forCommand(Message $command, CommandHandler $handler, string $message = ''): self
    {
        return new static(
            sprintf(
                'The command [%s] could not be handled by [%s]. %s',
                $command::schema()->getId()->toString(),
                $handler::class,
                $message
            ),
            Code::INTERNAL->value
        );
    }

    public static function forRequest(Message $request, RequestHandler $handler, string $message = ''): self
    {
        return new static(
            sprintf(
                'The request [%s] could not be handled by [%s].  %s',
                $request::schema()->getId()->toString(),
                $handler::class,
                $message
            ),
            Code::INTERNAL->value
        );
    }
}
