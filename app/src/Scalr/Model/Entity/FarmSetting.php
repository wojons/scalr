<?php

namespace Scalr\Model\Entity;

use Scalr\Model\Entity\Account\User;

/**
 * Farm Settings entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="farm_settings")
 */
class FarmSetting extends Setting
{
    const CRYPTO_KEY = 'crypto.key';

    const SZR_UPD_REPOSITORY = 'szr.upd.repository';
    const SZR_UPD_SCHEDULE = 'szr.upd.schedule';

    const LOCK = 'lock';
    const LOCK_COMMENT = 'lock.comment';
    const LOCK_BY = 'lock.by';
    const LOCK_RESTRICT = 'lock.restrict';
    const LOCK_UNLOCK_BY = 'lock.unlock.by';

    const LEASE_STATUS = 'lease.status';
    const LEASE_TERMINATE_DATE = 'lease.terminate.date';
    const LEASE_EXTEND_CNT = 'lease.extend.cnt';
    const LEASE_NOTIFICATION_SEND = 'lease.notification.send';

    const TIMEZONE = 'timezone';

    const PROJECT_ID = 'project_id';

    const OWNER_HISTORY = 'owner.history';

    const EC2_VPC_ID = 'ec2.vpc.id';
    const EC2_VPC_REGION = 'ec2.vpc.region';

    const CREATED_BY_ID = "created.by.id";
    const CREATED_BY_EMAIL = "created.by.email";

    //Beware lest you forget to add a new properties to cloneFarm methhod

    /**
     * Farm identifier
     *
     * @Id
     * @Column(name="farmid",type="integer")
     * @var int
     */
    public $farmId;

    /**
     * Add record to history setting. Changes are not saved automatically.
     *
     * @param   Farm    $farm   Farm object
     * @param   User    $owner  New owner
     * @param   User    $user   User who changes owner for this farm
     */
    public static function addOwnerHistory(Farm $farm, User $owner, User $user)
    {
        $history = unserialize($farm->settings[self::OWNER_HISTORY]);

        if (!is_array($history)) {
            $history = [];
        }

        $history[] = [
            'newId'             => $owner->id,
            'newEmail'          => $owner->email,
            'changedById'       => $user->id,
            'changedByEmail'    => $user->email,
            'dt'                => date('Y-m-d H:i:s')
        ];
        $farm->settings[self::OWNER_HISTORY] = serialize($history);
    }

    /**
     * Get history of changes
     *
     * @param   Farm    $farm   Farm object
     * @return  array
     */
    public static function getOwnerHistory(Farm $farm)
    {
        $history = unserialize($farm->settings[self::OWNER_HISTORY]);
        if (!is_array($history)) {
            $history = [];
        }

        return $history;
    }
}