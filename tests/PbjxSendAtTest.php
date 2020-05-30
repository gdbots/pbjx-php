<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Scheduler\Scheduler;
use Gdbots\Tests\Pbjx\Fixtures\FakeCommand;

class PbjxSendAtTest extends AbstractBusTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $scheduler = new class implements Scheduler {
            public array $lastSendAt = [];
            public array $lastCancelJobs = [];

            public function createStorage(): void
            {
            }

            public function describeStorage(): string
            {
                return '';
            }

            public function sendAt(Message $command, int $timestamp, ?string $jobId = null): string
            {
                $this->lastSendAt = [
                    'command'   => $command,
                    'timestamp' => $timestamp,
                    'job_id'    => $jobId,
                ];

                return $jobId ?: 'jobid';
            }

            public function cancelJobs(array $jobIds): void
            {
                $this->lastCancelJobs = $jobIds;
            }
        };

        $this->locator->setScheduler($scheduler);
    }

    public function testWithValidInput()
    {
        $command = FakeCommand::create();
        $timestamp = strtotime('+1 month');
        $jobId = 'next-month';

        $actualJobId = $this->pbjx->sendAt($command, $timestamp, $jobId);
        $scheduler = $this->locator->getScheduler();

        $this->assertSame($jobId, $actualJobId, 'JobId returned should match input.');
        $this->assertTrue(
            $command->equals($scheduler->lastSendAt['command']),
            'Scheduled command should match input.'
        );
        $this->assertSame(
            $timestamp,
            $scheduler->lastSendAt['timestamp'],
            'Scheduled timestamp should match input.'
        );
    }

    /**
     * @expectedException \Gdbots\Pbjx\Exception\LogicException
     */
    public function testWithTimestampInPast()
    {
        $command = FakeCommand::create();
        $this->pbjx->sendAt($command, strtotime('-1 second'), 'mcfly');
    }
}
