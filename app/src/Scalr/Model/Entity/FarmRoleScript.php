<?php

namespace Scalr\Model\Entity;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\InvalidEntityConfigurationException;

/**
 * FarmRoleScript entity
 *
 * @author N.V.
 *
 * @property    FarmRoleScriptingTarget[]   $targets
 *
 * @Entity
 * @Table(name="farm_role_scripts")
 */
class FarmRoleScript extends OrchestrationRule
{

    /**
     * Script Id
     *
     * @Column(name="scriptid",type="integer")
     *
     * @var int
     */
    public $scriptId;

    /**
     * Farm Id
     *
     * @Column(name="farmid",type="integer")
     *
     * @var int
     */
    public $farmId;

    /**
     * AMI Id
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $amiId;

    /**
     * Menu item flag
     *
     * @Column(type="boolean")
     *
     * @var bool
     */
    public $ismenuitem;

    /**
     * Farm Role Id
     *
     * @Column(name="farm_roleid",type="integer")
     *
     * @var int
     */
    public $farmRoleId;

    /**
     * Debug
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $debug;

    /**
     * Is system flag
     *
     * @Column(name="issystem",type="boolean")
     *
     * @var bool
     */
    public $isSystem = true;

    /**
     * @var FarmRoleScriptingTarget[]
     */
    protected $_targets = [];

    public function save()
    {
        parent::save();

        if (!empty($this->_targets)) {
            foreach ($this->_targets as $target) {
                $target->save();
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::__get()
     */
    public function __get($name)
    {
        switch ($name) {
            case 'targets':
                if (empty($this->_targets)) {
                    $this->_targets = FarmRoleScriptingTarget::findByFarmRoleScriptId($this->id)->getArrayCopy();
                }

                return $this->_targets;

            default:
                return parent::__get($name);
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::__set()
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'targets':
                if (!is_array($value)) {
                    $value = (array) $value;
                }

                /* @var $farmRole FarmRole */
                foreach (FarmRole::find([['id' => ['$in' => $value]]]) as $farmRole) {
                    $target = new FarmRoleScriptingTarget();
                    $target->farmRoleScriptId = &$this->id;
                    $target->targetType = OrchestrationRule::TARGET_ROLES;
                    $target->target = $farmRole->alias;

                    $this->_targets[$farmRole->id] = $target;
                }

                if ($notFound = array_diff_key(array_flip($value), $this->_targets)) {
                    throw new InvalidEntityConfigurationException("Invalid target. Not found Farm Roles with IDs: [" . implode(', ', array_keys($notFound)) . "]");
                }
                break;

            default:
                parent::__set($name, $value);
        }
    }

    /**
     * {@inheritdoc}
     * @see OrchestrationRule::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        return FarmRole::findPk($this->farmRoleId)->hasAccessPermissions($user, $environment, $modify);
    }

    /**
     * {@inheritdoc}
     * @see OrchestrationRule::getScope()
     */
    public function getScope()
    {
        return ScopeInterface::SCOPE_FARMROLE;
    }
}