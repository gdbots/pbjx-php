<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\DependencyInjection;

use Gdbots\Pbj\SchemaCurie;

/** Marker interface for pbjx handlers (command/request) */
interface PbjxHandler
{
    /**
     * Returns an array of curies that the given handler is able to handle.
     * In most cases handlers only handle one curie but in some advanced
     * scenarios (typically involving mixins) a handler can be used to
     * handle many different concrete schemas.
     *
     * The curies returned should be concrete schemas since the curie for
     * a mixin would never be able to be handled as instances of mixins
     * cannot be created.
     *
     * @return SchemaCurie|string[]
     */
    public static function handlesCuries(): array;
}
