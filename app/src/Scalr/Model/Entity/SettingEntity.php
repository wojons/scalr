<?php
namespace Scalr\Model\Entity;

/**
 * SettingEntity for scalr database
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (30.05.2014)
 * @Entity
 * @Table(name="settings")
 */
class SettingEntity extends AbstractSettingEntity
{

    /**
     * Upgrade script pid
     */
    const ID_UPGRADE_PID = 'upgrade.pid';

    /**
     * The unique identifier of the record
     *
     * @Id
     * @var string
     */
    public $id;

    /**
     * The date
     *
     * @var string
     */
    public $value;
}