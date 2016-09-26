<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\Marker;

/**
 * ListSubnetsFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    08.05.2013
 */
class ListSubnetsFilter extends Marker
{

    /**
     * Filters the list of subnets by name
     *
     * @var array
     */
    private $name;

    /**
     * Filter by id
     *
     * @var array
     */
    private $id;

    //Additional filters can be added.

    /**
     * Convenient constructor
     *
     * @param   string|array        $name         optional The one or more name of the subnet
     * @param   string|array        $id           optional The one or more ID of the subnet
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     */
    public function __construct($name = null, $id = null, $marker = null, $limit = null)
    {
        parent::__construct($marker, $limit);
        $this->setName($name);
        $this->setId($id);
    }

    /**
     * Initializes new object
     *
     * @param   string|array        $name         optional The one or more name of the subnet
     * @param   string|array        $id           optional The one or more ID of the subnet
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     * @return  ListSubnetsFilter   Returns a new ListSubnetsFilter object
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }

    /**
     * Gets the list of the subnet name.
     *
     * @return  array  Returns array of the name of the subnet to filter
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets one or more name of the subnet to filter
     *
     * @param   string $name The list of the name of the subnet to filter
     * @return  ListSubnetsFilter
     */
    public function setName($name = null)
    {
        $this->name = array();
        return $name == null ? $this : $this->addName($name);
    }

    /**
     * Adds one or more name of the subnet to filter
     *
     * @param   string|array $name The name of the subnet to filter
     */
    public function addName($name)
    {
        return $this->_addPropertyValue('name', $name);
    }

    /**
     * Gets the list of the ID of the subnet
     *
     * @return  array Returns the list of the ID of the subnet
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the list of ID of the subnet
     *
     * @param   array|string   $id  The one or more ID of the subnet
     * @return  ListSubnetsFilter
     */
    public function setId($id = null)
    {
        $this->id = array();
        return $id === null ? $this : $this->addId($id);
    }

    /**
     * Adds one or more ID of the subnet to filter
     *
     * @param   string|array $id The one or more ID of the subnet to filter
     * @return  ListSubnetsFilter
     */
    public function addId($id)
    {
        return $this->_addPropertyValue('id', $id);
    }
}