<?php

namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Exception\AnalyticsException;
use Scalr\Stats\CostAnalytics\Usage;
use DateTime, DateTimeZone;
use Scalr\Model\Collections\ArrayCollection;
use Scalr_Environment;

/**
 * CostCentreEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (10.02.2014)
 * @Entity
 * @Table(name="ccs")
 */
class CostCentreEntity extends \Scalr\Model\AbstractEntity implements AccessPermissionsInterface
{
    /**
     * The cost center is archived
     */
    const ARCHIVED = 1;

    /**
     * The cost center is not archived
     */
    const NOT_ARCHIVED = 0;

    /**
     * Cost centre identifier (UUID)
     *
     * @Id
     * @GeneratedValue("CUSTOM")
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
     * The account which is owned by this cost centre.
     * Global by default
     *
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * Identifier of the user who created this cost center
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
     * Whether the cost centre has been archived
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
     * Has it projects or not
     *
     * @var bool
     */
    private $hasProjects;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->archived = false;
        $this->created = new DateTime('now', new DateTimeZone('UTC'));
    }

    /**
     * Gets property with name
     *
     * @param  string      $name        The name of the property
     * @param  bool        $ignoreCache optional Should it ignore cache or not
     * @return string|null Returns the property value or null
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

        $property = new CostCentrePropertyEntity();
        $property->ccId = $this->ccId;
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
        $this->_properties = new ArrayCollection(array());
        foreach (CostCentrePropertyEntity::findByCcId($this->ccId) as $item) {
            $this->_properties[$item->name] = $item;
        }
    }

    /**
     * Gets all projects associated with the cost centre
     *
     * @return  ArrayCollection  Returns collection of the ProjectEntity objects
     */
    public function getProjects()
    {
        return ProjectEntity::result(self::RESULT_ENTITY_COLLECTION)->findByCcId($this->ccId);
    }

    /**
     * Checks wheter this cost center has at least one project
     *
     * @return bool Returns true if the cost center has projects associated to it
     */
    public function hasProjects()
    {
        if ($this->hasProjects === null) {
            $this->hasProjects = (bool) $this->db()->Execute("
                SELECT EXISTS (SELECT 1 FROM projects WHERE cc_id = UNHEX(?))
            ",[
                $this->type('ccId')->toDb($this->ccId)
            ]);
        }

        return $this->hasProjects;
    }

    /**
     * Sets whether current cost center has projects assigned to it
     *
     * @param   bool    $has
     * @return  CostCentreEntity
     */
    public function setHasProjects($has)
    {
        $this->hasProjects = (bool) $has;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::save()
     */
    public function save()
    {
        //Checks data integrity.
        $criteria = [['name' => $this->name]];

        if ($this->ccId) {
            $criteria[] = ['ccId' => ['$ne' => $this->ccId]];
        }

        //The name of the cost centre should be unique withing the global
        $item = CostCentreEntity::findOne($criteria);

        if ($item) {
            throw new AnalyticsException(sprintf(
                'A Cost Center with this name already exists. Please choose another name.'
            ));
        }

        parent::save();

        if ($this->ccId && \Scalr::getContainer()->analytics->enabled) {
            \Scalr::getContainer()->analytics->tags->syncValue(
                ($this->accountId ?: 0), \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_COST_CENTRE, $this->ccId, $this->name
            );
        }
    }

    /**
     * Checks whether cost ceter can be removed
     *
     * If Cost centre can't be archived and removed it will throw an exception
     *
     * @return   boolean   Returns TRUE if cost center can be removed or false if it can be archived or
     *                     throws an exception otherwise
     * @throws   AnalyticsException
     */
    public function checkRemoval()
    {
        //Checks data integrity

        if ($this->ccId == Usage::DEFAULT_CC_ID) {
            throw new AnalyticsException(sprintf(
                "'%s' is default automatically created Cost Center and it can not be archived.",
                $this->name
            ));
        }

        $accountCcs = \Scalr::getDb()->GetAll("
            SELECT ac.account_id, c.name FROM account_ccs ac
            JOIN clients c ON c.id = ac.account_id
            WHERE ac.cc_id = UNHEX(?)
            LIMIT 4
        ", [$this->type('ccId')->toDb($this->ccId)]);

        $someofthem = '';

        foreach ($accountCcs as $ac) {
            $cnt = 0;

            if ($cnt++ > 3) {
                $someofthem .= ' ...';
                break;
            }
            $someofthem .= ', "' . $ac['name'] . '"';
        }

        if (count($accountCcs) > 0) {
            throw new AnalyticsException(sprintf(
                "Cost center '%s' can not be archived because it is used by following account%s: %s. "
              . "Please contact your scalr admin to set free '%s' before you can archive it.",
                $this->name,
                (count($accountCcs) > 1 ? 's' : ''),
                substr($someofthem, 2),
                $this->name
            ));
        }

        $env = \Scalr::getDb()->GetRow("
            SELECT e.id, e.name FROM client_environments e
            JOIN client_environment_properties ep ON ep.env_id = e.id
            WHERE ep.name = ? AND ep.value = ?
            LIMIT 1
        ", [
            \Scalr_Environment::SETTING_CC_ID,
            strtolower($this->ccId)
        ]);

        if ($env) {
            throw new AnalyticsException(sprintf(
                "Cost center '%s' can not be archived because it is used by the environment '%s' (id:%d). "
              . "Please contact your scalr admin to reallocate %s to a new cost center before you can archive '%s'.",
                $this->name, $env['name'], $env['id'], $env['name'], $this->name
            ));
        }

        //Gets all projects related to cost centre
        $relatedProjects = $this->getProjects();

        //Whether Cost Centre is assigned to active Projects
        if ($relatedProjects->filterByArchived(false)->count()) {
            $someofthem = ''; $cnt = 0;
            foreach ($relatedProjects as $p) {
                if ($cnt++ > 3) {
                    $someofthem .= ' ...';
                    break;
                }
                $someofthem .= ', "' . $p->name . '"';
            }
            throw new AnalyticsException(sprintf(
                "Cost center '%s' can not be archived because it is used by %d project%s including: %s. "
              . "Please contact your scalr admin to reallocate %s to a new cost center before you can archive '%s'.",
                $this->name, $relatedProjects->count(),
                ($relatedProjects->count() > 1 ? 's' : ''),
                substr($someofthem, 2),
                ($relatedProjects->count() > 1 ? 'them' : substr($someofthem, 2)),
                $this->name
            ));
        }

        //It can be comletely removed if there isn't any project that has been assigned to cost centre.
        $bAllowedToRemove = $relatedProjects->count() == 0 && $this->ccId != Usage::DEFAULT_CC_ID;

        //Are there any record for this cost centre in the usage statistics?
        if ($bAllowedToRemove && \Scalr::getContainer()->analytics->enabled) {
            $budget = QuarterlyBudgetEntity::findOne([
                ['subjectType'     => QuarterlyBudgetEntity::SUBJECT_TYPE_CC],
                ['subjectId'       => $this->ccId],
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
        if (!$this->checkRemoval()) {
            //Archive it
            $this->archived = true;
            $this->save();
        } else {
            //Completely remove it
            parent::delete();
        }
    }

    /**
     * Get the list of the environment which are assigned to specified cost centre
     *
     * @return  array     Returns the array looks like [env_id => name]
     */
    public function getEnvironmentsList()
    {
        if ($this->ccId === null) {
            throw new \RuntimeException(sprintf(
                "Identifier of the cost center has not been initialized yet for %s",
                get_class($this)
            ));
        }
        return \Scalr::getContainer()->analytics->ccs->getEnvironmentsList($this->ccId);
    }

    /**
     * Gets quarterly budget of specified year for the CC
     *
     * @param   int    $year   The year
     * @throws  \RuntimeException
     * @return  \Scalr\Model\Collections\ArrayCollection Returns collection of the QuarterlyBudgetEntity objects
     */
    public function getQuarterlyBudget($year)
    {
        if ($this->projectId === null) {
            throw new \RuntimeException(sprintf(
                "Identifier of the cost center has not been initialized yet for %s",
                get_class($this)
            ));
        }
        return QuarterlyBudgetEntity::getCcBudget($year, $this->ccId);
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
            //FIXME CostCentreEntity::hasAccessPermissions() should be corrected according to logic
            return false;
        }

        if ($environment) {
            return $this->ccId == Scalr_Environment::init()->loadById($environment->id)->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);
        }

        return false;
    }
}
