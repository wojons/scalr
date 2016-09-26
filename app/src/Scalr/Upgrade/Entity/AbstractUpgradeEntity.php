<?php

namespace Scalr\Upgrade\Entity;

use Scalr\Upgrade\AbstractEntity;

/**
 * AbstractUpgradeEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (09.10.2013)
 *
 * @property string $uuid
 *           An UUID of the upgrade
 *
 * @property string $released
 *           The relased timestamp for the update
 *
 * @property string $applied
 *           The timestamp when this update is applied
 *
 * @property string $appears
 *           The timestamp when this update is delivered by git pull
 *
 * @property string $status
 *           The upgrade status
 *
 * @property string $hash
 *           SHA1 hash of the upgrade file
 */
abstract class AbstractUpgradeEntity extends AbstractEntity
{
    /**
     * Upgrade status OK
     */
    const STATUS_OK = 1;

    /**
     * Upgrade status FAILED
     */
    const STATUS_FAILED = 2;

    /**
     * Upgrade status PENDING
     */
    const STATUS_PENDING = 0;

    protected $uuid;

    protected $released;

    protected $applied;

    protected $appears;

    protected $status;

    protected $hash;

    /**
     * Gets update class name which is known to be based on the released property
     *
     * @return   string Returns the name of the update class
     */
    public function getUpdateClassName()
    {
        return 'Update' . str_replace(array('-', ' ', ':'), '', $this->released);
    }

    /**
     * Gets the status name
     *
     * @return   string Returns status name
     */
    public function getStatusName()
    {
        $name = $this->status;
        switch ($this->status) {
            case self::STATUS_FAILED :
                $name = 'FAILED';
                break;

            case self::STATUS_OK :
                $name = 'OK';
                break;

            case self::STATUS_PENDING :
                $name = 'PENDING';
                break;
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.AbstractEntity::getPrimaryKey()
     */
    public function getPrimaryKey()
    {
        return 'uuid';
    }

    /**
     * Creates failure message to storage
     *
     * @param   string   $log  The log
     */
    abstract public function createFailureMessage($log);
}
