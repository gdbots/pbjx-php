<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Schemas\Pbjx\Enum\Code;

final class HandlerNotFound extends \LogicException implements GdbotsPbjxException
{
    /** @var SchemaCurie */
    private $curie;

    /**
     * @param SchemaCurie $curie
     * @param \Throwable  $previous
     */
    public function __construct(SchemaCurie $curie, ?\Throwable $previous = null)
    {
        $this->curie = $curie;
        parent::__construct(
            sprintf('ServiceLocator did not find a handler for curie [%s].', $curie->toString()),
            Code::UNIMPLEMENTED,
            $previous
        );
    }

    /**
     * @return SchemaCurie
     */
    public function getSchemaCurie(): SchemaCurie
    {
        return $this->curie;
    }
}
