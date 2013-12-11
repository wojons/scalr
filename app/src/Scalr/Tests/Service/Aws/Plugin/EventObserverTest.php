<?php
namespace Scalr\Tests\Service\Aws;

use Scalr\Service\Aws\Exception\PluginException;
use Scalr\Tests\Service\AwsTestCase;
use Scalr\Service\Aws\Plugin\EventObserver;

/**
 * EventObserverTest
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     26.09.2013
 */
class EventObserverTest extends AwsTestCase
{
    const SUFFIX = 'Plugin';

    /**
     * Data provider for testConstructor
     */
    public function providerPlugins()
    {
        $d = array();
        $d[] = array('Statistics');
        return $d;
    }

    /**
     * Gets new plugin container instance
     *
     * @return EventObserver
     */
    private function getEventObserver()
    {
        $aws = $this->getAwsMock();
        $observer = new EventObserver($aws);
        $aws->setEventObserver($observer);
        return $observer;
    }

    /**
     * @test
     * @dataProvider providerPlugins
     */
    public function testConstructor($plugin)
    {
        $EventObserver = $this->getEventObserver();
        $hasMethod = 'has' . $plugin;
        $getMethod = 'get' . $plugin;
        $letMethod = 'let' . $plugin;

        //plugin has not been enabled yet
        if (!$EventObserver->$hasMethod()) {
            try {
                $EventObserver->$getMethod();
                $this->assertTrue(false, 'Exception must be thrown here.');
            } catch (PluginException $e) {
                $this->assertContains('has not been allowed', $e->getMessage());
            }
        }

        //Enables desired plugin
        $this->assertInstanceOf($this->getAwsClassName('Plugin\\EventObserver'), $EventObserver->$letMethod());

        //Checks that it is enabled
        $this->assertTrue($EventObserver->$hasMethod());

        //Gets plugin
        $this->assertInstanceOf($this->getAwsClassName('Plugin\\PluginInterface'), $EventObserver->$getMethod());
    }

    /**
     * @test
     */
    public function testAll()
    {
        $EventObserver = $this->getEventObserver();
        $all = $EventObserver->all();
        $this->assertInstanceOf('ArrayObject', $all);
        $this->assertGreaterThan(0, count($all));
        foreach ($all as $plugin) {
            $this->assertInstanceOf($this->getAwsClassName('Plugin\\PluginInterface'), $plugin);
        }
    }
}
