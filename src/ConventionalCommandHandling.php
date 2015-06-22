<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidHandler;

trait ConventionalCommandHandling
{
    /**
     * @param DomainCommand $command
     * @param Pbjx $pbjx
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleCommand(DomainCommand $command, Pbjx $pbjx)
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
