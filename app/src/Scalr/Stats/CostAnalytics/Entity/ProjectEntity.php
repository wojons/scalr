<?php

namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Exception\AnalyticsException;
use Scalr\Stats\CostAnalytics\Usage;
use DateTime, DateTimeZone;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Model\Entity;

/**
 * ProjectEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (11.02.2014)
 * @Entity
 * @Table(name="projects")
 */
class ProjectEntity extends \Scalr\Model\AbstractEntity implements AccessPermissionsInterface
{
    /**
     * The project is accessible only for owner or financial admin
     * @deprecated
     */
    const SHARED_TO_OWNER = 0;

    /**
     * The project is accessible for all farms which environment is associated with this cost centre
     */
    const SHARED_WITHIN_CC = 1;

    /**
     * The same as WITHIN_CC but additionally restricted by the account
     */
    const SHARED_WITHIN_ACCOUNT = 2;

    /**
     * The same as WITHIN_ACCOUNT but additionally restricted by the environment
     * @deprecated
     */
    const SHARED_WITHIN_ENV = 3;

    /**
     * The project is archived
     */
    const ARCHIVED = 1;

    /**
     * The project is not archived
     */
    const NOT_ARCHIVED = 0;

    /**
     * Project identifier (UUID)
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="uuid")
     * @var string
     */
    public $projectId;

    /**
     * Cost centre identifier (UUID)
     *
     * @Column(type="uuid")
     * @var string
     */
    public $ccId;

    /**
     * The name of the cost centre
     *
     * @var string
     */
    public $name;

    /**
     * The account which is associated with the project.
     * Global by default
     *
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * Share type
     *
     * @Column(type="integer")
     * @var int
     */
    public $shared;

    /**
     * The identifier of the environment the project is associated with.
     *
     * It's used only in combination with share type - 3
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

    /**
     * Identifier of the user who created this project
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $createdById;

    /**
     * Email address of the user who created record
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $createdByEmail;

    /**
     * The date the record was created on
     *
     * @Column(type="UTCDatetime")
     * @var \DateTime
     */
    public $created;

    /**
     * Whether the project has been archived
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $archived;

    /**
     * Array of the properties
     *
     * @var ArrayCollection
     */
    private $_properties;

    /**
     * Cost centre
     *
     * @var CostCentreEntity
     */
    private $_cc;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->shared = self::SHARED_WITHIN_CC;
        $this->archived = false;
        $this->created = new DateTime('now', new DateTimeZone('UTC'));
    }

    /**
     * Gets property with name
     *
     * @param  string      $name        The name of the property
     * @param  bool        $ignoreCache optional Should it ignore cache or not
     * @return string|null Returns the property entity or null
     */
    public function getProperty($name, $ignoreCache = false)
    {
        if ($this->_properties === null || $ignoreCache) {
            $this->loadProperties();
        }

        return isset($this->_properties[$name]) ? $this->_properties[$name]->value : null;
    }

    /**
     * Sets property with specified name
     *
     * @param    string    $name  The unique name of the property
     * @param    string    $value The value of the property
     * @return   CostCentreEntity
     */
    public function setProperty($name, $value)
    {
        if ($this->_properties === null) {
            $this->_properties = new ArrayCollection(array());
        }

        $property = new ProjectPropertyEntity();
        $property->projectId = $this->projectId;
        $property->name = $name;
        $property->value = $value;

        $this->_properties[$property->name] = $property;

        return $this;
    }

    /**
     * Saves property
     *
     * It will immediately save specified record to database, so that
     * cost centre must exist before you call this method.
     *
     * @param    string    $name  The unique name of the property
     * @param    string    $value The value of the property
     * @return   CostCentreEntity
     */
    public function saveProperty($name, $value)
    {
        $this->setProperty($name, $value);
        $this->_properties[$name]->save();

        return $this;
    }

    /**
     * Gets all collection of the properties for this cost centre
     *
     * @return ArrayCollection|null
     */
    public function getProperties()
    {
        return $this->_properties;
    }

    /**
     * Loads all properties to entity
     */
    public function loadProperties()
    {
        $this->_properties = new ArrayCollection([]);
        foreach (ProjectPropertyEntity::findByProjectId($this->projectId) as $item) {
            $this->_properties[$item->name] = $item;
        }
    }

    /**
     * Gets parent Cost Center
     *
     * @return CostCentreEntity Returns cost centre entity
     */
    public function getCostCenter()
    {
        if ($this->_cc === null || $this->_cc->ccId != $this->ccId) {
            $this->_cc = CostCentreEntity::findPk($this->ccId);
        }

        return $this->_cc;
    }

    /**
     * Sets a parent costcenter
     *
     * @param   CostCentreEntity  $cc
     * @return  ProjectEntity
     */
    public function setCostCenter(CostCentreEntity $cc = null)
    {
        $this->_cc = $cc === null || $cc->ccId === null ? null : $cc;

        $this->ccId = $cc === null ? null : $cc->ccId;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::save()
     */
    public function save()
    {
        //Checks data integrity.
        $criteria = [['name' => $this->name], ['ccId' => $this->ccId]];

        if ($this->projectId) {
            $criteria[] = ['projectId' => ['$ne' => $this->projectId]];
        }

        //The name of the project should be unique withing the current cost center
        $item = ProjectEntity::findOne($criteria);

        if ($item) {
            throw new AnalyticsException(sprintf(
                'A Project with this name already exists. Please choose another name.'
            ));
        }

        parent::save();

        if ($this->projectId && \Scalr::getContainer()->analytics->enabled) {
            \Scalr::getContainer()->analytics->tags->syncValue(
                ($this->accountId ?: 0), \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_PROJECT, $this->projectId, $this->name
            );
        }
    }

    /**
     * Checks whether project can be removed
     *
     * If Project can't be archived and removed it will throw an exception
     *
     * @return   boolean   Returns TRUE if Project can be removed or false if it can be archived or
     *                     throws an exception otherwise
     * @throws   AnalyticsException
     */
    public function checkRemoval()
    {
        //Checks data integrity
        if ($this->projectId == Usage::DEFAULT_PROJECT_ID) {
            throw new AnalyticsException(sprintf(
                "'%s' is default automatically created Project and it can not be archived.",
                $this->name
            ));
        }

        $farm = \Scalr::getDb()->GetRow("
            SELECT f.id, f.name FROM farms f
            JOIN farm_settings fs ON fs.farmid = f.id
            WHERE fs.name = ? AND fs.value = ?
            LIMIT 1
        ", [
            Entity\FarmSetting::PROJECT_ID,
            strtolower($this->projectId)
        ]);

        if ($farm) {
            throw new AnalyticsException(sprintf(
                "Project '%s' can not be archived because it is used by the farm '%s' (id:%d). "
              . "Reallocate '%s' to another project first.",
                $this->name, $farm['name'], $farm['id'], $farm['name'], $this->name
            ));
        }

        $bAllowedToRemove = $this->projectId != Usage::DEFAULT_PROJECT_ID;

        //Are there any record for this project in the usage statistics?
        if ($bAllowedToRemove && \Scalr::getContainer()->analytics->enabled) {
            $budget = QuarterlyBudgetEntity::findOne([
                ['subjectType'     => QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT],
                ['subjectId'       => $this->projectId],
                ['cumulativespend' => ['$gt' => 0]]
            ]);
            if ($budget) {
                //It can only be archived
                $bAllowedToRemove = false;
            }
        }

        return $bAllowedToRemove;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::delete()
     */
    public function delete()
    {
        if ($this->checkRemoval()) {
            //Completely remove it
            parent::delete();
            ReportEntity::deleteBy([['subjectId' => $this->projectId], ['subjectType' => ReportEntity::SUBJECT_TYPE_PROJECT]]);
            NotificationEntity::deleteBy([['subjectId' => $this->projectId], ['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT]]);
        } else {
            //Archive it
            $this->archived = true;
            $this->save();
        }
    }

    /**
     * Get the list of the farms which are assigned to specified project
     *
     * @return  array     Returns the array looks like [farm_id => name]
     */
    public function getFarmsList()
    {
        if ($this->projectId === null) {
            throw new \RuntimeException(sprintf(
                "Identifier of the project has not been initialized yet for %s",
                get_class($this)
            ));
        }
        return \Scalr::getContainer()->analytics->projects->getFarmsList($this->projectId);
    }

    /**
     * Gets quarterly budget of specified year for the Project
     *
     * @param   int    $year   The year
     * @throws  \RuntimeException
     * @return  \Scalr\Model\Collections\ArrayCollection Returns collection of the QuarterlyBudgetEntity objects
     */
    public function getQuarterlyBudget($year)
    {
        if ($this->projectId === null) {
            throw new \RuntimeException(sprintf(
                "Identifier of the project has not been initialized yet for %s",
                get_class($this)
            ));
        }
        return QuarterlyBudgetEntity::getProjectBudget($year, $this->projectId);
    }


    /**
     * {@inheritdoc}
     * @see AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        if ($user->isFinAdmin() || $user->isScalrAdmin()) {
            return true;
        } else if ($modify) {
            return false;
        }

        switch ($this->shared) {
            case static::SHARED_WITHIN_ACCOUNT:
                return $this->accountId == $user->getAccountId();

            case static::SHARED_WITHIN_CC:
                return $this->getCostCenter()->hasAccessPermissions($user, $environment, $modify);

            default:
                return false;
        }
    }
}
