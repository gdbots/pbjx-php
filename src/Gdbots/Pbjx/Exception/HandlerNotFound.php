<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\SchemaId;

class HandlerNotFound extends \LogicException implements GdbotsPbjxException
{
    /** @var SchemaId */
    private $schemaId;

    /**
     * @param SchemaId $schemaId
     */
    public function __construct(SchemaId $schemaId)
    {
        $this->schemaId = $schemaId;
        parent::__construct(
            sprintf('ServiceLocator did not find a handler for schema id [%s].', $schemaId->toString())
        );
    }

    /**
     * @return SchemaId
     */
    public function getSchemaId()
    {
        return $this->schemaId;
    }
}
