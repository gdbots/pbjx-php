<?php

namespace Gdbots\Tests\Pbjx\Mock;

use Gdbots\Pbjx\Dispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

class DispatcherMock extends EventDispatcher implements Dispatcher
{
}
