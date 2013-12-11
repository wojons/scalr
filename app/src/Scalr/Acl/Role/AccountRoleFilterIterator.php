<?php

namespace Scalr\Acl\Role;


/**
 * AccountRoleFilterIterator class
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    09.08.2013
 */
class AccountRoleFilterIterator extends \FilterIterator
{
    /**
     * Filter options
     *
     * @var array
     */
    private $exclude;

    /**
     * Constructor
     *
     * @param   \Iterator $iterator Inner Iterator
     * @param   array     $filter   Filter options
     */
    public function __construct(\Iterator $iterator, array $filter)
    {
        parent::__construct($iterator);
        $this->exclude = $filter;
    }

	/**
     * {@inheritdoc}
     * @see FilterIterator::accept()
     */
    public function accept()
    {
        $ret = true;
        if (!empty($this->exclude['teamRoles'])) {
            $ret = $ret && !$this->getInnerIterator()->current()->isTeamRole();
        }
        if ($ret && !empty($this->exclude['roles'])) {
            $ret = $ret && (bool)$this->getInnerIterator()->current()->isTeamRole();
        }
        return $ret;
    }
}