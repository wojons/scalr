<?php

namespace Scalr\Tests\System\Zmq;

use Scalr\Tests\TestCase;
use Scalr\System\Zmq\Cron\Task\CloudPricing;
use Scalr\System\Config\Yaml as ScalrConfig;
use Scalr\System\Zmq\Cron\ServiceIterator;

/**
 * Task test
 *
 * @author    Vitaliy Demidov  <vitaliy@scalr.com>
 * @since     5.0 (11.09.2014)
 */
class TaskTest extends TestCase
{

    const NS_CRON = 'Scalr\\System\\Zmq\\Cron';

    /**
     * Configuration
     *
     * @var ScalrConfig
     */
    protected $config;

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::setUp()
     */
    protected function setUp()
    {
        $this->config = \Scalr::getContainer()->config;

        if (!$this->config->defined('scalr.crontab')) {
            $this->markTestSkipped("scalr.crontab section of config has not been defined.");
        }
    }

    /**
     * cloud_pricing service test
     *
     * @test
     */
	public function testCloudPricing()
    {
        $task = new CloudPricing();

        $config = $task->config();

        $databag = 'scalr.crontab.services.cloud_pricing';

        $expected = $this->config->defined($databag) ? (object)$this->config->get($databag) : null;

        if (isset($config->cronExpression)) {
            //This option is added runtime
            $this->assertInstanceOf('Scalr\\Util\\Cron\\CronExpression', $config->cronExpression);
            unset($config->cronExpression);
        }

        $this->assertEquals($expected, $config);
    }

    /**
     * @test
     */
    public function testServiceIterator()
    {
        $iterator = new ServiceIterator();

        $this->assertNotEmpty($iterator->count());

        $cnt = 0;
        foreach ($iterator as $task) {
            $this->assertInstanceOf(self::NS_CRON . '\\TaskInterface', $task);
            $cnt++;
        }

        $this->assertEquals($iterator->count(), $cnt);
    }
}