<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\EventSearch;

use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\Indexed;

final class EventIndexer implements EventSubscriber
{
    /**
     * The EventSearch will need context when indexing an
     * event.  The "tenant_id" field can be extracted
     * from the message if it exists and put it into the
     * context object.
     *
     * @var string
     */
    private $tenantIdField = null;

    /**
     * @param string $tenantIdField
     */
    public function __construct(?string $tenantIdField = null)
    {
        $this->tenantIdField = $tenantIdField;
    }

    /**
     * @param Indexed $event
     * @param Pbjx    $pbjx
     */
    public function onIndexed(Indexed $event, Pbjx $pbjx): void
    {
        if ($event->isReplay()) {
            return;
        }

        $context = [];
        if (null !== $this->tenantIdField && $event->has($this->tenantIdField)) {
            $context['tenant_id'] = (string)$event->get($this->tenantIdField);
        }

        $pbjx->getEventSearch()->indexEvents([$event], $context);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:indexed' => 'onIndexed',
        ];
    }
}
