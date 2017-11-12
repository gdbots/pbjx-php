<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidHandler;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;

trait CommandHandlerTrait
{
    /**
     * @param Command $command
     * @param Pbjx    $pbjx
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleCommand(Command $command, Pbjx $pbjx): void
    {
        /** @var CommandHandlerTrait|CommandHandler $this */
        $method = $this->getMethodForCommand($command);

        if (empty($method)) {
            throw InvalidHandler::forCommand($command, $this, 'getMethodForCommand returned an empty string');
        }

        if (!is_callable([$this, $method])) {
            $message = <<<MSG
The CommandHandler needs the following code to operate:

    /**
     * @param Command \$command
     * @param Pbjx    \$pbjx
     *
     * @throws \Exception
     */
    protected function $method(Command \$command, Pbjx \$pbjx): void
    {
    }

MSG;
            throw InvalidHandler::forCommand($command, $this, $message);
        }

        $this->$method($command, $pbjx);
    }

    /**
     * Returns the method that should be called for the given command.
     *
     * @param Command $command
     *
     * @return string
     */
    protected function getMethodForCommand(Command $command): string
    {
        return 'handle';
    }
}
