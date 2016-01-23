<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidHandler;
use Gdbots\Schemas\Pbj\Command\Command;

trait ConventionalCommandHandling
{
    /**
     * @param Command $command
     * @param Pbjx $pbjx
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleCommand(Command $command, Pbjx $pbjx)
    {
        $schema = $command::schema();
        $short = $schema->getClassShortName();
        $method = $schema->getHandlerMethodName();

        if (!is_callable([$this, $method])) {
            $message = <<<MSG
The CommandHandler needs the following code to operate:

    /**
     * @param $short \$command
     * @param Pbjx \$pbjx
     *
     * @throws \Exception
     */
    protected function $method($short \$command, Pbjx \$pbjx)
    {
    }

MSG;
            /** @var CommandHandler $this */
            throw InvalidHandler::forCommand($command, $this, $message);
        }

        $this->$method($command, $pbjx);
    }
}
