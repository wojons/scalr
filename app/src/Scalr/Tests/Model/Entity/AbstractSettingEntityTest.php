<?php
namespace Scalr\Tests\Model\Entity;

use Scalr\Tests\TestCase;

/**
 * AbstractSettingEntityTest
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.4.0 (21.02.2015)
 */
class AbstractSettingEntityTest extends TestCase
{

    const CLASS_ENTITY2 = 'Scalr\Tests\Fixtures\Model\Entity\Entity2';

    /**
     * @test
     */
    public function testMultiColumnPk()
    {
        $pk = ['parentId', 'name'];

        $entity = $this->getMockBuilder(self::CLASS_ENTITY2)
            ->disableOriginalConstructor()
            ->setMethods(['_findPk'])
            ->getMock()
        ;

        //To be able to reference to the object inside the static method
        $entity::$mock = $entity;

        $entity->id = $pk[0];
        $entity->name = $pk[1];
        $entity->value = 'The value';

        $entity->expects($this->once())
            ->method('_findPk')
            ->with($this->equalTo($pk))
            ->willReturn($entity)
        ;

        $result = $entity::getValue($pk);

        $this->assertSame($entity->value, $result);
     }
}