<?php
namespace Scalr\Tests;

use Scalr\Logger\AuditLog\AuditLogTags;
use Scalr\Logger\AuditLog;
use Scalr\DependencyInjection\Container;
use Scalr\Logger\AuditLog\Documents\FarmDocument;

/**
 * Audit Log test
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     01.11.2012
 */
class AuditLogTest extends \PHPUnit_Framework_TestCase
{
	const TEST_USER_ID = 7362;

	const CLASS_AUDITLOG_LOG_RECORD = 'Scalr\\Logger\\AuditLog\\LogRecord';

	/**
	 * @var AuditLog
	 */
	protected $logger;

	/**
	 * {@inheritdoc}
	 * @see PHPUnit_Framework_TestCase::setUp()
	 */
	public function setUp()
	{
		parent::setUp();
		$container = Container::getInstance();
		if ($container->user === null) {
			$this->setTestUserToContainer();
		} else {
			try {
				$container->user->getId();
			} catch (\Exception $e) {
				$this->setTestUserToContainer();
			}
		}
		$this->logger = $container->auditLog;
	}

	/**
	 * Sets test user to container
	 */
	private function setTestUserToContainer()
	{
		$container = Container::getInstance();
		$container->user = new \Scalr_Account_User();
		$container->user->loadById(self::TEST_USER_ID);
	}

	/**
	 * {@inheritdoc}
	 * @see PHPUnit_Framework_TestCase::tearDown()
	 */
	public function tearDown()
	{
		unset($this->logger);
		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function testDocumentToArray()
	{
		$farm = new FarmDocument(10, 'farm name');
		$arr = $farm->toArray();
		$this->assertEquals(array('farmid' => 10, 'name' => 'farm name', 'datatype' => 'Farm'), $arr);
		$this->assertEquals($farm, FarmDocument::createFromArray($arr));
	}

	/**
	 * @test
	 */
	public function testAuditLogTags()
	{
		$tags = new AuditLogTags();
		try {
			$tags->add('unknown-tag--');
			$this->assertTrue(false, 'Exeption must be thrown in this case.');
		} catch(\InvalidArgumentException $e) {}
		$tags->add(AuditLogTags::TAG_STOP, AuditLogTags::TAG_PAUSE);
		$this->assertEquals(AuditLogTags::TAG_STOP . ',' . AuditLogTags::TAG_PAUSE, (string)$tags);
		$tags->remove(AuditLogTags::TAG_STOP);
		$this->assertEquals(AuditLogTags::TAG_PAUSE, (string) $tags);
		$this->assertEquals(isset($tags->pause), true);
		unset($tags->pause);
		$tags->add(AuditLogTags::TAG_CREATE);
		$this->assertEquals(array(AuditLogTags::TAG_CREATE), $tags->get());

		$tags = new AuditLogTags(AuditLogTags::TAG_UPDATE, AuditLogTags::TAG_REMOVE);
		$this->assertEquals(AuditLogTags::TAG_UPDATE . ',' . AuditLogTags::TAG_REMOVE, (string) $tags);
		$tags = new AuditLogTags(array(AuditLogTags::TAG_UPDATE, AuditLogTags::TAG_STOP));
		$this->assertEquals(AuditLogTags::TAG_UPDATE . ',' . AuditLogTags::TAG_STOP, (string) $tags);
	}

	/**
	 * @test
	 */
	public function testFunctionalAuditLog()
	{
// 		$this->markTestSkipped();
// 		$r = $this->logger->log('I have just removed a farm', array(AuditLogTags::TAG_REMOVE), new FarmDocument(2332, 'test farm'));
// 		$this->assertTrue($r);
// 		$records = $this->logger->find(array('tags' => array('$in' => array('remove'))), array('time' => -1), 1);
// 		$this->assertEquals(1, count($records));
// 		$this->assertInstanceOf(self::CLASS_AUDITLOG_LOG_RECORD, $records[0]);
	}
}