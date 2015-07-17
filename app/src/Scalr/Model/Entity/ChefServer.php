<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Exception\ScalrException;
use Scalr\DataType\ScopeInterface;

/**
 * ChefServer entity
 * @Table(name="services_chef_servers")
 */
class ChefServer extends AbstractEntity implements ScopeInterface
{

    const LEVEL_SCALR = 1;
    const LEVEL_ACCOUNT = 2;
    const LEVEL_ENVIRONMENT = 4;

    /**
     * The identifier of the webhook endpoint
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * The level
     *
     * @Column(type="integer")
     * @var int
     */
    public $level;

    /**
     * The identifier of the client's account
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * The identifier of the client's environment
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

    /**
     * Endpoint url
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $url;

    /**
     * Username
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $username;

    /**
     * Auth key
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $authKey;

    /**
     * vUsername
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $vUsername;

    /**
     * vAuth key
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $vAuthKey;
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    public function isInUse($accountId = null, $envId = null, $scope = self::SCOPE_SCALR)
    {
        $sql = 'SELECT count(DISTINCT f.id)
                FROM farms f
                INNER JOIN farm_roles fr ON fr.farmid = f.id
                INNER JOIN farm_role_settings frs1 ON fr.id = frs1.farm_roleid AND frs1.name = ? AND frs1.value = ?
                INNER JOIN farm_role_settings frs2 ON fr.id = frs2.farm_roleid AND frs2.name = ? AND frs2.value = ?';
        $args = [
            \Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID,
            $this->id,
            \Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP,
            1
        ];
        if ($scope == self::SCOPE_ENVIRONMENT) {
            $sql .= ' AND f.env_id = ?';
            $args[] = $envId;
        } elseif ($scope == self::SCOPE_ACCOUNT) {
            $sql .= ' AND f.clientid = ?';
            $args[] = $accountId;
        }
        $farmsCount = $this->db()->GetOne($sql, $args);

        $sql2 = 'SELECT count(r.id)
                 FROM roles r
                 INNER JOIN role_properties p1 ON p1.role_id = r.id AND p1.name = ? AND p1.value = ?
                 INNER JOIN role_properties p2 ON p2.role_id = r.id AND p2.name = ? AND p2.value = ?
                 WHERE 1 = 1';

        $args2 = [
            \Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID,
            $this->id,
            \Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP,
            1
        ];
        if ($scope == self::SCOPE_ENVIRONMENT) {
            $sql .= ' AND (r.env_id = ? OR r.env_id IS NULL)';
            $args[] = empty($envId) ? null : $envId;
        } elseif ($scope == self::SCOPE_ACCOUNT) {
            $sql .= ' AND (r.client_id = ? OR r.client_id IS NULL)';
            $args[] = empty($accountId) ? null : $accountId;
        }

        $rolesCount = $this->db()->GetOne($sql2, $args2);
        return $farmsCount > 0 || $rolesCount > 0 ? ['rolesCount' => $rolesCount, 'farmsCount' => $farmsCount] : false;
    }

    public static function getList($accountId, $envId, $scope = self::SCOPE_ENVIRONMENT)
    {
        $criteria = [];
        switch ($scope) {
            case self::SCOPE_ENVIRONMENT:
                $criteria[] = ['$or' => [
                    ['$and' => [['accountId' => $accountId], ['envId' => $envId], ['level' => self::LEVEL_ENVIRONMENT]]],
                    ['$and' => [['accountId' => $accountId], ['envId' => null], ['level' => self::LEVEL_ACCOUNT]]],
                    ['$and' => [['accountId' => null], ['envId' => null], ['level' => self::LEVEL_SCALR]]]
                ]];
                break;
            case self::SCOPE_ACCOUNT:
                $criteria[] = ['$or' => [
                    ['$and' => [['accountId' => $accountId], ['envId' => null], ['level' => self::LEVEL_ACCOUNT]]],
                    ['$and' => [['accountId' => null], ['envId' => null], ['level' => self::LEVEL_SCALR]]]
                ]];
                break;
            case self::SCOPE_SCALR:
                $criteria[] = ['level' => self::LEVEL_SCALR];
                $criteria[] = ['envId' => null];
                $criteria[] = ['accountId' => null];
                break;

        }

        return ChefServer::result(ChefServer::RESULT_ENTITY_COLLECTION)->find($criteria);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        switch ($this->level) {
            case self::LEVEL_ENVIRONMENT:
                return self::SCOPE_ENVIRONMENT;
            case self::LEVEL_ACCOUNT:
                return self::SCOPE_ACCOUNT;
            case self::LEVEL_SCALR:
                return self::SCOPE_SCALR;
            default:
                throw new \UnexpectedValueException(sprintf(
                    "Unknown level type: %d in %s::%s",
                    $this->level, get_class($this), __FUNCTION__
                ));
        }
    }

    public function setScope($scope, $accountId, $envId)
    {
        switch ($scope) {
            case self::SCOPE_ENVIRONMENT:
                $this->level = self::LEVEL_ENVIRONMENT;
                $this->accountId = $accountId;
                $this->envId = $envId;
                break;
            case self::SCOPE_ACCOUNT:
                $this->level = self::LEVEL_ACCOUNT;
                $this->accountId = $accountId;
                break;
            case self::SCOPE_SCALR:
                $this->level = self::LEVEL_SCALR;
                break;
            default:
                throw new \UnexpectedValueException(sprintf(
                    "Unknown scope: %d in %s::%s",
                    $scope, get_class($this), __FUNCTION__
                ));
        }
    }

}