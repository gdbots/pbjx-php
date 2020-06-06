<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch\Elastica;

use Gdbots\Pbj\Field;
use Gdbots\Pbj\Marshaler\Elastica\MappingBuilder as BaseMappingBuilder;
use Gdbots\Pbj\Schema;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;

class MappingBuilder extends BaseMappingBuilder
{
    const MAX_PATH_DEPTH = 2;

    protected function filterProperties(Schema $schema, Field $field, string $path, array $properties): array
    {
        if ($field->getName() === EventV1Mixin::CTX_UA_FIELD) {
            $properties['index'] = false;
            unset($properties['analyzer']);
            unset($properties['copy_to']);
            return $properties;
        }

        return $properties;
    }
}
