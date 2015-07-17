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

    /**
     * Constants for statistics
     */
    const LEASE_STANDARD_REQUEST = 'statistic.lease.standard.request';
    const LEASE_NOT_STANDARD_REQUEST = 'statistic.lease.not.standard.request';
    const LEASE_DECLINED_REQUEST = 'statistic.lease.declined.request';
    const LEASE_TERMINATE_FARM = 'statistic.lease.terminate.farm';

    /**
     * Increase count for event
     *
     * @param $name
     */
    public static function increase($name)
    {
        $setting = SettingEntity::findPk($name);

        if (! $setting) {
            $setting = new SettingEntity();
            $setting->id = $name;
        }

        $setting->value = intval($setting->value) + 1;
        $setting->save();
    }
}