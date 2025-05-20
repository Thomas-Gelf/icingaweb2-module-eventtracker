<?php

namespace Icinga\Tests\Module\Eventtracker\Engine\TimePeriod;

use Icinga\Module\Eventtracker\Engine\Downtime\DowntimePeriodRunner;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class DowntimePeriodRunnerTest extends TestCase
{
    public function testCalculatesCurrentSlot()
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        DowntimePeriodRunner::setFakeNow(new \DateTime('2025-05-08 12:02:00'));
        $runner = new DowntimePeriodRunner($mockLogger);
        $rule = new DowntimeRule();
        $rule->setProperties([
            'uuid'              => Uuid::fromString('ab8438fb-6da3-40f7-83bf-b83e0854ad98')->getBytes(),
            'time_definition'   => '0,5,10,15,20,25,30,35,40,45,50,55 * * * *',
            'filter_definition' => 'a=b',
            'message'           => 'Two minutes Downtime every 5 minutes',
            'config_uuid'       => Uuid::fromString('B17C20FAF93A5B6A9B2F40CF1FD7273F')->getBytes(),
            'is_enabled'        => 'y',
            'is_recurring'      => 'y',
            'ts_not_before'     => 1680116280000,
            'duration'          => 180,
            'max_single_problem_duration'   => 120,
            'on_iteration_end_issue_status' => 'open',
            'timezone'                      => 'Europe/Berlin',
        ]);
        $rule->setStored();
        $runner->setRules([
            Uuid::fromBytes($rule->get('config_uuid'))->toString() => $rule
        ]);
        $active = $runner->getActiveTimeSlots();
        $this->assertEquals(1, count($active));
        $first = $active[array_key_first($active)];
        $this->assertEquals(new \DateTime('2025-05-08 12:00:00'), $first->start);
        $this->assertEquals(new \DateTime('2025-05-08 12:03:00'), $first->end);
        $runner->stop();
    }
}
