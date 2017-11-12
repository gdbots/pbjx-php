<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;

final class InvalidHandler extends \UnexpectedValueException implements GdbotsPbjxException
{
    /**
     * @param Command        $command
     * @param CommandHandler $handler
     * @param string         $message
     *
     * @return self
     */
    public static function forCommand(Command $command, CommandHandler $handler, string $message = ''): self
    {
        return new static(
            sprintf(
                'The command [%s] could not be handled by [%s].  %s',
                $command::schema()->getId()->toString(),
                get_class($handler),
                $message
            ),
            Code::INTERNAL
        );
    }

    /**
     * @param Request        $request
     * @param RequestHandler $handler
     * @param string         $message
     *
     * @return self
     */
    public static function forRequest(Request $request, RequestHandler $handler, string $message = ''): self
    {
        return new static(
            sprintf(
                'The request [%s] could not be handled by [%s].  %s',
                $request::schema()->getId()->toString(),
                get_class($handler),
                $message
            ),
            Code::INTERNAL
        );
    }
}
