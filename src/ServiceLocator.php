<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Scheduler\Scheduler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

interface ServiceLocator
{
    public function getPbjx(): Pbjx;

    public function getDispatcher(): EventDispatcherInterface;

    public function getCommandBus(): CommandBus;

    public function getEventBus(): EventBus;

    public function getRequestBus(): RequestBus;

    public function getExceptionHandler(): ExceptionHandler;

    public function getCommandHandler(SchemaCurie $curie): CommandHandler;

    public function getRequestHandler(SchemaCurie $curie): RequestHandler;

    public function getEventStore(): EventStore;

    public function getEventSearch(): EventSearch;

    public function getScheduler(): Scheduler;
}
