<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Model\Entity\AbstractSettingEntity;

/**
 * SettingEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (29.03.2014)
 * @Entity
 * @Table(name="settings",service="cadb")
 */
class SettingEntity extends AbstractSettingEntity
{

    /**
     * The start day each of the four quarter
     * JSON
     */
    const ID_BUDGET_DAYS = 'budget_days';

    /**
     * Whether automatic prices update routine should override user defined prices for AWS
     * 1|0
     */
    const ID_FORBID_AUTOMATIC_UPDATE_AWS_PRICES = 'forbid_automatic_update_aws_prices';

    /**
     * Whether quarters days have been confirmed by the financial admin
     * 1|0
     */
    const ID_QUARTERS_DAYS_CONFIRMED = 'quarters_days_confirmed';

    /**
     * Whether notifications is enabled for cost centres
     * 1|0
     */
    const ID_NOTIFICATIONS_CCS_ENABLED = 'notifications.ccs.enabled';

    /**
     * Whether notifications is enabled for projects
     * 1|0
     */
    const ID_NOTIFICATIONS_PROJECTS_ENABLED = 'notifications.projects.enabled';

    /**
     * Whether periodic reports is enabled
     * 1|0
     */
    const ID_REPORTS_ENABLED = 'reports.enabled';

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
     * Gets quarter start days
     *
     * @param  bool  $ignoreCache   Whether it should ignore cache
     * @return array Returns days as array looks like ["01-01", "04-01", "07-01", "10-01"]
     */
    public static function getQuarters($ignoreCache = false)
    {
        static $days = null;

        if ($days === null || $ignoreCache) {
            $entity = self::findPk(self::ID_BUDGET_DAYS);
            if ($entity) {
                $days = @json_decode($entity->value);
            }
        }

        return $days;
    }
}