<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\RequestHandler;

final class InvalidHandler extends \UnexpectedValueException implements GdbotsPbjxException
{
    /**
     * @param DomainCommand $command
     * @param CommandHandler $handler
     * @param string $message
     * @return static
     */
    public static function forCommand(DomainCommand $command, CommandHandler $handler, $message = '')
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

    /**
     * @param DomainRequest $request
     * @param RequestHandler $handler
     * @param string $message
     * @return static
     */
    public static function forRequest(DomainRequest $request, RequestHandler $handler, $message = '')
    {
        return new static(
            sprintf(
                'The request [%s] could not be handled by [%s].  %s',
                $request::schema()->getId()->toString(),
                get_class($handler),
                $message
            )
        );
    }
}
