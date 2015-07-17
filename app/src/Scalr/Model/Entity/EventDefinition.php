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
     * @param $name
     * @param $accountId
     * @param $envId
     * @return bool
     */
    public static function isExist($name, $accountId, $envId)
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
     *
     * @param int $accountId
     * @return array|false
     * @throws \Scalr\Exception\ModelException
     */
    public function getUsed($accountId)
    {
        $used = [];

        $query = "SELECT COUNT(*) FROM role_scripts rs
                  JOIN roles r ON r.id = rs.role_id
                  WHERE rs.event_name = ?";
        $params[] = $this->name;

        if (empty($accountId)) {
            $query .= " AND r.client_id IS NULL";
        } else {
            $query .= " AND r.client_id = ?";
            $params[] = $accountId;
        }

        $used['rolesCount'] = $this->db()->GetOne($query, $params);

        $used['farmRolesCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM farm_role_scripts frs ' .
            'JOIN farms f ON f.id = frs.farmid WHERE f.clientid = ? AND frs.event_name = ?',
            [empty($accountId) ? 0 : $accountId, $this->name]);

        $used['webhooksCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM webhook_configs wh ' .
            'JOIN webhook_config_events wce ON wce.webhook_id = wh.webhook_id WHERE wh.account_id = ? AND wce.event_type = ?',
            [$accountId, $this->name]);

        return $used['rolesCount'] == 0 && $used['farmRolesCount'] == 0 && $used['webhooksCount'] == 0 ? false : $used;
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
