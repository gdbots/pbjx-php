<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbjx\CommandHandler;

final class InvalidHandler extends \UnexpectedValueException implements GdbotsPbjxException
{
    public static function forCommand(Command $command, CommandHandler $handler, $message = '')
    {
        return new static(
            sprintf(
                'The command [%s] could not be handled by [%s].  %s',
                $command::schema()->getId()->toString(),
                get_class($handler),
                $message
            )
        );
    }
}
