<?php
namespace Scalr\Stats\CostAnalytics\Entity;

/**
 * UsageItemEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.5 (19.05.2015)
 * @Entity
 * @Table(name="usage_items",service="cadb")
 */
class UsageItemEntity extends \Scalr\Model\AbstractEntity
{
    /**
     * Unique identifier of the Usage Item
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="bin4")
     * @var string
     */
    public $id;

    /**
     * Usage Type identifier
     *
     * @Column(type="bin4")
     * @var string
     */
    public $usageType;

    /**
     * The name of the Usage Item
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Display name of the Usage Item
     *
     * It is used to override the name for displaying purposes
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $displayName;

    /**
     * The Usage Type Entity that corresponds to the Usage Item
     *
     * @var UsageTypeEntity
     */
    private $_usageType;

    /**
     * Gets display name of the Usage Item
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName ?: $this->name;
    }

    /**
     * Gets the Usage Type that corresponds to the Usage Item
     *
     * @return UsageTypeEntity Returns the Usage Type that corresponds to the Usage Item
     */
    public function getUsageType()
    {
        if ($this->_usageType === null) {
            $this->_usageType = UsageTypeEntity::findPk($this->usageType);
        }

        return $this->_usageType;
    }
}