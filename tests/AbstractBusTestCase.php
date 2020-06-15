<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RegisteringServiceLocator;
use PHPUnit\Framework\TestCase;

abstract class AbstractBusTestCase extends TestCase
{
    protected ?RegisteringServiceLocator $locator = null;
    protected ?Pbjx $pbjx = null;

    protected function setUp(): void
    {
        $this->locator = new RegisteringServiceLocator();
        $this->pbjx = $this->locator->getPbjx();
    }
}
