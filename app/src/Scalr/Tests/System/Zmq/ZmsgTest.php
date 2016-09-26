<?php

namespace Scalr\Tests\System\Zmq;

use Scalr\Tests\TestCase;
use Scalr\System\Zmq\Zmsg;

/**
 * Zmsg test
 *
 * @since     5.0 (05.09.2014)
 */
class ZmsgTest extends TestCase
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::setUp()
     */
    protected function setUp()
    {
        if (!class_exists('ZMQContext')) {
            $this->markTestSkipped("Zmq extension is not available.");
        }
    }

	public function testComplex()
    {
		$context = new \ZMQContext();
		$output = new \ZMQSocket($context, \ZMQ::SOCKET_DEALER);
		$output->bind("inproc://zmsg_selftest");

		$input = new \ZMQSocket($context, \ZMQ::SOCKET_ROUTER);
		$input->connect("inproc://zmsg_selftest");

		//  Test send and receive of single-part message
		$zmsgo = new Zmsg($output);
		$zmsgo->setLast("Hello");
		$this->assertTrue($zmsgo->getLast() == "Hello");
		$zmsgo->send();

		$zmsgi = new Zmsg($input);
		$zmsgi->recv();
		$this->assertTrue($zmsgi->parts() == 2);
		$this->assertTrue($zmsgi->getLast() == "Hello");

		//  Test send and receive of multi-part message
		$zmsgo = new Zmsg($output);
		$zmsgo->setLast("Hello");
		$zmsgo->wrap("address1", "");
		$zmsgo->wrap("address2");
		$this->assertTrue($zmsgo->parts() == 4);
		$zmsgo->send();

		$zmsgi = new Zmsg($input);
		$zmsgi->recv();
		$this->assertTrue($zmsgi->parts() == 5);
		$zmsgi->unwrap();
		$this->assertTrue($zmsgi->unwrap() == "address2");

		$zmsgi->setLast(sprintf("%s%s", 'W', "orld"));
		$this->assertTrue($zmsgi->getLast() == "World");

		//  Pull off address 1, check that empty part was dropped
		$zmsgi->unwrap();
		$this->assertTrue($zmsgi->parts() == 1);

		//  Check that message body was correctly modified
		$part = $zmsgi->pop();
		$this->assertTrue($part == "World");
		$this->assertTrue($zmsgi->parts() == 0);

		// Test load and save
		$zmsg = new Zmsg();
		$zmsg->setLast("Hello");
		$zmsg->wrap("address1", "");
		$zmsg->wrap("address2");
		$this->assertTrue($zmsg->parts() == 4);
		$fh = fopen(sys_get_temp_dir() . "/zmsgtest.zmsg", 'w');
		$zmsg->save($fh);
		fclose($fh);
		$fh = fopen(sys_get_temp_dir() . "/zmsgtest.zmsg", 'r');
		$zmsg2 = new Zmsg();
		$zmsg2->load($fh);
		assert($zmsg2->getLast() == $zmsg->getLast());
		fclose($fh);
		$this->assertTrue($zmsg2->parts() == 4);
    }
}