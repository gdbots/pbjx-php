<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Exception\LogicException;
use Gdbots\Pbjx\Scheduler\Scheduler;
use Gdbots\Schemas\Pbjx\Command\CheckHealthV1;

class PbjxSendAtTest extends AbstractBusTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $scheduler = new class implements Scheduler {
            public array $lastSendAt = [];
            public array $lastCancelJobs = [];

            public function createStorage(array $context = []): void
            {
            }

            public function describeStorage(array $context = []): string
            {
                return '';
            }

            public function sendAt(Message $command, int $timestamp, ?string $jobId = null, array $context = []): string
            {
                $this->lastSendAt = [
                    'command'   => $command,
                    'timestamp' => $timestamp,
                    'job_id'    => $jobId,
                ];

                return $jobId ?: 'jobid';
            }

            public function cancelJobs(array $jobIds, array $context = []): void
            {
                $this->lastCancelJobs = $jobIds;
            }
        };

        $this->locator->setScheduler($scheduler);
    }

    public function testWithValidInput(): void
    {
        $command = CheckHealthV1::create();
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

    public function testWithTimestampInPast(): void
    {
        $this->expectException(LogicException::class);
        $command = CheckHealthV1::create();
        $this->pbjx->sendAt($command, strtotime('-1 second'), 'mcfly');
    }
}
