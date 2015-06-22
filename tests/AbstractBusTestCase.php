<?php

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RegisteringServiceLocator;

abstract class AbstractBusTestCase extends \PHPUnit_Framework_TestCase
{
    /** @var RegisteringServiceLocator */
    protected $locator;

    /** @var Pbjx */
    protected $pbjx;

    protected function setup()
    {
        $this->locator = new RegisteringServiceLocator();
        $this->pbjx = $this->locator->getPbjx();
    }
}
