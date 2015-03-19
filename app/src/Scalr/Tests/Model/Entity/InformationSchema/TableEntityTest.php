<?php
namespace Scalr\Tests\Model\Entity\InformationSchema;

use Scalr\Model\Entity\InformationSchema\TableEntity;
use Scalr\Tests\TestCase;

/**
 * TableEntity test
 *
 * @author   Vlad Dobrovolskiy <v.dobrovolskiy@scalr.com>
 * @since    5.0.0 (23.02.2015)
 */
class TableEntityTest extends TestCase
{
    /**
     * @test
     */
    public function testFunctional()
    {
        if ($this->isSkipFunctionalTests()) {
            $this->markTestSkipped();
        }

        $db = \Scalr::getDb();

        $entity = new TableEntity();
        $schema = $db->GetOne("SELECT DATABASE()");

        $tableInfo = $entity->findOne([['tableSchema' => $schema]]);

        $this->assertInstanceOf('Scalr\\Model\\Entity\\InformationSchema\\TableEntity', $tableInfo);
        /* @var TableEntity $tableInfo */
        $this->assertNotEmpty($tableInfo->engine);
        $this->assertNotEmpty($tableInfo->tableName);
        $this->assertNotEmpty($tableInfo->createTime);
        $this->assertInstanceOf('DateTime', $tableInfo->createTime);
    }

}