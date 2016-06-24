<?php

namespace Scalr\Tests\Fixtures\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\SettingsCollection;

/**
 * Test entity
 *
 * @author N.V.
 *
 * @property    SettingsCollection  $settings   The list of entity properties
 *
 * @Entity
 * @Table(name="test_abstract_entity_2")
 */
class TestEntity2 extends AbstractEntity
{

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var int
     */
    public $id;

    /**
     * @Column(type="string")
     * @var string
     */
    public $data;

    /**
     * Entity properties
     *
     * @var SettingsCollection
     */
    protected $_settings;

    /**
     * Magic getter.
     * Gets the values of the fields that require initialization.
     *
     * @param   string  $name   Name of property that is accessed
     *
     * @return  mixed   Returns property value
     */
    public function __get($name)
    {
        switch ($name) {
            case 'settings':
                if (empty($this->_settings)) {
                    $this->_settings = new SettingsCollection(
                        'Scalr\Tests\Fixtures\Model\Entity\TestEntitySetting',
                        [[ 'testEntityId' => &$this->id ]],
                        [ 'testEntityId' => &$this->id ]
                    );
                }

                return $this->_settings;

            default:
                return parent::__get($name);
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::save()
     */
    public function save()
    {
        parent::save();

        if (!empty($this->_settings)) {
            $this->_settings->save();
        }
    }
}