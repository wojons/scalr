<?php
namespace Scalr\Stats\CostAnalytics\Iterator;

use Scalr\DataType\Iterator\AbstractFilter;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Exception\AnalyticsException;
use Scalr_Account_User,
    Scalr_Environment,
    InvalidArgumentException;

/**
 * Shared projects filter iterator
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (25.04.2014)
 */
class SharedProjectsFilterIterator extends AbstractFilter
{

    /**
     * User trying to get access to project
     *
     * @var Scalr_Account_User
     */
    private $user;

    /**
     * Environment
     *
     * @var Scalr_Environment
     */
    private $environment;

    /**
     * Default filter
     *
     * @var bool
     */
    private $default;

    /**
     * ID of the Cost Centre to filter
     *
     * @var string
     */
    private $ccId;

    /**
     * Constructor
     *
     * @param    ArrayCollection     $collection  Collection of the projects
     * @param    string              $ccId        Identifier of the cost centre to check
     * @param    Scalr_Account_User  $user        optional The user
     * @param    Scalr_Environment   $environment optional An envrironment
     * @throws   InvalidArgumentException
     */
    public function __construct(ArrayCollection $collection, $ccId, Scalr_Account_User $user = null, Scalr_Environment $environment = null)
    {
        parent::__construct($collection->getIterator());

        if ($user !== null && !($user instanceof Scalr_Account_User)) {
            throw new InvalidArgumentException("User argument must be instance of the Scalr_Account_User class.");
        }

        $this->user = $user;

        if ($environment !== null && !($environment instanceof Scalr_Environment)) {
            throw new InvalidArgumentException("Environment argument must be instance of the Scalr_Environment class.");
        }

        $this->environment = $environment;

        $this->ccId = $ccId;

        $this->default = $this->user === null && $this->environment === null;
    }


    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\Iterator\AbstractFilter::accept()
     */
    public function accept()
    {
        $project = $this->current();

        $samecc = $project->ccId == $this->ccId;

        if ($this->default || !$samecc) {
            return $samecc;
        }

        if ($project->shared == ProjectEntity::SHARED_WITHIN_CC) {
            return $samecc;
        }

        switch ($project->shared) {
            case ProjectEntity::SHARED_TO_OWNER:
                if (isset($this->user) && $project->createdById == $this->user->id) return $samecc;
                break;

            case ProjectEntity::SHARED_WITHIN_ACCOUNT:
                if (isset($this->user) && $project->accountId == $this->user->getAccountId()) return $samecc;
                break;

            case ProjectEntity::SHARED_WITHIN_ENV:
                if (isset($this->environment) && $project->envId == $this->environment->id) return $samecc;
                break;

            default:
                throw new AnalyticsException(sprintf("Unexpected project share type (%d)", $project->shared));
                break;
        }

        return false;
    }
}