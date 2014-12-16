<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Exception\ScalrException;

/**
 * ChefServer entity
 * @Table(name="services_chef_servers")
 */
class ChefServer extends AbstractEntity
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

    public function isInUse($accountId = 0, $envId = 0, $level = self::LEVEL_SCALR)
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
        if ($level == self::LEVEL_ENVIRONMENT) {
            $sql .= ' AND f.env_id = ?';
            $args[] = $envId;
        } elseif ($level == self::LEVEL_ACCOUNT) {
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
        if ($level == self::LEVEL_ENVIRONMENT) {
            $sql .= ' AND (r.env_id = ? OR r.env_id = 0)';
            $args[] = $envId;
        } elseif ($level == self::LEVEL_ACCOUNT) {
            $sql .= ' AND (r.client_id = ? OR r.client_id = 0)';
            $args[] = $accountId;
        }

        $rolesCount = $this->db()->GetOne($sql2, $args2);
        return $farmsCount > 0 || $rolesCount > 0 ? ['rolesCount' => $rolesCount, 'farmsCount' => $farmsCount] : false;
    }

    public static function getList($accountId, $envId, $level = self::LEVEL_ENVIRONMENT)
    {
        $criteria = [];

        if ($level == self::LEVEL_ENVIRONMENT) {
            $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $accountId], ['envId' => $envId], ['level' => self::LEVEL_ENVIRONMENT]]],
                ['$and' => [['accountId' => $accountId], ['envId' => null], ['level' => self::LEVEL_ACCOUNT]]],
                ['$and' => [['accountId' => null], ['envId' => null], ['level' => self::LEVEL_SCALR]]]
            ]];
        } elseif ($level == self::LEVEL_ACCOUNT) {
            $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $accountId], ['envId' => null], ['level' => self::LEVEL_ACCOUNT]]],
                ['$and' => [['accountId' => null], ['envId' => null], ['level' => self::LEVEL_SCALR]]]
            ]];
        } elseif ($level == self::LEVEL_SCALR) {
            $criteria[] = ['level' => self::LEVEL_SCALR];
            $criteria[] = ['envId' => null];
            $criteria[] = ['accountId' => null];
        }

        return ChefServer::find($criteria);
    }

}