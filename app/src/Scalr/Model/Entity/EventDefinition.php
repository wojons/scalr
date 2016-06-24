<?php
namespace Scalr\Model\Entity;

use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Model\AbstractEntity;
use Scalr\DataType\ScopeInterface;
use DateTime;
/**
 * Event definition
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (08.05.2014)
 *
 * @Entity
 * @Table(name="event_definitions")
 */
class EventDefinition extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{
    /**
     * Regex for name validation
     */
    const NAME_REGEXP = '[[:alnum:]]+';

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
     * Time when the record is created
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $created;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->created = new DateTime();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->envId) ? self::SCOPE_ENVIRONMENT : (!empty($this->accountId) ? self::SCOPE_ACCOUNT : self::SCOPE_SCALR);
    }


    /**
     * Checks whether the event with the specified name exists
     *
     * @param  string $name      The name of the Event
     * @param  int    $accountId Identifier of the account
     * @param  int    $envId     Identifier of the environment
     * @return boolean Returns true if the Event exists or false otherwise
     */
    public static function isExist($name, $accountId, $envId)
    {
        return !!self::findOne([
            ['name' => $name],
            ['$or'  => [['accountId' => null], ['accountId' => $accountId]]],
            ['$or'  => [['envId' => null], ['envId' => $envId]]]
        ]);
    }

    /**
     * Gets the list of the events by specified criteria
     *
     * @param   integer     $accountId
     * @param   integer     $envId
     * @return  array       [name => description]
     */
    public static function getList($accountId, $envId)
    {
        $retval = [];
        foreach (self::find([
            ['$or' => [['accountId' => null], ['accountId' => $accountId]]],
            ['$or' => [['envId' => null], ['envId' => $envId]]]
        ]) as $ev) {
            /* @var $ev EventDefinition */
            $retval[$ev->name] = [
                'name'        => $ev->name,
                'description' => $ev->description,
                'scope'       => $ev->envId ? self::SCOPE_ENVIRONMENT : ($ev->accountId ? self::SCOPE_ACCOUNT : self::SCOPE_SCALR)
            ];
        }

        return $retval;
    }

    /**
     * Checks whether the Event exists
     *
     * @param    int          $accountId  optional   Identifier of the account
     * @param    int          $envId  optional   Identifier of the environment
     * @return   array|false  Returns FALSE if the Event does not exist on account or Array otherwise.
     *                        Array looks like ['rolesCount' => N, 'farmRolesCount' => M, 'webhooksCount' => Z, 'accountScriptsCount' => K]
     * @throws   \Scalr\Exception\ModelException
     */
    public function getUsed($accountId = null, $envId = null)
    {
        if (!empty($this->accountId)) {
            $accountId = $this->accountId;

            if (!empty($this->envId)) {
                $envId = $this->envId;
            }
        }

        $used = [];

        // Find role scripts that use this event definition
        $query = "SELECT COUNT(*) FROM role_scripts rs";
        $where = " WHERE rs.event_name = ?";
        $params = [$this->name];

        if (!empty($accountId)) {
            $query .= " JOIN roles r ON r.id = rs.role_id";
            $where .= " AND r.client_id = ?";
            $params[] = $accountId;

            if (!empty($envId)) {
                $where .= " AND r.env_id = ?";
                $params[] = $envId;
            }
        }

        $query .= $where;

        $used['rolesCount'] = $this->db()->GetOne($query, $params);

        // Find farm role scripts that use this event definition
        $query = "SELECT COUNT(*) FROM farm_role_scripts frs";
        $where = " WHERE frs.event_name = ?";
        $params = [$this->name];

        if (!empty($accountId)) {
            $query .= " JOIN farms f ON f.id = frs.farmid";
            $where .= " AND f.clientid = ?";
            $params[] = $accountId;

            if (!empty($envId)) {
                $where .= " AND f.env_id = ?";
                $params[] = $envId;
            }
        }

        $query .= $where;

        $used['farmRolesCount'] = $this->db()->GetOne($query, $params);

        // Find webhook config events that use this event definition
        $query = "SELECT COUNT(*) FROM webhook_config_events wce";
        $where = " WHERE wce.event_type = ?";
        $params = [$this->name];

        if (!empty($accountId)) {
            $query .= " JOIN webhook_configs wh ON wh.webhook_id = wce.webhook_id";
            $where .= " AND wh.account_id = ?";
            $params[] = $accountId;

            if (!empty($envId)) {
                $where .= " AND wh.env_id = ?";
                $params[] = $envId;
            }
        }

        $query .= $where;

        $used['webhooksCount'] = $this->db()->GetOne($query, $params);

        if (empty($envId)) {
            // Find account scripts that use this event definition
            $query = "SELECT COUNT(*) FROM account_scripts acs WHERE acs.event_name = ?";
            $params = [$this->name];

            if (!empty($accountId)) {
                $query .= " AND acs.account_id = ?";
                $params[] = $accountId;
            }

            $used['accountScriptsCount'] = $this->db()->GetOne($query, $params);
        } else {
            $used['accountScriptsCount'] = 0;
        }

        return $used['rolesCount'] != 0 || $used['farmRolesCount'] != 0 || $used['webhooksCount'] != 0 || $used['accountScriptsCount'] != 0 ? $used : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        $scope = $this->getScope();

        if ($modify) {
            if (!$environment) {
                $result = $scope === $this::SCOPE_ACCOUNT && $this->accountId == $user->accountId ||
                    $scope === $this::SCOPE_ENVIRONMENT && $user->hasAccessToEnvironment($this->envId);
            } else {
                $result = $scope === $this::SCOPE_ENVIRONMENT && $this->envId == $environment->id;
            }
        } else {
            if (!$environment) {
                $result = $scope === $this::SCOPE_SCALR ||
                    $scope === $this::SCOPE_ACCOUNT && $this->accountId == $user->accountId ||
                    $scope === $this::SCOPE_ENVIRONMENT && $user->hasAccessToEnvironment($this->envId);
            } else {
                $result = $scope === $this::SCOPE_SCALR ||
                    $scope === $this::SCOPE_ACCOUNT && $this->accountId == $user->accountId ||
                    $scope === $this::SCOPE_ENVIRONMENT && $this->envId == $environment->id;
            }
        }

        return $result;
    }

}
