<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch\Elastica;

use Gdbots\Pbj\Field;
use Gdbots\Pbj\Marshaler\Elastica\MappingBuilder as BaseMappingBuilder;
use Gdbots\Pbj\Schema;

class MappingBuilder extends BaseMappingBuilder
{
    const MAX_PATH_DEPTH = 2;

    protected function filterProperties(Schema $schema, Field $field, string $path, array $properties): array
    {
        if ($field->getName() === 'ctx_ua') {
            $properties['index'] = false;
            unset($properties['analyzer']);
            unset($properties['copy_to']);
            return $properties;
        }

        return $properties;
    }
}
