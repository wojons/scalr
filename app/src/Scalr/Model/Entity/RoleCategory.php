<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\DataType\ScopeInterface;
use Scalr\DataType\AccessPermissionsInterface;

/**
 * RoleCategory entity
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.4.0 (05.03.2015)
 *
 * @Entity
 * @Table(name="role_categories")
 */
class RoleCategory extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{

    /**
     * Identifier of the Role Category
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * The identifier of the Environment
     *
     * @Column(type="integer")
     * @var   int
     */
    public $envId;

    /**
     * The name of the Role Category
     * @var string
     */
    public $name;

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->envId) ? self::SCOPE_ENVIRONMENT : self::SCOPE_SCALR;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        switch ($this->getScope()) {
            case static::SCOPE_ENVIRONMENT:
                return $environment
                    ? $this->envId == $environment->id
                    : $user->hasAccessToEnvironment($this->envId);

            case static::SCOPE_SCALR:
                return !$modify;

            default:
                return false;
        }
    }
}
