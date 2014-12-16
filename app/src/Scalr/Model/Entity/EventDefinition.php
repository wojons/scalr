<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\DataType\ScopeInterface;
/**
 * Event definition
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (08.05.2014)
 *
 * @Entity
 * @Table(name="event_definitions")
 */
class EventDefinition extends AbstractEntity implements ScopeInterface
{
    /**
     * ID
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * Event's name
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Description
     *
     * @Column(type="string")
     * @var string
     */
    public $description;

    /**
     * The identifier of the client's account
     *
     * @Column(type="integer")
     * @var integer
     */
    public $accountId;

    /**
     * The identifier of the client's environment
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $envId;

    /**
     * @param $name
     * @param $accountId
     * @param $envId
     * @return bool
     */
    public static function isExisted($name, $accountId, $envId)
    {
        return !!self::findOne([
            ['name' => $name],
            ['$or' => [['accountId' => NULL], ['accountId' => $accountId]]],
            ['$or' => [['envId' => NULL], ['envId' => $envId]]]
        ]);
    }

    /**
     * @param   integer     $accountId
     * @param   integer     $envId
     * @return  array       [name => description]
     */
    public static function getList($accountId, $envId)
    {
        $retval = [];
        foreach (self::find([
            ['$or' => [['accountId' => NULL], ['accountId' => $accountId]]],
            ['$or' => [['envId' => NULL], ['envId' => $envId]]]
        ]) as $ev) {
            /* @var EventDefinition $ev */
            $retval[$ev->name] = [
                'name'        => $ev->name,
                'description' => $ev->description,
                'scope'       => $ev->envId ? self::SCOPE_ENVIRONMENT : ($ev->accountId ? self::SCOPE_ACCOUNT : self::SCOPE_SCALR)
            ];
        }

        return $retval;
    }

    /**
     *
     * @param int $accountId
     * @return array|false
     * @throws \Scalr\Exception\ModelException
     */
    public function getUsed($accountId)
    {
        $used = [];
        $used['rolesCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM role_scripts rs ' .
            'JOIN roles r ON r.id = rs.role_id WHERE r.client_id = ? AND rs.event_name = ?',
            [$accountId == NULL ? 0 : $accountId, $this->name]);

        $used['farmRolesCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM farm_role_scripts frs ' .
            'JOIN farms f ON f.id = frs.farmid WHERE f.clientid = ? AND frs.event_name = ?',
            [$accountId == NULL ? 0 : $accountId, $this->name]);

        $used['webhooksCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM webhook_configs wh ' .
            'JOIN webhook_config_events wce ON wce.webhook_id = wh.webhook_id WHERE wh.account_id = ? AND wce.event_type = ?',
            [$accountId, $this->name]);

        return $used['rolesCount'] == 0 && $used['farmRolesCount'] == 0 && $used['webhooksCount'] == 0 ? false : $used;
    }
}
