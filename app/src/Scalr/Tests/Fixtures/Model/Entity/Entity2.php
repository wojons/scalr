<?php
namespace Scalr\Tests\Fixtures\Model\Entity;

use Scalr\Model\Entity\AbstractSettingEntity;

/**
 * Entity2
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4.0 (21.02.2015)
 * @Entity
 * @Table(name="table_should_not_exist_entity2")
 */
class Entity2 extends AbstractSettingEntity
{
    /**
     * Mock for entity
     * @var Entity2
     */
    public static $mock;

    /**
     * The identifier of the parent record
     *
     * @Id
     * @var string
     */
    public $id;

    /**
     * The name of the setting
     *
     * @Id
     * @var string
     */
    public $name;

    /**
     * The value
     *
     * @var string
     */
    public $value;

    /**
     * This method is expected to be mocking
     */
    protected function _findPk(array $args = [])
    {
    }

    /**
     * Static call mocking
     */
    public static function findPk()
    {
        return self::$mock->_findPk(func_get_args());
    }
}