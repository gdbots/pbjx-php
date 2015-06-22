<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\MessageCurie;

final class HandlerNotFound extends \LogicException implements GdbotsPbjxException
{
    /** @var MessageCurie */
    private $curie;

    /**
     * @param MessageCurie $curie
     */
    public function __construct(MessageCurie $curie)
    {
        $this->curie = $curie;
        parent::__construct(
            sprintf('ServiceLocator did not find a handler for curie [%s].', $curie->toString())
        );
    }

    /**
     * @return MessageCurie
     */
    public function getMessageCurie()
    {
        return $this->curie;
    }
}
